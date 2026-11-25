<?php

namespace Tests\Feature\Media;

use App\Models\Client;
use App\Models\Designer;
use App\Models\Media;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\TrelloNativeMigrator;
use App\Support\TrelloCutover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrelloNativeMigratorTest extends TestCase
{
    use FakesGoogleDrive, RefreshDatabase;

    protected const CARD_ID = '5f0000000000000000000001';

    protected const IMAGE_ATTACHMENT_ID = 'aa0000000000000000000001';

    protected const LINK_ATTACHMENT_ID = 'aa0000000000000000000002';

    /** @var list<array<string, mixed>> */
    protected array $cards = [];

    /** @var list<array<string, mixed>> */
    protected array $actions = [];

    /** @var list<list<array<string, mixed>>>|null When set, successive pages returned by the board actions endpoint. */
    protected ?array $actionPages = null;

    /** @var array<string, int> Download URL => HTTP status to return instead of the file. */
    protected array $failingDownloads = [];

    protected User $adrian;

    protected User $euraliz;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Cache::forget(GoogleDriveService::TOKEN_CACHE_KEY);
        $this->configureGoogleDrive();
        config([
            'services.trello.board_id' => 'board-1',
            'services.trello.api_key' => 'trello-key',
            'services.trello.token' => 'trello-token',
        ]);

        $this->adrian = User::factory()->create(['name' => 'Adrián Salvatori']);
        $this->euraliz = User::factory()->create(['name' => 'Euraliz Pérez']);
        Designer::create(['name' => 'Euraliz', 'slug' => 'euraliz', 'user_id' => $this->euraliz->id, 'trello_member_id' => 'member-euraliz']);

        $this->cards = [$this->card(self::CARD_ID)];
        $this->actions = [
            $this->commentAction('ac0000000000000000000001', self::CARD_ID, "**Adrián Salvatori (Kudos DOES):**\nListo el render ![render](".$this->downloadUrl(self::CARD_ID, self::IMAGE_ATTACHMENT_ID, 'plano.png').')', '2025-05-02T09:00:00.000Z', 'member-shared', 'Kudos Compartido'),
            $this->commentAction('ac0000000000000000000002', self::CARD_ID, 'Cliente aprobó la propuesta', '2025-05-03T09:00:00.000Z', 'member-euraliz', 'Euraliz Trello', '2025-05-03T10:30:00.000Z'),
            $this->commentAction('ac0000000000000000000003', self::CARD_ID, 'Comentario de alguien sin usuario', '2025-05-04T09:00:00.000Z', 'member-old', 'Andrea Antigua'),
        ];

        Http::fake(array_merge([
            'api.trello.com/1/boards/board-1/cards/all*' => fn () => Http::response($this->cards),
            'api.trello.com/1/boards/board-1/actions*' => fn () => Http::response($this->actionPages !== null ? (array_shift($this->actionPages) ?? []) : $this->actions),
            'api.trello.com/1/boards/board-1/members*' => Http::response([
                ['id' => 'member-euraliz', 'fullName' => 'Euraliz Trello', 'username' => 'euraliz'],
                ['id' => 'member-camila', 'fullName' => 'Camila Trello', 'username' => 'camila'],
            ]),
            'trello.com/1/cards/*/attachments/*/download/*' => fn (Request $request) => isset($this->failingDownloads[$request->url()])
                ? Http::response('error', $this->failingDownloads[$request->url()])
                : Http::response('BYTES-OF-'.basename($request->url()), 200, ['Content-Type' => 'image/png']),
        ], $this->googleDriveFakes()));
    }

    public function test_it_imports_description_comments_files_and_links_for_an_order(): void
    {
        $client = Client::create(['name' => 'Bravepro']);
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'company_name' => 'BRAVEPRO',
            'task_name' => 'SIGN DE BABY SHOWER',
            'wo_number' => 'WO 16512',
            'trello_card_id' => self::CARD_ID,
        ]);

        $result = $this->migrator()->migrateOrder($order);

        $this->assertSame(TrelloNativeMigrator::STATUS_MIGRATED, $result['status']);
        $this->assertSame(['comments' => 3, 'files' => 1, 'links' => 1], array_intersect_key($result, ['comments' => 0, 'files' => 0, 'links' => 0]));

        $order->refresh();
        $this->assertNotNull($order->trello_migrated_at);

        // Files land in Clientes/BRAVEPRO/WO 16512 · SIGN DE BABY SHOWER, with the bytes untouched.
        $orderFolder = $this->driveFolderPath('Clientes', 'BRAVEPRO', 'WO 16512 · SIGN DE BABY SHOWER');
        $this->assertNotNull($orderFolder);
        $this->assertSame($orderFolder, $order->drive_folder_id);
        $this->assertSame($this->driveFolderPath('Clientes', 'BRAVEPRO'), $client->fresh()->drive_folder_id);

        $image = Media::where('trello_attachment_id', self::IMAGE_ATTACHMENT_ID)->firstOrFail();
        $this->assertSame(Media::KIND_FILE, $image->kind);
        $this->assertSame(Media::SOURCE_TRELLO_IMPORT, $image->source);
        $this->assertSame('plano.png', $image->name);
        $this->assertSame($client->id, $image->client_id);
        $this->assertSame($this->euraliz->id, $image->uploaded_by);
        $this->assertSame('Euraliz Trello', $image->uploader_name);
        $this->assertSame('2025-05-01 10:00:00', $image->created_at->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('BYTES-OF-plano.png', $this->drive['files'][$image->drive_file_id]['content']);
        $this->assertSame($orderFolder, $this->drive['files'][$image->drive_file_id]['parent']);

        $link = Media::where('trello_attachment_id', self::LINK_ATTACHMENT_ID)->firstOrFail();
        $this->assertTrue($link->isLink());
        $this->assertSame('https://docs.google.com/document/d/brief', $link->external_url);
        $this->assertNull($link->drive_file_id);

        // Trello image URLs in the description and comments now point at the app's own media route.
        $this->assertSame('Medidas: ![plano](/media/'.$image->id.'/file)', $order->description);

        $comments = $order->comments()->get();
        $this->assertCount(3, $comments);

        $this->assertSame($this->adrian->id, $comments[0]->user_id);
        $this->assertSame('Adrián Salvatori', $comments[0]->author_name);
        $this->assertSame('Listo el render ![render](/media/'.$image->id.'/file)', $comments[0]->body);
        $this->assertSame('2025-05-02 09:00:00', $comments[0]->created_at->utc()->format('Y-m-d H:i:s'));

        $this->assertSame($this->euraliz->id, $comments[1]->user_id);
        $this->assertSame('Cliente aprobó la propuesta', $comments[1]->body);
        $this->assertSame('2025-05-03 10:30:00', $comments[1]->edited_at->utc()->format('Y-m-d H:i:s'));

        $this->assertNull($comments[2]->user_id);
        $this->assertSame('Andrea Antigua', $comments[2]->author_name);

        // Nothing was ever written back to Trello.
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'trello.com') && $request->method() !== 'GET');
    }

    public function test_re_running_is_idempotent_and_applies_changes_made_on_trello(): void
    {
        $order = Order::factory()->create(['trello_card_id' => self::CARD_ID]);
        $migrator = $this->migrator();
        $migrator->migrateOrder($order);

        // Second run with no changes: nothing new uploaded, no duplicates.
        $second = $this->migrator()->migrateOrder($order->fresh());
        $this->assertSame(0, $second['files']);
        $this->assertSame(0, $second['links']);
        $this->assertSame(3, OrderComment::count());
        $this->assertSame(2, Media::count());
        $this->assertCount(1, $this->drive['files']);

        // On Trello: a comment is edited, one deleted, one added; the image is removed and a new file added.
        $this->actions[0]['data']['text'] = "**Adrián Salvatori (Kudos DOES):**\nListo, versión 2";
        unset($this->actions[2]);
        $this->actions = array_values($this->actions);
        $this->actions[] = $this->commentAction('ac0000000000000000000004', self::CARD_ID, 'Nuevo comentario', '2025-06-01T09:00:00.000Z', 'member-euraliz', 'Euraliz Trello');
        $this->cards[0]['attachments'] = [
            $this->cards[0]['attachments'][1],
            $this->uploadAttachment('aa0000000000000000000003', self::CARD_ID, 'arte-final.pdf', 'application/pdf'),
        ];

        $third = $this->migrator()->migrateOrder($order->fresh());

        $this->assertSame(1, $third['files']);
        $this->assertSame(1, $third['media_removed']);
        $this->assertSame(1, $third['comments_removed']);

        $this->assertSame(['Listo, versión 2', 'Cliente aprobó la propuesta', 'Nuevo comentario'], $order->comments()->pluck('body')->all());

        $removed = Media::withTrashed()->where('trello_attachment_id', self::IMAGE_ATTACHMENT_ID)->first();
        $this->assertTrue($removed->trashed());
        $this->assertTrue($this->drive['files'][$removed->drive_file_id]['trashed']);
        $this->assertEqualsCanonicalizing(['arte-final.pdf', 'Brief en Docs'], $order->media()->pluck('name')->all());
    }

    public function test_a_card_shared_by_several_orders_is_imported_only_into_the_oldest_one(): void
    {
        $original = Order::factory()->create(['trello_card_id' => self::CARD_ID]);
        $duplicate = Order::factory()->create(['trello_card_id' => 'AbCd0001']); // same card, by shortLink

        $migrator = $this->migrator();
        $this->assertSame(TrelloNativeMigrator::STATUS_DUPLICATE_CARD, $migrator->migrateOrder($duplicate)['status']);
        $this->assertSame(TrelloNativeMigrator::STATUS_MIGRATED, $migrator->migrateOrder($original)['status']);

        $this->assertSame(0, $duplicate->comments()->count());
        $this->assertSame(3, $original->comments()->count());
    }

    public function test_scope_covers_overview_orders_with_a_card_and_skips_the_trash(): void
    {
        $workspace = Order::factory()->create(['trello_card_id' => self::CARD_ID, 'in_workspace' => true]);
        $backlog = Order::factory()->create(['trello_card_id' => 'card-backlog', 'in_workspace' => false]);
        Order::factory()->create(['trello_card_id' => null]);
        Order::factory()->create(['trello_card_id' => '']);
        Order::factory()->create(['trello_card_id' => 'card-trashed'])->delete();

        $this->assertSame([$workspace->id, $backlog->id], $this->migrator()->scopedOrders()->pluck('id')->all());
    }

    public function test_an_order_whose_card_is_gone_is_reported_and_left_untouched(): void
    {
        $order = Order::factory()->create(['trello_card_id' => '5f00000000000000000000ff']);

        $result = $this->migrator()->migrateOrder($order);

        $this->assertSame(TrelloNativeMigrator::STATUS_CARD_NOT_FOUND, $result['status']);
        $this->assertNull($order->fresh()->trello_migrated_at);
    }

    public function test_a_failed_download_marks_the_order_partial_and_a_later_run_finishes_it(): void
    {
        $order = Order::factory()->create(['trello_card_id' => self::CARD_ID]);
        $url = $this->downloadUrl(self::CARD_ID, self::IMAGE_ATTACHMENT_ID, 'plano.png');
        $this->failingDownloads[$url] = 404;

        $first = $this->migrator()->migrateOrder($order);

        $this->assertSame(TrelloNativeMigrator::STATUS_PARTIAL, $first['status']);
        $this->assertStringContainsString('plano.png', $first['errors'][0]);
        $this->assertSame(3, $order->comments()->count());
        $this->assertStringContainsString('trello.com', $order->fresh()->description);

        unset($this->failingDownloads[$url]);
        $second = $this->migrator()->migrateOrder($order->fresh());

        $this->assertSame(TrelloNativeMigrator::STATUS_MIGRATED, $second['status']);
        $this->assertSame(1, $second['files']);
        $this->assertStringNotContainsString('trello.com', $order->fresh()->description);
    }

    public function test_without_files_imports_text_and_counts_pending_files(): void
    {
        $order = Order::factory()->create(['trello_card_id' => self::CARD_ID]);

        $result = $this->migrator()->migrateOrder($order, withFiles: false);

        $this->assertSame(1, $result['files_pending']);
        $this->assertSame(0, $result['files']);
        $this->assertSame(3, $order->comments()->count());
        $this->assertSame(1, Media::count()); // only the link
        $this->assertSame([], $this->drive['files']);
    }

    public function test_board_comments_are_paged_past_trellos_thousand_action_limit(): void
    {
        $order = Order::factory()->create(['trello_card_id' => self::CARD_ID]);
        $firstPage = [];
        for ($i = 1; $i <= 1000; $i++) {
            $firstPage[] = $this->commentAction(sprintf('bb%022d', $i), 'otra-tarjeta', "c{$i}", '2025-01-01T00:00:00.000Z', 'member-old', 'Alguien');
        }

        $this->actionPages = [$firstPage, $this->actions];

        $this->migrator()->migrateOrder($order);

        $this->assertSame(3, $order->comments()->count());
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/actions') && str_contains($request->url(), 'before='.sprintf('bb%022d', 1000)));
    }

    public function test_command_migrates_orders_and_reports_totals(): void
    {
        $order = Order::factory()->create(['trello_card_id' => self::CARD_ID]);
        Order::factory()->create(['trello_card_id' => '5f00000000000000000000ff']);

        $this->artisan('trello:migrate-to-native')
            ->expectsOutputToContain('Leyendo el tablero de Trello')
            ->expectsTable(['Estado', 'Órdenes'], [['migrated', 1], ['card_not_found', 1]])
            ->assertSuccessful();

        $this->assertSame(3, $order->comments()->count());
        $this->assertSame(2, $order->media()->count());
    }

    public function test_command_only_pending_skips_orders_already_migrated(): void
    {
        $done = Order::factory()->create(['trello_card_id' => self::CARD_ID, 'trello_migrated_at' => now()]);

        $this->artisan('trello:migrate-to-native', ['--only-pending' => true])->assertSuccessful();

        $this->assertSame(0, $done->comments()->count());
    }

    public function test_command_dry_run_writes_nothing(): void
    {
        $order = Order::factory()->create(['trello_card_id' => self::CARD_ID]);

        $this->artisan('trello:migrate-to-native', ['--dry-run' => true])
            ->expectsTable(['Simulación', 'Cantidad'], [
                ['Órdenes en alcance', 1],
                ['Con tarjeta en el tablero', 1],
                ['Tarjeta no encontrada', 0],
                ['Tarjeta compartida (se importa en la orden más antigua)', 0],
                ['Archivos', 1],
                ['Tamaño (GB)', 0.0],
                ['Enlaces', 1],
            ])
            ->assertSuccessful();

        $this->assertSame(0, $order->comments()->count());
        $this->assertNull($order->fresh()->trello_migrated_at);
    }

    public function test_command_requires_drive_unless_importing_text_only(): void
    {
        app(GoogleDriveService::class)->disconnect();
        $order = Order::factory()->create(['trello_card_id' => self::CARD_ID]);

        $this->artisan('trello:migrate-to-native')->assertFailed();
        $this->assertSame(0, $order->comments()->count());

        $this->artisan('trello:migrate-to-native', ['--without-files' => true])->assertSuccessful();
        $this->assertSame(3, $order->comments()->count());
    }

    public function test_command_refuses_to_run_after_the_cutover(): void
    {
        TrelloCutover::markComplete();
        $order = Order::factory()->create(['trello_card_id' => self::CARD_ID]);

        $this->artisan('trello:migrate-to-native')
            ->expectsOutputToContain('ya está completo')
            ->assertFailed();

        $this->assertSame(0, $order->comments()->count());
    }

    protected function migrator(): TrelloNativeMigrator
    {
        return app(TrelloNativeMigrator::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function card(string $id): array
    {
        return [
            'id' => $id,
            'shortLink' => 'AbCd0001',
            'name' => 'BRAVEPRO - SIGN DE BABY SHOWER',
            'desc' => 'Medidas: ![plano]('.$this->downloadUrl($id, self::IMAGE_ATTACHMENT_ID, 'plano.png').')',
            'closed' => false,
            'attachments' => [
                $this->uploadAttachment(self::IMAGE_ATTACHMENT_ID, $id, 'plano.png', 'image/png', 'member-euraliz', '2025-05-01T10:00:00.000Z'),
                [
                    'id' => self::LINK_ATTACHMENT_ID,
                    'name' => 'Brief en Docs',
                    'bytes' => null,
                    'mimeType' => null,
                    'isUpload' => false,
                    'url' => 'https://docs.google.com/document/d/brief',
                    'date' => '2025-05-01T11:00:00.000Z',
                    'idMember' => 'member-camila',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function uploadAttachment(string $id, string $cardId, string $name, string $mime, string $memberId = 'member-euraliz', string $date = '2025-06-01T10:00:00.000Z'): array
    {
        return [
            'id' => $id,
            'name' => $name,
            'bytes' => 1234,
            'mimeType' => $mime,
            'isUpload' => true,
            'url' => $this->downloadUrl($cardId, $id, $name),
            'date' => $date,
            'idMember' => $memberId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function commentAction(string $id, string $cardId, string $text, string $date, string $memberId, string $memberName, ?string $editedAt = null): array
    {
        return [
            'id' => $id,
            'date' => $date,
            'idMemberCreator' => $memberId,
            'memberCreator' => ['id' => $memberId, 'fullName' => $memberName, 'username' => strtolower($memberName)],
            'data' => array_filter([
                'text' => $text,
                'dateLastEdited' => $editedAt,
                'card' => ['id' => $cardId],
            ]),
        ];
    }

    protected function downloadUrl(string $cardId, string $attachmentId, string $name): string
    {
        return "https://trello.com/1/cards/{$cardId}/attachments/{$attachmentId}/download/{$name}";
    }
}
