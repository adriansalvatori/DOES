<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\AddDeliverable;
use App\Application\Delivery\Actions\CreateWorkOrder;
use App\Application\Delivery\Actions\CreateWorkOrderData;
use App\Application\Delivery\Actions\DuplicateDeliverable;
use App\Application\Delivery\Actions\UpdateDeliverableDetails;
use App\Contracts\WorkOrderNumberGenerator;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Events\DeliverableUrgent;
use App\Domain\Delivery\Events\WorkOrderCreated;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Task;
use App\Models\Client;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

class CreateWorkOrderActionsTest extends DeliveryTestCase
{
    private function create(CreateWorkOrderData $data, ?User $actor = null)
    {
        return app(CreateWorkOrder::class)($data, $actor ?? $this->manager());
    }

    public function test_it_creates_the_work_order_with_every_deliverable_in_intake(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $designer = $this->designer();

        $workOrder = $this->create(new CreateWorkOrderData(
            deliverables: [['name' => 'Front sign', 'size' => '4x8', 'material' => 'Aluminum', 'quantity' => 2], ['name' => 'Side sign']],
            number: 'WO 500',
            clientId: $client->id,
            description: 'Storefront',
            fulfillmentMethod: FulfillmentMethod::INSTALLATION,
            flags: [DeliverableFlag::TICKET],
            assigneeId: $designer->id,
            promisedDate: Carbon::parse('2026-10-20'),
            trelloLink: 'https://trello.com/c/abc',
            installationTypes: ['WALL'],
            estimateInvoiceNumber: 'E-1',
            notes: 'Careful',
        ));

        $this->assertSame('WO 500', $workOrder->number);
        $this->assertSame(FulfillmentMethod::INSTALLATION, $workOrder->fulfillment_method);
        $this->assertSame('2026-10-20', $workOrder->promised_date->toDateString());
        $this->assertSame(['WALL'], $workOrder->installation_types);
        $this->assertSame('E-1', $workOrder->estimate_invoice_number);
        $this->assertCount(2, $workOrder->deliverables);

        $front = $workOrder->deliverables->firstWhere('task_name', 'Front sign');
        $this->assertSame(DeliverableStatus::INTAKE, $front->status);
        $this->assertSame('4x8', $front->size);
        $this->assertSame(2, $front->quantity);
        $this->assertSame($designer->id, $front->assignee_id);
        $this->assertSame('https://trello.com/c/abc', $front->trello_card_url);
        $this->assertTrue($front->hasFlag(DeliverableFlag::TICKET));
        $this->assertSame('2026-10-05', $front->due_target->toDateString());
        $this->assertSame('ACME', $front->company_name);

        $row = $this->legacyRow($front);
        $this->assertSame('WO 500', $row->wo_number);
        $this->assertSame('ENTRANTE', $row->core_status);
        $this->assertSame(1, $front->events()->where('event_type', 'deliverable_created')->count());
    }

    public function test_a_phase_name_creates_one_phase_holding_every_deliverable(): void
    {
        $workOrder = $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A'], ['name' => 'B']], phaseName: 'Phase 1'));

        $this->assertCount(1, $workOrder->phases);
        $this->assertSame('Phase 1', $workOrder->phases->first()->name);
        $this->assertSame([$workOrder->phases->first()->id], $workOrder->deliverables->pluck('phase_id')->unique()->all());
    }

    public function test_without_a_phase_name_there_is_no_phase(): void
    {
        $workOrder = $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A']]));

        $this->assertCount(0, $workOrder->phases);
        $this->assertNull($workOrder->deliverables->first()->phase_id);
    }

    public function test_a_missing_number_stays_empty_unless_generation_is_requested(): void
    {
        $this->assertNull($this->create(new CreateWorkOrderData(deliverables: [['name' => 'A']]))->number);

        $generator = $this->mock(WorkOrderNumberGenerator::class);
        $generator->shouldReceive('generateNext')->once()->andReturn('WO 16368');

        $workOrder = $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A']], generateNumber: true));

        $this->assertSame('WO 16368', $workOrder->number);
    }

    public function test_a_number_already_in_use_is_refused(): void
    {
        $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A']], number: 'WO 9'));

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('already exists');

        $this->create(new CreateWorkOrderData(deliverables: [['name' => 'B']], number: 'WO 9'));
    }

    public function test_a_work_order_needs_at_least_one_deliverable(): void
    {
        $this->expectException(WorkflowException::class);

        $this->create(new CreateWorkOrderData(deliverables: []));
    }

    public function test_the_welcome_email_alert_is_due_today_when_created_before_16_30(): void
    {
        $workOrder = $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A']]));

        $alert = Alert::where('work_order_id', $workOrder->id)->sole();
        $this->assertSame(AlertKind::WELCOME_EMAIL, $alert->kind);
        $this->assertSame('2026-09-30', $alert->due_on->toDateString());
    }

    public function test_the_welcome_email_moves_to_the_next_business_day_after_16_30_and_on_weekends(): void
    {
        $this->freezeAt('2026-09-30 16:45:00');
        $late = $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A']]));
        $this->freezeAt('2026-10-03 10:00:00');
        $weekend = $this->create(new CreateWorkOrderData(deliverables: [['name' => 'B']]));

        $this->assertSame('2026-10-01', Alert::where('work_order_id', $late->id)->sole()->due_on->toDateString());
        $this->assertSame('2026-10-05', Alert::where('work_order_id', $weekend->id)->sole()->due_on->toDateString());
    }

    public function test_a_card_older_than_a_week_gets_no_welcome_email(): void
    {
        $workOrder = $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A']], createdAt: Carbon::parse('2026-09-20 10:00:00')));

        $this->assertSame(0, Alert::where('work_order_id', $workOrder->id)->count());
    }

    public function test_a_card_from_five_days_ago_still_gets_the_welcome_email(): void
    {
        $workOrder = $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A']], createdAt: Carbon::parse('2026-09-25 10:00:00')));

        $this->assertSame(1, Alert::where('work_order_id', $workOrder->id)->count());
    }

    public function test_it_dispatches_the_creation_events_after_commit(): void
    {
        Event::fake([WorkOrderCreated::class, DeliverableUrgent::class]);

        $workOrder = $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A'], ['name' => 'B']], flags: [DeliverableFlag::URGENT]));

        Event::assertDispatched(WorkOrderCreated::class, fn (WorkOrderCreated $event): bool => $event->workOrder->is($workOrder) && count($event->deliverableIds) === 2);
        Event::assertDispatchedTimes(DeliverableUrgent::class, 2);
    }

    public function test_an_inactive_user_cannot_create_a_work_order(): void
    {
        $this->expectException(AuthorizationException::class);

        $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A']]), User::factory()->create(['active' => false]));
    }

    public function test_add_deliverable_puts_a_new_piece_into_an_existing_work_order(): void
    {
        $workOrder = $this->create(new CreateWorkOrderData(deliverables: [['name' => 'A']], phaseName: 'P'));

        $added = app(AddDeliverable::class)($workOrder, 'Extra panel', quantity: 3, phase: $workOrder->phases->first(), actor: $this->manager());

        $this->assertSame($workOrder->id, $added->work_order_id);
        $this->assertSame(DeliverableStatus::INTAKE, $added->status);
        $this->assertSame($workOrder->phases->first()->id, $added->phase_id);
        $this->assertSame(3, $added->quantity);
        $this->assertCount(2, $workOrder->deliverables()->get());
    }

    public function test_update_details_returns_the_old_and_new_value_of_each_changed_field(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['size' => '2x2', 'material' => 'Vinyl', 'quantity' => 1]);

        $diff = app(UpdateDeliverableDetails::class)($deliverable, ['size' => '3x3', 'material' => 'Vinyl', 'quantity' => 5, 'name' => 'new name'], $this->manager());

        $this->assertSame(['size', 'quantity', 'name'], array_keys($diff));
        $this->assertSame(['old' => '2x2', 'new' => '3x3'], $diff['size']);
        $this->assertSame(['old' => 1, 'new' => 5], $diff['quantity']);
        $deliverable->refresh();
        $this->assertSame('3x3', $deliverable->size);
        $this->assertSame('new name', $deliverable->task_name);
        $this->assertSame($diff, $deliverable->events()->where('event_type', 'details_updated')->sole()->metadata['changes']);
    }

    public function test_update_details_with_no_change_writes_nothing(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['size' => '2x2']);

        $diff = app(UpdateDeliverableDetails::class)($deliverable, ['size' => '2x2'], $this->manager());

        $this->assertSame([], $diff);
        $this->assertSame(0, $deliverable->events()->count());
    }

    public function test_update_details_changes_flags_and_announces_new_urgency_once(): void
    {
        Event::fake([DeliverableUrgent::class]);
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['flag_codes' => ['ticket']]);
        $action = app(UpdateDeliverableDetails::class);

        $diff = $action($deliverable, ['flags' => [DeliverableFlag::TICKET, DeliverableFlag::URGENT]], $this->manager());
        $action($deliverable, ['flags' => [DeliverableFlag::TICKET, DeliverableFlag::URGENT]], $this->manager());

        $this->assertSame(['ticket', 'urgent'], $diff['flags']['new']);
        $this->assertTrue($deliverable->fresh()->isUrgent());
        $this->assertContains('URGENTE', json_decode($this->legacyRow($deliverable)->flags, true));
        Event::assertDispatchedTimes(DeliverableUrgent::class, 1);
    }

    public function test_duplicate_runs_the_creation_rules_and_copies_only_the_record_data(): void
    {
        $this->freezeAt('2026-10-02 09:00:00');
        $source = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, [
            'task_name' => 'Sign', 'size' => '4x4', 'material' => 'PVC', 'quantity' => 2, 'flag_codes' => ['urgent'], 'due_target' => '2026-09-01',
        ]);
        Approval::factory()->fromClient()->create(['deliverable_id' => $source->id]);
        Task::factory()->create(['order_id' => $source->id]);

        $copy = app(DuplicateDeliverable::class)($source, $this->manager());

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame($source->work_order_id, $copy->work_order_id);
        $this->assertSame(DeliverableStatus::INTAKE, $copy->status);
        $this->assertSame('4x4', $copy->size);
        $this->assertSame(2, $copy->quantity);
        $this->assertTrue($copy->isUrgent());
        $this->assertSame('2026-10-07', $copy->due_target->toDateString());
        $this->assertSame(0, $copy->approvals()->count());
        $this->assertSame(0, $copy->tasks()->count());
        $this->assertSame(['deliverable_created', 'deliverable_duplicated'], $copy->events()->orderBy('id')->pluck('event_type')->all());
        $this->assertSame('ENTRANTE', $this->legacyRow($copy)->core_status);
    }
}
