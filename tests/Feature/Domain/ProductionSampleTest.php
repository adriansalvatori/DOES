<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\CloseDeliverable;
use App\Application\Delivery\Actions\LogReceipt;
use App\Application\Delivery\Actions\RequestProductionSample;
use App\Application\Delivery\Actions\RunDailyCycle;
use App\Application\Delivery\Actions\TrashDeliverable;
use App\Application\Delivery\Actions\UpdateDeliverableDetails;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\PointsEstimator;
use App\Livewire\Board\BoardPage;
use App\Livewire\Deliverables\DeliverablePanel;
use Livewire\Livewire;

class ProductionSampleTest extends DeliveryTestCase
{
    public function test_a_sample_is_a_child_card_in_production_files_and_the_main_one_stays_in_client_review(): void
    {
        $designer = $this->designer();
        $main = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, [
            'task_name' => 'Channel letters', 'assignee_id' => $designer->id, 'priority' => 'high', 'size' => '4x8', 'last_sent_to_client_at' => '2026-09-29 10:00:00',
        ]);
        Alert::factory()->create(['deliverable_id' => $main->id, 'kind' => AlertKind::CLIENT_FOLLOW_UP_1, 'due_on' => '2026-10-02']);

        $sample = app(RequestProductionSample::class)($main, 'Print on vinyl', $this->manager());

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $main->fresh()->status);
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $sample->status);
        $this->assertSame($main->id, $sample->sample_of_id);
        $this->assertSame($main->work_order_id, $sample->work_order_id);
        $this->assertSame(['Channel letters', '4x8', $designer->id, 'high'], [$sample->task_name, $sample->size, $sample->assignee_id, $sample->priority->value]);
        $gate = $sample->tasks()->sole();
        $this->assertSame(TaskKind::GATE_PREPARE_PRODUCTION_FILES, $gate->kind);
        $this->assertSame(1, $sample->fresh()->points);
        $this->assertSame(0, Alert::query()->open()->where('deliverable_id', $main->id)->count(), 'Client follow-ups pause while the sample is on its way.');
        $this->assertSame(1, $this->eventCount($main, 'production_sample_requested'));
    }

    public function test_several_samples_can_be_out_but_only_work_in_client_review_gets_one(): void
    {
        $main = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        app(RequestProductionSample::class)($main, 'Matte', $this->manager());
        app(RequestProductionSample::class)($main, 'Glossy', $this->manager());

        $this->assertSame(2, $main->samples()->active()->count());
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $main->fresh()->status);

        $this->expectException(WorkflowException::class);
        app(RequestProductionSample::class)($this->deliverable(DeliverableStatus::IN_DESIGN), null, $this->manager());
    }

    public function test_follow_ups_wait_for_the_sample_and_restart_when_it_is_delivered(): void
    {
        $this->freezeAt('2026-10-14 07:00:00');
        $main = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-30 10:00:00']);
        $sample = app(RequestProductionSample::class)($main, null, $this->manager());
        $second = app(RequestProductionSample::class)($main, null, $this->manager());

        $summary = app(RunDailyCycle::class)(false);

        $this->assertSame(0, $summary['put_on_hold'], 'A client waiting for a sample is not chased or put on hold.');
        $this->assertFalse($main->fresh()->isOnHold());

        $sample->forceFill(['status' => DeliverableStatus::FULFILLMENT])->save();
        app(LogReceipt::class)($sample->fresh(), FulfillmentMethod::PICKUP, $this->manager());

        $this->assertSame(DeliverableStatus::DONE, $sample->fresh()->status);
        $this->assertSame(0, Alert::query()->open()->where('deliverable_id', $main->id)->count(), 'Follow-ups wait for the last sample.');

        $this->freezeAt('2026-10-16 09:00:00');
        $second->forceFill(['status' => DeliverableStatus::FULFILLMENT])->save();
        app(LogReceipt::class)($second->fresh(), FulfillmentMethod::PICKUP, $this->manager());

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $main->fresh()->status);
        $this->assertSame('2026-10-16', $main->fresh()->last_sent_to_client_at->toDateString());
        $this->assertSame(3, Alert::query()->open()->where('deliverable_id', $main->id)->count());
        $this->assertSame(1, $this->eventCount($main, 'client_follow_ups_resumed'));
    }

    public function test_the_panel_requests_a_sample_and_links_both_cards_and_the_board_tags_them(): void
    {
        $main = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['task_name' => 'Menu board']);
        $manager = $this->manager();

        Livewire::actingAs($manager)->test(DeliverablePanel::class)
            ->dispatch('open-deliverable', deliverableId: $main->id)
            ->assertSee(__('Request production sample'))
            ->call('openSheet', 'sample')
            ->set('sheetNote', 'Matte finish')
            ->call('submitSample')
            ->assertSeeHtml('data-banner="sample"');

        $sample = $main->samples()->sole();

        Livewire::actingAs($manager)->test(DeliverablePanel::class)
            ->dispatch('open-deliverable', deliverableId: $sample->id)
            ->assertSee(__('Production sample of :name', ['name' => 'Menu board']))
            ->assertDontSee(__('Request production sample'));

        Livewire::actingAs($manager)->test(BoardPage::class)
            ->assertSee(__('Sample in production'))
            ->assertSeeHtml('data-board-column="production_files"')
            ->assertSee(__('Production sample'));
    }

    public function test_a_card_without_tasks_takes_points_by_hand_until_it_gets_tasks(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'CHANNEL LETTERS', 'points' => null]);
        $update = app(UpdateDeliverableDetails::class);

        $update($deliverable, ['points' => 8], $this->manager());
        $this->assertSame(8, $deliverable->fresh()->points);

        app(PointsEstimator::class)->fillTasks();
        $this->assertSame(8, $deliverable->fresh()->points, 'Filling task points keeps the points of a card without tasks.');

        Livewire::actingAs($this->manager())->test(DeliverablePanel::class)
            ->dispatch('open-deliverable', deliverableId: $deliverable->id)
            ->call('changePoints', '13');
        $this->assertSame(13, $deliverable->fresh()->points);

        Task::factory()->for($deliverable, 'deliverable')->create(['title' => 'First Proposal']);
        $this->assertSame(5, $deliverable->fresh()->points, 'Once it has tasks, its points are their sum.');

        $this->expectException(WorkflowException::class);
        $update($deliverable->fresh(), ['points' => 3], $this->manager());
    }

    public function test_follow_ups_also_restart_when_the_remaining_samples_are_cancelled_or_trashed(): void
    {
        $this->freezeAt('2026-10-14 07:00:00');
        $main = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-30 10:00:00']);
        $samples = collect(range(1, 3))->map(fn () => app(RequestProductionSample::class)($main, null, $this->manager()));
        $manager = $this->manager();

        $samples[1]->forceFill(['status' => DeliverableStatus::FULFILLMENT])->save();
        app(LogReceipt::class)($samples[1]->fresh(), FulfillmentMethod::PICKUP, $manager);
        app(CloseDeliverable::class)($samples[0]->fresh(), CloseReason::CANCELLED_BY_CLIENT, 'They liked the second one', $manager);

        $this->assertSame(0, Alert::query()->open()->where('deliverable_id', $main->id)->count(), 'One sample is still open.');

        app(TrashDeliverable::class)($samples[2]->fresh(), $manager);

        $this->assertSame(3, Alert::query()->open()->where('deliverable_id', $main->id)->count());
        $this->assertSame('2026-10-14', $main->fresh()->last_sent_to_client_at->toDateString());
        $this->assertSame(1, $this->eventCount($main, 'client_follow_ups_resumed'));
    }
}
