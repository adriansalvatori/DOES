<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\MergeDeliverables;
use App\Application\Delivery\Actions\MergeTasks;
use App\Application\Delivery\Actions\MergeWorkOrders;
use App\Application\Delivery\Actions\RequestProductionSample;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\Priority;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\Deliverables\DeliverablePanel;
use App\Livewire\Merge\MergeModal;
use App\Livewire\WorkOrders\WorkOrderIndex;
use App\Models\Client;
use App\Models\OrderComment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

class MergeTest extends DeliveryTestCase
{
    public function test_merging_work_orders_moves_their_work_and_trashes_the_others(): void
    {
        $acme = Client::create(['name' => 'ACME']);
        $main = WorkOrder::factory()->create(['number' => 'WO 1', 'client_id' => $acme->id, 'notes' => 'Main notes', 'promised_date' => '2026-10-10']);
        $other = WorkOrder::factory()->create(['number' => 'WO 2', 'client_id' => null, 'notes' => 'Other notes', 'promised_date' => '2026-10-20']);
        $third = WorkOrder::factory()->create(['number' => 'WO 3']);
        $kept = $this->deliverable(DeliverableStatus::READY, [], $main);
        $moved = $this->deliverable(DeliverableStatus::IN_DESIGN, [], $other);
        $alsoMoved = $this->deliverable(DeliverableStatus::INTAKE, [], $third);
        Phase::factory()->create(['work_order_id' => $main->id, 'position' => 1]);
        $phase = Phase::factory()->create(['work_order_id' => $other->id, 'position' => 1]);
        Alert::factory()->create(['deliverable_id' => null, 'work_order_id' => $main->id, 'kind' => AlertKind::WELCOME_EMAIL]);
        $duplicateWelcome = Alert::factory()->create(['deliverable_id' => null, 'work_order_id' => $other->id, 'kind' => AlertKind::WELCOME_EMAIL]);

        $result = app(MergeWorkOrders::class)($main, [$other, $third], ['promised_date' => $other->promised_date, 'notes' => 'Other notes'], $this->manager());

        $this->assertSame([$kept->id, $moved->id, $alsoMoved->id], $result->deliverables()->orderBy('id')->pluck('id')->all());
        $this->assertSame('2026-10-20', $result->promised_date->toDateString());
        $this->assertSame('Other notes', $result->notes);
        $this->assertSame('WO 1', $result->number);
        $this->assertSame($acme->id, $result->client_id, 'Fields nobody chose keep the main value.');
        $this->assertSame([$main->id, 2], [$phase->fresh()->work_order_id, $phase->fresh()->position]);
        $this->assertNotNull($duplicateWelcome->fresh()->resolved_at, 'Only one welcome email stays open.');
        $this->assertSoftDeleted($other);
        $this->assertSame($main->id, WorkOrder::withTrashed()->find($other->id)->merged_into_id);
        $this->assertSame('WO 1', DB::table('orders')->where('id', $moved->id)->value('wo_number'));
        $this->assertSame(1, $this->eventCount($moved, 'work_order_merged'));
    }

    public function test_merging_cards_moves_tasks_comments_samples_and_keeps_the_main_status(): void
    {
        $main = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['task_name' => 'Wall wrap', 'priority' => 'medium']);
        $duplicate = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Wall wrap v2', 'priority' => 'high']);
        Task::factory()->for($main, 'deliverable')->create(['title' => 'First Proposal', 'points' => 5]);
        Task::factory()->for($duplicate, 'deliverable')->create(['title' => 'Ajustes cliente', 'points' => 2]);
        OrderComment::create(['order_id' => $duplicate->id, 'author_name' => 'Ana', 'body' => 'Use the blue']);
        Block::factory()->create(['deliverable_id' => $duplicate->id]);
        $duplicate->update(['status' => DeliverableStatus::CLIENT_REVIEW]);
        $sample = app(RequestProductionSample::class)($duplicate->fresh(), null, $this->manager());

        $result = app(MergeDeliverables::class)($main, [$duplicate], ['priority' => Priority::HIGH, 'task_name' => 'Wall wrap v2'], $this->manager());

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $result->status);
        $this->assertSame(['Wall wrap v2', Priority::HIGH], [$result->task_name, $result->priority]);
        $this->assertSame(2, $result->tasks()->count());
        $this->assertSame(7, $result->points, 'The story is the sum of all its tasks again.');
        $this->assertSame(1, DB::table('order_comments')->where('order_id', $main->id)->count());
        $this->assertSame($main->id, $sample->fresh()->sample_of_id, 'Samples follow the card they were made for.');
        $this->assertSame(0, Block::query()->open()->where('deliverable_id', $duplicate->id)->count());
        $this->assertSoftDeleted($duplicate);
        $this->assertSame($main->id, Deliverable::withTrashed()->find($duplicate->id)->merged_into_id);
        $this->assertSame(1, $this->eventCount($main, 'deliverables_merged'));
    }

    public function test_only_managers_merge_work_orders_and_cards(): void
    {
        $this->expectException(AuthorizationException::class);

        app(MergeDeliverables::class)($this->deliverable(), [$this->deliverable()], [], $this->designer());
    }

    public function test_merging_tasks_can_add_up_points_and_completes_a_gate_through_the_workflow(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        $gate = Task::factory()->for($deliverable, 'deliverable')->gate(TaskKind::GATE_PREPARE_PRODUCTION_FILES, DeliverableStatus::READY_FOR_PRODUCTION)->create(['title' => 'Prepare files', 'points' => 1]);
        $copy = Task::factory()->for($deliverable, 'deliverable')->done()->create(['title' => 'Alta', 'points' => 1]);

        $result = app(MergeTasks::class)($gate, [$copy], ['points' => 2, 'status' => 'done'], $this->manager());

        $this->assertSame(2, $result->points);
        $this->assertTrue($result->isDone());
        $this->assertSame(DeliverableStatus::READY_FOR_PRODUCTION, $deliverable->fresh()->status, 'Completing the merged gate still moves the card.');
        $this->assertSoftDeleted($copy);
        $this->assertSame(2, $deliverable->fresh()->points);

        $this->expectException(WorkflowException::class);
        app(MergeTasks::class)($result, [Task::factory()->create()], [], $this->manager());
    }

    public function test_the_modal_shows_only_conflicting_fields_and_merges_with_the_chosen_values(): void
    {
        $main = WorkOrder::factory()->create(['number' => 'WO 10', 'notes' => 'Keep', 'description' => 'Same']);
        $other = WorkOrder::factory()->create(['number' => 'WO 11', 'notes' => 'Take this', 'description' => 'Same']);
        $this->deliverable(DeliverableStatus::READY, [], $other);
        $manager = $this->manager();

        $modal = Livewire::actingAs($manager)->test(MergeModal::class)
            ->dispatch('open-merge', kind: 'work_order', ids: [$main->id, $other->id])
            ->assertSet('isOpen', true)
            ->assertSeeHtml('data-merge-field="notes"')
            ->assertSeeHtml('data-merge-field="number"')
            ->assertDontSeeHtml('data-merge-field="description"')
            ->assertSee('Take this');

        $modal->set('choices.notes', (string) $other->id)->call('merge')->assertRedirect(route('work-orders.show', $main));

        $this->assertSame('Take this', $main->fresh()->notes);
        $this->assertSame('WO 10', $main->fresh()->number);
        $this->assertSoftDeleted($other);
    }

    public function test_the_card_merge_adds_cards_by_search_and_designers_cannot_open_it(): void
    {
        $main = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Menu board']);
        $other = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Menu board copy']);

        Livewire::actingAs($this->manager())->test(MergeModal::class)
            ->dispatch('open-merge', kind: 'deliverable', ids: [$main->id])
            ->set('search', 'copy')
            ->assertSeeHtml('data-merge-results')
            ->call('addRecord', $other->id)
            ->assertSet('ids', [$main->id, $other->id])
            ->call('merge')
            ->assertDispatched('open-deliverable', deliverableId: $main->id);

        $this->assertSoftDeleted($other);

        Livewire::actingAs($this->designer('Ana'))->test(MergeModal::class)
            ->dispatch('open-merge', kind: 'deliverable', ids: [$main->id])
            ->assertSet('isOpen', false);
    }

    public function test_entry_points_appear_in_the_work_order_list_the_card_menu_and_the_task_list(): void
    {
        $first = WorkOrder::factory()->create();
        $second = WorkOrder::factory()->create();
        $this->deliverable(DeliverableStatus::READY, [], $first);
        $this->deliverable(DeliverableStatus::READY, [], $second);
        $manager = $this->manager();

        Livewire::actingAs($manager)->test(WorkOrderIndex::class)
            ->set('selected', [(string) $first->id, (string) $second->id])
            ->assertSeeHtml('data-merge-selected')
            ->call('mergeSelected')
            ->assertDispatched('open-merge', kind: 'work_order', ids: [$first->id, $second->id]);

        $card = $this->deliverable(DeliverableStatus::READY);
        Task::factory()->for($card, 'deliverable')->count(2)->create();

        Livewire::actingAs($manager)->test(DeliverablePanel::class)
            ->dispatch('open-deliverable', deliverableId: $card->id)
            ->assertSee(__('Merge with other cards'))
            ->assertSeeHtml('data-merge-tasks');
    }
}
