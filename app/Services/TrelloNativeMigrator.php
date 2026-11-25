<?php

namespace App\Services;

use App\Models\Designer;
use App\Models\Media;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * One-way, idempotent import of Trello card content (descriptions, comments, attachments) into DOES.
 *
 * Scope: every order shown in the Overview (i.e. not in the trash) that is linked to a Trello card.
 * Safe to re-run: records are keyed by Trello ids, so a second run only adds what changed and
 * removes what was deleted on Trello since the previous run.
 */
class TrelloNativeMigrator
{
    public const STATUS_MIGRATED = 'migrated';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_CARD_NOT_FOUND = 'card_not_found';

    public const STATUS_DUPLICATE_CARD = 'duplicate_card';

    protected string $baseUrl = 'https://api.trello.com/1';

    /** @var array<string, array<string, mixed>> Board cards keyed by both id and shortLink. */
    protected array $cards = [];

    /** @var array<string, list<array<string, mixed>>> Comment actions grouped by card id, oldest first. */
    protected array $commentsByCard = [];

    /** @var array<string, string> Trello member id => full name. */
    protected array $memberNames = [];

    /** @var array<string, int> Canonical card id => id of the order that owns its content. */
    protected array $cardOwners = [];

    /** @var array<string, int|null> Memoized author name => user id lookups. */
    protected array $userIdsByName = [];

    /** @var array<string, int|null> Trello member id => user id (via designers.trello_member_id). */
    protected array $userIdsByMember = [];

    /** @var list<string> */
    protected array $knownNames = [];

    protected bool $boardLoaded = false;

    public function __construct(protected MediaLibraryService $library) {}

    /**
     * Orders in migration scope: everything not in the trash that points at a Trello card.
     */
    public function scopedOrders(): Builder
    {
        return Order::query()
            ->whereNotNull('trello_card_id')
            ->where('trello_card_id', '!=', '')
            ->orderBy('id');
    }

    /**
     * Fetch the whole board in a handful of requests: cards with attachments, every comment, members.
     */
    public function loadBoard(): void
    {
        $boardId = $this->boardId();
        if ($boardId === '') {
            throw new RuntimeException(__('No Trello board is configured.'));
        }

        $cards = $this->trelloGet("/boards/{$boardId}/cards/all", [
            'fields' => 'id,shortLink,name,desc,closed',
            'attachments' => 'true',
            'attachment_fields' => 'id,name,bytes,mimeType,isUpload,url,date,idMember',
        ]);

        $this->cards = [];
        foreach ($cards as $card) {
            $this->cards[$card['id']] = $card;
            if (! empty($card['shortLink'])) {
                $this->cards[$card['shortLink']] = $card;
            }
        }

        $this->commentsByCard = [];
        foreach ($this->fetchAllCommentActions($boardId) as $action) {
            $cardId = $action['data']['card']['id'] ?? null;
            if ($cardId) {
                $this->commentsByCard[$cardId][] = $action;
            }
        }
        foreach ($this->commentsByCard as &$actions) {
            usort($actions, fn (array $a, array $b): int => strcmp($a['date'] ?? '', $b['date'] ?? ''));
        }
        unset($actions);

        $this->memberNames = [];
        foreach ($this->trelloGet("/boards/{$boardId}/members", ['fields' => 'fullName,username']) as $member) {
            $this->memberNames[$member['id']] = $member['fullName'] ?: $member['username'];
        }

        $this->buildCardOwners();
        $this->prepareAuthorLookups();
        $this->boardLoaded = true;
    }

    /**
     * The card linked to an order, if it still exists on the board.
     *
     * @return array<string, mixed>|null
     */
    public function cardFor(Order $order): ?array
    {
        return $this->cards[trim((string) $order->trello_card_id)] ?? null;
    }

    /**
     * Whether this order receives the card's content (when several orders share a card, only the oldest does).
     *
     * @param  array<string, mixed>  $card
     */
    public function ownsCard(Order $order, array $card): bool
    {
        return ($this->cardOwners[$card['id']] ?? $order->id) === $order->id;
    }

    /**
     * Import one order's description, comments and attachments.
     *
     * @return array{status: string, comments: int, comments_removed: int, files: int, links: int, files_pending: int, media_removed: int, description: bool, errors: list<string>}
     */
    public function migrateOrder(Order $order, bool $withFiles = true): array
    {
        if (! $this->boardLoaded) {
            $this->loadBoard();
        }

        $result = [
            'status' => self::STATUS_MIGRATED,
            'comments' => 0,
            'comments_removed' => 0,
            'files' => 0,
            'links' => 0,
            'files_pending' => 0,
            'media_removed' => 0,
            'description' => false,
            'errors' => [],
        ];

        $card = $this->cardFor($order);
        if ($card === null) {
            $result['status'] = self::STATUS_CARD_NOT_FOUND;

            return $result;
        }

        if (! $this->ownsCard($order, $card)) {
            $result['status'] = self::STATUS_DUPLICATE_CARD;

            return $result;
        }

        $mediaByAttachment = $this->importAttachments($order, $card, $withFiles, $result);
        $urlMap = $this->attachmentUrlMap($card, $mediaByAttachment);

        $this->importComments($order, $card, $urlMap, $result);

        $description = trim((string) ($card['desc'] ?? ''));
        $order->forceFill([
            'description' => $description !== '' ? $this->rewriteAttachmentUrls($description, $urlMap) : null,
            'trello_migrated_at' => now(),
        ])->saveQuietly();
        $result['description'] = $description !== '';

        if (! empty($result['errors'])) {
            $result['status'] = self::STATUS_PARTIAL;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array<string, mixed>  $result
     * @return array<string, Media> Trello attachment id => media record
     */
    protected function importAttachments(Order $order, array $card, bool $withFiles, array &$result): array
    {
        $attachments = $card['attachments'] ?? [];
        $existing = Media::withTrashed()
            ->where('order_id', $order->id)
            ->whereNotNull('trello_attachment_id')
            ->get()
            ->keyBy('trello_attachment_id');

        $mediaByAttachment = [];
        foreach ($attachments as $attachment) {
            $attachmentId = $attachment['id'];

            if ($existing->has($attachmentId)) {
                $mediaByAttachment[$attachmentId] = $existing[$attachmentId];

                continue;
            }

            $attributes = [
                'source' => Media::SOURCE_TRELLO_IMPORT,
                'trello_attachment_id' => $attachmentId,
                'uploader_name' => $this->memberNames[$attachment['idMember'] ?? ''] ?? null,
                'uploaded_by' => $this->userIdForMember($attachment['idMember'] ?? null),
                'created_at' => $this->trelloDate($attachment['date'] ?? null),
            ];

            if (empty($attachment['isUpload'])) {
                $mediaByAttachment[$attachmentId] = $this->library->storeLinkForOrder(
                    $order,
                    (string) ($attachment['url'] ?? ''),
                    (string) ($attachment['name'] ?? ''),
                    $attributes,
                );
                $result['links']++;

                continue;
            }

            if (! $withFiles) {
                $result['files_pending']++;

                continue;
            }

            try {
                $download = $this->downloadAttachment((string) $attachment['url']);
                $name = $this->attachmentFileName($attachment);
                $mime = $attachment['mimeType'] ?: ($download['mime'] ?: 'application/octet-stream');

                $mediaByAttachment[$attachmentId] = $this->library->storeForOrder($order, $download['content'], $name, $mime, $attributes);
                $result['files']++;
            } catch (\Throwable $e) {
                $result['errors'][] = "Adjunto '{$attachment['name']}': ".$e->getMessage();
            }
        }

        $currentIds = array_column($attachments, 'id');
        foreach ($existing as $attachmentId => $media) {
            if (! in_array($attachmentId, $currentIds, true) && ! $media->trashed()) {
                try {
                    $this->library->delete($media);
                    $result['media_removed']++;
                } catch (\Throwable $e) {
                    $result['errors'][] = "Eliminar '{$media->name}': ".$e->getMessage();
                }
            }
        }

        return $mediaByAttachment;
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array<string, string>  $urlMap
     * @param  array<string, mixed>  $result
     */
    protected function importComments(Order $order, array $card, array $urlMap, array &$result): void
    {
        $actions = $this->commentsByCard[$card['id']] ?? [];
        $seenActionIds = [];

        foreach ($actions as $action) {
            $parsed = $this->parseCommentAuthor($action);
            $createdAt = $this->trelloDate($action['date'] ?? null) ?? now();
            $editedAt = $this->trelloDate($action['data']['dateLastEdited'] ?? null);

            $comment = OrderComment::firstOrNew(['trello_action_id' => $action['id']]);
            $comment->forceFill([
                'order_id' => $order->id,
                'user_id' => $parsed['user_id'],
                'author_name' => $parsed['author_name'],
                'body' => $this->rewriteAttachmentUrls($parsed['body'], $urlMap),
                'edited_at' => $editedAt,
                'created_at' => $createdAt,
                'updated_at' => $editedAt ?? $createdAt,
            ]);
            $comment->timestamps = false;
            $comment->save();

            $seenActionIds[] = $action['id'];
            $result['comments']++;
        }

        $result['comments_removed'] = OrderComment::query()
            ->where('order_id', $order->id)
            ->whereNotNull('trello_action_id')
            ->whereNotIn('trello_action_id', $seenActionIds)
            ->delete();
    }

    /**
     * Resolve who wrote a Trello comment. Comments posted from DOES were sent through a shared Trello
     * token with a "**Name (Kudos DOES):**" prefix, so the real author is recovered from that prefix.
     *
     * @param  array<string, mixed>  $action
     * @return array{author_name: string, user_id: int|null, body: string}
     */
    public function parseCommentAuthor(array $action): array
    {
        $raw = (string) ($action['data']['text'] ?? '');
        $creator = $action['memberCreator'] ?? [];
        $trelloName = $creator['fullName'] ?? ($creator['username'] ?? ($this->memberNames[$action['idMemberCreator'] ?? ''] ?? 'Usuario Trello'));

        if (preg_match('/^\*\*([^*]+?)\s*\((?:Kudos\s*DOES|Kudos)\):\*\*\s*(?:\r?\n)*(.*)$/si', $raw, $matches)) {
            $name = trim($matches[1]);

            return [
                'author_name' => $name,
                'user_id' => $this->userIdForName($name),
                'body' => trim($matches[2]) !== '' ? trim($matches[2]) : $raw,
            ];
        }

        if (preg_match('/^\*\*([^*]+?):\*\*\s*(?:\r?\n)*(.*)$/si', $raw, $matches) && in_array(trim($matches[1]), $this->knownNames, true)) {
            $name = trim($matches[1]);

            return [
                'author_name' => $name,
                'user_id' => $this->userIdForName($name),
                'body' => trim($matches[2]) !== '' ? trim($matches[2]) : $raw,
            ];
        }

        return [
            'author_name' => $trelloName,
            'user_id' => $this->userIdForMember($action['idMemberCreator'] ?? ($creator['id'] ?? null)),
            'body' => $raw,
        ];
    }

    /**
     * Replace Trello attachment URLs in markdown with the app's own media URLs.
     *
     * @param  array<string, string>  $urlMap  Trello URL or attachment id => app URL
     */
    public function rewriteAttachmentUrls(string $text, array $urlMap): string
    {
        if ($text === '' || empty($urlMap)) {
            return $text;
        }

        $exactUrls = array_filter($urlMap, fn (string $key): bool => str_starts_with($key, 'http'), ARRAY_FILTER_USE_KEY);
        if (! empty($exactUrls)) {
            uksort($exactUrls, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
            $text = strtr($text, $exactUrls);
        }

        return preg_replace_callback(
            '#https?://(?:api\.)?trello\.com/1/cards/[A-Za-z0-9]+/attachments/([a-f0-9]{24})/download/[^\s)\]"\'<>]+#i',
            fn (array $m): string => $urlMap[$m[1]] ?? $m[0],
            $text,
        ) ?? $text;
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array<string, Media>  $mediaByAttachment
     * @return array<string, string>
     */
    protected function attachmentUrlMap(array $card, array $mediaByAttachment): array
    {
        $map = [];
        foreach ($card['attachments'] ?? [] as $attachment) {
            $media = $mediaByAttachment[$attachment['id']] ?? null;
            if ($media === null || $media->isLink() || $media->trashed()) {
                continue;
            }

            $map[$attachment['id']] = (string) $media->file_url;
            if (! empty($attachment['url'])) {
                $map[$attachment['url']] = (string) $media->file_url;
            }
        }

        return $map;
    }

    /**
     * Download an attachment's bytes into memory (Trello caps uploads at 10 MB, so this never needs disk).
     *
     * @return array{content: string, mime: string|null}
     */
    protected function downloadAttachment(string $url): array
    {
        $response = Http::withHeaders([
            'Authorization' => sprintf('OAuth oauth_consumer_key="%s", oauth_token="%s"', $this->apiKey(), $this->apiToken()),
        ])
            ->timeout(120)
            ->retry(3, 2000, fn (\Throwable $e): bool => $this->isRetryable($e), throw: false)
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException(__('Trello responded with HTTP :status while downloading.', ['status' => $response->status()]));
        }

        return [
            'content' => $response->body(),
            'mime' => $response->header('Content-Type') ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    protected function attachmentFileName(array $attachment): string
    {
        $name = trim((string) ($attachment['name'] ?? ''));
        if ($name === '' || str_starts_with($name, 'http')) {
            $name = urldecode(basename((string) parse_url((string) ($attachment['url'] ?? ''), PHP_URL_PATH))) ?: 'adjunto';
        }

        return $name;
    }

    /**
     * Page through every comment on the board (Trello caps a page at 1000 actions).
     *
     * @return list<array<string, mixed>>
     */
    protected function fetchAllCommentActions(string $boardId): array
    {
        $all = [];
        $before = null;

        do {
            $page = $this->trelloGet("/boards/{$boardId}/actions", array_filter([
                'filter' => 'commentCard',
                'limit' => 1000,
                'fields' => 'id,data,date,idMemberCreator',
                'memberCreator' => 'true',
                'memberCreator_fields' => 'fullName,username',
                'before' => $before,
            ]));

            array_push($all, ...$page);
            $before = $page !== [] ? end($page)['id'] : null;
        } while (count($page) === 1000);

        return $all;
    }

    /**
     * Decide which order owns each card when several orders point at the same card: the oldest one.
     */
    protected function buildCardOwners(): void
    {
        $this->cardOwners = [];
        foreach ($this->scopedOrders()->get(['id', 'trello_card_id']) as $order) {
            $card = $this->cardFor($order);
            if ($card !== null && ! isset($this->cardOwners[$card['id']])) {
                $this->cardOwners[$card['id']] = $order->id;
            }
        }
    }

    protected function prepareAuthorLookups(): void
    {
        $this->userIdsByName = [];
        $this->userIdsByMember = Designer::query()
            ->whereNotNull('trello_member_id')
            ->whereNotNull('user_id')
            ->pluck('user_id', 'trello_member_id')
            ->all();

        $this->knownNames = User::query()->whereNotNull('name')->pluck('name')
            ->merge(Designer::query()->pluck('name'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function userIdForName(string $name): ?int
    {
        if (array_key_exists($name, $this->userIdsByName)) {
            return $this->userIdsByName[$name];
        }

        $userId = User::query()->where('name', $name)->value('id')
            ?? Designer::query()->where('name', $name)->whereNotNull('user_id')->value('user_id')
            ?? User::query()->where('name', 'like', "%{$name}%")->value('id');

        return $this->userIdsByName[$name] = $userId !== null ? (int) $userId : null;
    }

    protected function userIdForMember(?string $memberId): ?int
    {
        if (empty($memberId)) {
            return null;
        }

        $userId = $this->userIdsByMember[$memberId] ?? null;

        return $userId !== null ? (int) $userId : null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    protected function trelloGet(string $path, array $query = []): array
    {
        $response = Http::timeout(120)
            ->retry(3, 2000, fn (\Throwable $e): bool => $this->isRetryable($e), throw: false)
            ->get($this->baseUrl.$path, array_merge($query, [
                'key' => $this->apiKey(),
                'token' => $this->apiToken(),
            ]));

        if (! $response->successful()) {
            throw new RuntimeException(__('Trello responded with HTTP :status at :path: :body', ['status' => $response->status(), 'path' => $path, 'body' => $response->body()]));
        }

        return $response->json() ?? [];
    }

    /**
     * Trello timestamps are UTC; convert them to the app timezone because Eloquent stores wall-clock time.
     */
    protected function trelloDate(?string $value): ?Carbon
    {
        return empty($value) ? null : Carbon::parse($value)->setTimezone(config('app.timezone'));
    }

    protected function isRetryable(\Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        return $exception instanceof RequestException
            && in_array($exception->response->status(), [429, 500, 502, 503, 504], true);
    }

    protected function boardId(): string
    {
        return trim((string) (Setting::get('trello_board_id') ?: config('services.trello.board_id')));
    }

    protected function apiKey(): string
    {
        return trim((string) (Setting::get('trello_api_key') ?: config('services.trello.api_key')));
    }

    protected function apiToken(): string
    {
        return trim((string) (Setting::get('trello_user_token') ?: config('services.trello.token')));
    }
}
