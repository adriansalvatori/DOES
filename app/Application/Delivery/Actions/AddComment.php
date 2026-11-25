<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Models\OrderComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Posts a native comment on a deliverable. After the Trello cutover this is the only place comments live.
 */
class AddComment
{
    use AuthorizesActor;

    public const MAX_LENGTH = 5000;

    public function __construct(private DeliveryLog $log) {}

    public function __invoke(Deliverable $deliverable, string $body, ?User $actor = null): OrderComment
    {
        $this->authorizeActor($actor, 'addComment', $deliverable);

        $body = trim($body);
        if ($body === '') {
            throw WorkflowException::refused('Write a comment first.');
        }
        if (mb_strlen($body) > self::MAX_LENGTH) {
            throw WorkflowException::refused('A comment can have at most :max characters.', ['max' => self::MAX_LENGTH]);
        }

        return DB::transaction(function () use ($deliverable, $body, $actor): OrderComment {
            $comment = OrderComment::query()->create([
                'order_id' => $deliverable->getKey(),
                'user_id' => $actor?->getKey(),
                'author_name' => $actor?->name,
                'body' => $body,
            ]);

            $this->log->record($deliverable, 'comment_added', $actor, null, null, ['comment_id' => $comment->getKey()]);

            return $comment;
        });
    }
}
