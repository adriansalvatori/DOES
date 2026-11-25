<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\BlockDeliverable;
use App\Application\Delivery\Actions\PutOnHold;
use App\Application\Delivery\Actions\ResolveBlock;
use App\Application\Delivery\Actions\Resume;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Events\BlockResolved;
use App\Domain\Delivery\Events\DeliverableBlocked;
use App\Domain\Delivery\Events\DeliverablePutOnHold;
use App\Domain\Delivery\Events\DeliverableResumed;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Hold;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

class BlockAndHoldActionsTest extends DeliveryTestCase
{
    public function test_a_block_defaults_to_the_first_active_customer_service_user(): void
    {
        $inactive = User::factory()->customerService()->create(['active' => false]);
        $first = $this->customerService();
        $this->customerService();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);

        $block = app(BlockDeliverable::class)($deliverable, BlockReason::MISSING_LOGO, 'No logo yet', $this->designer());

        $this->assertSame($first->id, $block->owner_id);
        $this->assertNotSame($inactive->id, $block->owner_id);
        $this->assertSame(BlockReason::MISSING_LOGO, $block->reason);
        $this->assertSame('No logo yet', $block->note);
        $this->assertNotNull($block->raised_at);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
        $this->assertSame('BLOQUEADA', $this->legacyRow($deliverable)->substatus);
    }

    public function test_without_customer_service_users_the_owner_is_the_queue(): void
    {
        $block = app(BlockDeliverable::class)($this->deliverable(DeliverableStatus::READY), BlockReason::OTHER, 'x', $this->manager());

        $this->assertNull($block->owner_id);
    }

    public function test_an_explicit_owner_wins(): void
    {
        $this->customerService();
        $owner = $this->manager();

        $block = app(BlockDeliverable::class)($this->deliverable(DeliverableStatus::READY), BlockReason::OTHER, null, $owner, $owner);

        $this->assertSame($owner->id, $block->owner_id);
    }

    public function test_the_same_reason_cannot_block_twice_but_another_reason_can(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $action = app(BlockDeliverable::class);
        $action($deliverable, BlockReason::MISSING_LOGO, null, $this->manager());

        $action($deliverable, BlockReason::MISSING_MEASUREMENTS, null, $this->manager());
        $this->assertSame(2, $deliverable->blocks()->open()->count());

        $this->expectException(WorkflowException::class);
        $action($deliverable, BlockReason::MISSING_LOGO, null, $this->manager());
    }

    public function test_a_closed_deliverable_cannot_be_blocked(): void
    {
        $this->expectException(WorkflowException::class);

        app(BlockDeliverable::class)($this->deliverable(DeliverableStatus::DONE), BlockReason::OTHER, null, $this->manager());
    }

    public function test_blocking_dispatches_the_event_and_is_logged(): void
    {
        Event::fake([DeliverableBlocked::class]);
        $deliverable = $this->deliverable(DeliverableStatus::READY);

        app(BlockDeliverable::class)($deliverable, BlockReason::MISSING_LOGO, null, $this->manager());

        Event::assertDispatched(DeliverableBlocked::class);
        $this->assertSame(1, $deliverable->events()->where('event_type', 'deliverable_blocked')->count());
    }

    public function test_resolving_a_block_frees_the_deliverable_where_it_was(): void
    {
        Event::fake([BlockResolved::class]);
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $block = Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_LOGO]);

        $resolved = app(ResolveBlock::class)($block, 'Logo received', $this->manager());

        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame('Logo received', $resolved->resolution_note);
        $this->assertFalse($deliverable->fresh()->isBlocked());
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
        $this->assertSame('TO DO TODAY', $this->legacyRow($deliverable)->core_status);
        Event::assertDispatched(BlockResolved::class);
    }

    public function test_a_block_cannot_be_resolved_twice(): void
    {
        $block = Block::factory()->create(['deliverable_id' => $this->deliverable()->id, 'resolved_at' => now()]);

        $this->expectException(WorkflowException::class);

        app(ResolveBlock::class)($block, null, $this->manager());
    }

    public function test_resolving_the_missing_measurements_block_completes_the_approval_and_moves_on(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        Approval::factory()->fromClient(false, true)->create(['deliverable_id' => $deliverable->id]);
        $block = Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_MEASUREMENTS]);

        app(ResolveBlock::class)($block, 'Measured on site', $this->customerService());

        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->fresh()->status);
        $this->assertTrue((bool) Approval::standing()->where('deliverable_id', $deliverable->id)->sole()->measurements_confirmed);
    }

    public function test_resolving_one_block_does_not_move_a_deliverable_that_is_still_blocked(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        Approval::factory()->fromClient(false, true)->create(['deliverable_id' => $deliverable->id]);
        $measurements = Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_MEASUREMENTS]);
        Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_LOGO]);

        app(ResolveBlock::class)($measurements, null, $this->manager());

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $deliverable->fresh()->status);
    }

    public function test_a_designer_who_does_not_own_the_work_cannot_resolve_its_block(): void
    {
        $owner = $this->designer('Owner');
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $owner->id]);
        $block = Block::factory()->create(['deliverable_id' => $deliverable->id]);

        $this->expectException(AuthorizationException::class);

        app(ResolveBlock::class)($block, null, $this->designer('Other'));
    }

    public function test_putting_a_deliverable_on_hold_keeps_its_status(): void
    {
        Event::fake([DeliverablePutOnHold::class]);
        $deliverable = $this->deliverable(DeliverableStatus::READY);

        $hold = app(PutOnHold::class)($deliverable, HoldReason::AWAITING_PERMIT, 'City permit', $this->manager());

        $this->assertSame(HoldReason::AWAITING_PERMIT, $hold->reason);
        $this->assertNotNull($hold->started_at);
        $this->assertTrue($deliverable->fresh()->isOnHold());
        $this->assertSame(DeliverableStatus::READY, $deliverable->fresh()->status);
        $this->assertSame('ON HOLD', $this->legacyRow($deliverable)->core_status);
        Event::assertDispatched(DeliverablePutOnHold::class);
    }

    public function test_a_deliverable_cannot_be_held_twice_or_when_closed(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $action = app(PutOnHold::class);
        $action($deliverable, HoldReason::PAUSED, null, $this->manager());

        try {
            $action($deliverable, HoldReason::PAUSED, null, $this->manager());
            $this->fail('Already on hold.');
        } catch (WorkflowException) {
        }

        $this->expectException(WorkflowException::class);
        $action($this->deliverable(DeliverableStatus::DONE), HoldReason::PAUSED, null, $this->manager());
    }

    public function test_only_managers_and_customer_service_can_hold(): void
    {
        $this->expectException(AuthorizationException::class);

        app(PutOnHold::class)($this->deliverable(), HoldReason::PAUSED, null, $this->designer());
    }

    public function test_resume_ends_the_hold_and_restarts_the_two_day_sla(): void
    {
        Event::fake([DeliverableResumed::class]);
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['due_target' => '2026-09-01']);
        $hold = Hold::factory()->create(['deliverable_id' => $deliverable->id]);

        app(Resume::class)($deliverable, $this->manager());

        $this->assertNotNull($hold->fresh()->ended_at);
        $this->assertSame('2026-10-02', $deliverable->fresh()->due_target->toDateString());
        $this->assertSame('TO DO TODAY', $this->legacyRow($deliverable)->core_status);
        Event::assertDispatched(DeliverableResumed::class);
    }

    public function test_resuming_a_proof_waiting_for_the_client_restarts_its_follow_ups(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-01 09:00:00']);
        Hold::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => HoldReason::NO_RESPONSE]);
        Alert::factory()->create(['deliverable_id' => $deliverable->id, 'kind' => AlertKind::CLIENT_FOLLOW_UP_1, 'due_on' => '2026-09-04']);

        app(Resume::class)($deliverable, $this->customerService());

        $deliverable->refresh();
        $this->assertSame('2026-09-30 09:00:00', $deliverable->last_sent_to_client_at->toDateTimeString());
        $this->assertSame(
            ['2026-10-05', '2026-10-08', '2026-10-13'],
            $deliverable->alerts()->open()->orderBy('due_on')->pluck('due_on')->map->toDateString()->all(),
        );
    }

    public function test_resuming_a_deliverable_that_is_not_on_hold_is_refused(): void
    {
        $this->expectException(WorkflowException::class);

        app(Resume::class)($this->deliverable(), $this->manager());
    }
}
