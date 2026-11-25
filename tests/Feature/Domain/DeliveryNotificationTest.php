<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\ApproveByReviewer;
use App\Application\Delivery\Actions\BlockDeliverable;
use App\Application\Delivery\Actions\CreateWorkOrder;
use App\Application\Delivery\Actions\CreateWorkOrderData;
use App\Application\Delivery\Actions\MoveDeliverable;
use App\Application\Delivery\Actions\PutOnHold;
use App\Application\Delivery\Actions\RecordClientApproval;
use App\Application\Delivery\Actions\ResolveBlock;
use App\Application\Delivery\Actions\Resume;
use App\Application\Delivery\Actions\RunDailyCycle;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Events\DeliverableMoved;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Infrastructure\Notifications\NotifyOnDeliveryEvent;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class DeliveryNotificationTest extends DeliveryTestCase
{
    /**
     * @return array<int, array<string, mixed>>
     */
    private function bell(User $user, ?string $type = null): array
    {
        return $user->notifications()->get()
            ->map(fn ($notification) => $notification->data)
            ->when($type, fn ($all) => $all->where('event_type', $type))
            ->values()
            ->all();
    }

    public function test_the_listener_is_registered_for_every_mapped_event(): void
    {
        foreach (NotifyOnDeliveryEvent::events() as $event) {
            $this->assertTrue(Event::hasListeners($event), "{$event} has no listener");
        }
    }

    public function test_a_status_change_reaches_the_managers_but_not_the_actor(): void
    {
        $actor = $this->manager();
        $other = $this->manager();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);

        app(MoveDeliverable::class)($deliverable, DeliverableStatus::INTERNAL_REVIEW, $actor);

        $this->assertSame([], $this->bell($actor));
        $notification = $this->bell($other, 'status_changed')[0];
        $this->assertSame($deliverable->id, $notification['order_id']);
        $this->assertSame(['from' => ['core_status' => 'TO DO TODAY'], 'to' => ['core_status' => 'ENVIADO A CAMILA']], $notification['detail_params']);
    }

    public function test_a_move_inside_the_same_legacy_status_sends_no_status_change(): void
    {
        $other = $this->manager();
        $deliverable = $this->deliverable(DeliverableStatus::READY_FOR_PRODUCTION);

        app(MoveDeliverable::class)($deliverable, DeliverableStatus::PRODUCTION_QUEUE, $this->manager());

        $this->assertSame([], $this->bell($other, 'status_changed'));
    }

    public function test_reaching_production_files_announces_the_pending_alta(): void
    {
        $other = $this->manager();
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        app(RecordClientApproval::class)($deliverable, ApprovalSource::CLIENT, true, true, null, null, $this->manager());

        $this->assertCount(1, $this->bell($other, 'order_approved_alta'));
        $this->assertCount(1, $this->bell($other, 'status_changed'));
    }

    public function test_the_assigned_designer_is_notified_through_the_legacy_link(): void
    {
        $designer = $this->designer();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id]);

        app(MoveDeliverable::class)($deliverable, DeliverableStatus::INTERNAL_REVIEW, $this->manager());

        $this->assertCount(1, $this->bell($designer, 'status_changed'));
    }

    public function test_a_block_notifies_the_managers_urgently_and_its_customer_service_owner(): void
    {
        $manager = $this->manager();
        $service = $this->customerService();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);

        app(BlockDeliverable::class)($deliverable, BlockReason::MISSING_LOGO, 'No logo', $this->designer());

        $managerBell = $this->bell($manager, 'order_blocked');
        $this->assertCount(1, $managerBell);
        $this->assertTrue($managerBell[0]['is_urgent']);
        $this->assertSame('Missing logo', $managerBell[0]['detail_text']);
        $this->assertCount(1, $this->bell($service, 'order_blocked'));
    }

    public function test_a_manager_who_owns_the_block_is_notified_once(): void
    {
        $manager = $this->manager();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);

        app(BlockDeliverable::class)($deliverable, BlockReason::OTHER, null, $this->designer(), $manager);

        $this->assertCount(1, $this->bell($manager, 'order_blocked'));
    }

    public function test_a_block_with_no_owner_reaches_the_managers_only(): void
    {
        $manager = $this->manager();
        $sales = User::factory()->sales()->create();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);

        app(BlockDeliverable::class)($deliverable, BlockReason::OTHER, null, $this->designer());

        $this->assertCount(1, $this->bell($manager, 'order_blocked'));
        $this->assertSame([], $this->bell($sales));
    }

    public function test_resolving_a_block_sends_unblocked(): void
    {
        $manager = $this->manager();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $block = app(BlockDeliverable::class)($deliverable, BlockReason::OTHER, null, $this->designer());

        app(ResolveBlock::class)($block, null, $this->customerService());

        $this->assertCount(1, $this->bell($manager, 'order_unblocked'));
    }

    public function test_holding_and_resuming_send_status_changes_to_and_from_on_hold(): void
    {
        $manager = $this->manager();
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        app(PutOnHold::class)($deliverable, HoldReason::PAUSED, null, $this->customerService());
        app(Resume::class)($deliverable, $this->customerService());

        $details = array_column($this->bell($manager, 'status_changed'), 'detail_params');
        $this->assertSame(['from' => ['core_status' => 'ENVIADO AL CLIENTE'], 'to' => ['core_status' => 'ON HOLD']], $details[0]);
        $this->assertSame(['from' => ['core_status' => 'ON HOLD'], 'to' => ['core_status' => 'ENVIADO AL CLIENTE']], $details[1]);
    }

    public function test_creating_a_work_order_sends_new_order_and_urgent_for_urgent_pieces(): void
    {
        $manager = $this->manager();

        app(CreateWorkOrder::class)(new CreateWorkOrderData(deliverables: [['name' => 'A'], ['name' => 'B']], flags: [DeliverableFlag::URGENT]), $this->designer());

        $this->assertCount(2, $this->bell($manager, 'new_order'));
        $urgent = $this->bell($manager, 'order_urgent');
        $this->assertCount(2, $urgent);
        $this->assertTrue($urgent[0]['is_urgent']);
    }

    public function test_the_morning_run_sends_due_today_and_overdue_notifications(): void
    {
        $manager = $this->manager();
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-09-30']);
        $this->deliverable(DeliverableStatus::READY, ['due_target' => '2026-09-28']);

        app(RunDailyCycle::class)(true);

        $this->assertCount(1, $this->bell($manager, 'order_due_today'));
        $overdue = $this->bell($manager, 'order_overdue');
        $this->assertCount(1, $overdue);
        $this->assertTrue($overdue[0]['is_urgent']);
    }

    public function test_the_start_by_the_daily_cycle_notifies_without_an_actor(): void
    {
        $manager = $this->manager();
        $designer = $this->designer();
        $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);

        app(RunDailyCycle::class)(false);

        $this->assertCount(1, $this->bell($manager, 'status_changed'));
        $this->assertCount(1, $this->bell($designer, 'status_changed'));
    }

    public function test_notifications_an_admin_disabled_are_not_sent(): void
    {
        Setting::set('admin_notification_settings', json_encode(['order_blocked' => false]));
        $manager = $this->manager();
        $service = $this->customerService();

        app(BlockDeliverable::class)($this->deliverable(DeliverableStatus::IN_DESIGN), BlockReason::OTHER, null, $this->designer());

        $this->assertSame([], $this->bell($manager, 'order_blocked'));
        $this->assertSame([], $this->bell($service, 'order_blocked'));
    }

    public function test_events_without_a_notification_do_nothing(): void
    {
        $manager = $this->manager();
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);

        app(ApproveByReviewer::class)($deliverable, null, $this->reviewer());

        $this->assertSame([], $this->bell($manager));
    }

    public function test_the_listener_ignores_a_deliverable_that_no_longer_exists(): void
    {
        Notification::fake();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $event = new DeliverableMoved($deliverable, DeliverableStatus::IN_DESIGN, DeliverableStatus::INTERNAL_REVIEW, TransitionReason::MANUAL);
        $deliverable->forceDelete();

        app(NotifyOnDeliveryEvent::class)->handle($event);

        Notification::assertNothingSent();
    }
}
