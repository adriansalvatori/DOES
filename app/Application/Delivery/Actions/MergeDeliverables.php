<?php

namespace App\Application\Delivery\Actions;

use App\Application\Delivery\Actions\Concerns\AuthorizesActor;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Support\DeliveryLog;
use App\Domain\Delivery\Support\PointsEstimator;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Merges deliverables (samples included) into one. The main deliverable keeps its status, work order, approvals,
 * blocks and holds, and takes the field values chosen for it. The tasks, comments, files, history, receipts and
 * samples of the others move to it; their open blocks, holds and alerts end, and they go to the trash marked as
 * merged into it. The main deliverable's points are the sum of its tasks again.
 */
class MergeDeliverables
{
    use AuthorizesActor;

    /** Fields whose value can be taken from any of the merged deliverables. */
    public const FIELDS = [
        'task_name', 'size', 'material', 'quantity', 'files_url', 'trello_card_url', 'assignee_id', 'reviewer_id',
        'priority', 'due_target', 'planned_for', 'flag_codes', 'points',
    ];

    public function __construct(private DeliveryLog $log, private LegacyProjection $projection, private PointsEstimator $points) {}

    /**
     * @param  list<Deliverable>  $others
     * @param  array<string, mixed>  $fields  Chosen values by field (see FIELDS); missing fields keep the main value.
     */
    public function __invoke(Deliverable $main, array $others, array $fields = [], ?User $actor = null): Deliverable
    {
        $this->authorizeActor($actor, 'merge', Deliverable::class);

        $others = collect($others)->reject(fn (Deliverable $deliverable): bool => $deliverable->is($main))->unique('id')->values();
        if ($others->isEmpty()) {
            throw WorkflowException::refused('Choose at least two cards to merge.');
        }

        return DB::transaction(function () use ($main, $others, $fields, $actor): Deliverable {
            $main->refresh();
            $otherIds = $others->map(fn ($other): int => (int) $other->getKey())->all();
            $now = now();

            $main->fill(array_intersect_key($fields, array_flip(self::FIELDS)));
            if (in_array($main->sample_of_id, $otherIds, true)) {
                $main->sample_of_id = null;
            }
            $main->save();

            DB::table('related_tasks')->whereIn('order_id', $otherIds)->update(['order_id' => $main->getKey()]);
            DB::table('order_comments')->whereIn('order_id', $otherIds)->update(['order_id' => $main->getKey()]);
            DB::table('media')->whereIn('order_id', $otherIds)->update(['order_id' => $main->getKey()]);
            DB::table('order_events')->whereIn('order_id', $otherIds)->update(['order_id' => $main->getKey()]);
            DB::table('due_date_histories')->whereIn('order_id', $otherIds)->update(['order_id' => $main->getKey()]);
            DB::table('receipts')->whereIn('deliverable_id', $otherIds)->update(['deliverable_id' => $main->getKey()]);
            Deliverable::withTrashed()->whereIn('sample_of_id', $otherIds)->whereKeyNot($main->getKey())->update(['sample_of_id' => $main->getKey()]);

            Block::query()->open()->whereIn('deliverable_id', $otherIds)->update(['resolved_at' => $now, 'resolution_note' => 'Merged into another card']);
            Hold::query()->open()->whereIn('deliverable_id', $otherIds)->update(['ended_at' => $now]);
            Alert::query()->open()->whereIn('deliverable_id', $otherIds)->update(['resolved_at' => $now]);

            foreach ($others as $other) {
                $other->forceFill(['merged_into_id' => $main->getKey()])->save();
                $other->delete();
            }

            if (DB::table('related_tasks')->where('order_id', $main->getKey())->whereNull('deleted_at')->exists()) {
                $this->points->syncStory($main->getKey());
            }

            $this->log->record($main, 'deliverables_merged', $actor, null, null, [
                'merged' => $others->map(fn (Deliverable $other): array => [
                    'id' => $other->getKey(),
                    'name' => $other->task_name,
                    'status' => $other->status?->value,
                    'work_order_id' => $other->work_order_id,
                ])->all(),
                'fields' => array_keys(array_intersect_key($fields, array_flip(self::FIELDS))),
            ]);
            $this->projection->project($main);

            return $main->fresh();
        });
    }
}
