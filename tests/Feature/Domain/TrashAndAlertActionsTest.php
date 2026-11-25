<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\ResolveAlert;
use App\Application\Delivery\Actions\RestoreDeliverable;
use App\Application\Delivery\Actions\TrashDeliverable;
use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\WorkOrder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class TrashAndAlertActionsTest extends DeliveryTestCase
{
    public function test_trashing_soft_deletes_the_deliverable_and_cascades_its_tasks_and_alerts(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        $task = Task::factory()->create(['order_id' => $deliverable->id]);
        Alert::factory()->create(['deliverable_id' => $deliverable->id, 'kind' => AlertKind::CLIENT_FOLLOW_UP_1]);

        app(TrashDeliverable::class)($deliverable, $this->manager());

        $this->assertSoftDeleted($deliverable);
        $this->assertSoftDeleted($task);
        $this->assertSame(0, Alert::where('deliverable_id', $deliverable->id)->count());
        $this->assertNotNull(DB::table('orders')->where('id', $deliverable->id)->value('deleted_at'));
        $this->assertSame(1, $this->eventCount($deliverable, 'deliverable_trashed'));
    }

    public function test_restoring_brings_back_the_deliverable_its_tasks_and_the_alerts_of_its_stage(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['last_sent_to_client_at' => '2026-09-30 09:00:00']);
        $task = Task::factory()->create(['order_id' => $deliverable->id]);
        $earlier = Task::factory()->create(['order_id' => $deliverable->id]);
        $earlier->delete();
        $this->freezeAt('2026-10-01 09:00:00');
        app(TrashDeliverable::class)($deliverable, $this->manager());

        $this->freezeAt('2026-10-02 09:00:00');
        app(RestoreDeliverable::class)(Deliverable::withTrashed()->find($deliverable->id), $this->manager());

        $this->assertNotSoftDeleted($deliverable);
        $this->assertNotSoftDeleted($task);
        $this->assertSoftDeleted($earlier);
        $this->assertSame(3, Alert::open()->where('deliverable_id', $deliverable->id)->count());
        $this->assertNull(DB::table('orders')->where('id', $deliverable->id)->value('deleted_at'));
        $this->assertSame(1, $this->eventCount($deliverable, 'deliverable_restored'));
    }

    public function test_only_managers_trash_and_restore(): void
    {
        $deliverable = $this->deliverable();

        try {
            app(TrashDeliverable::class)($deliverable, $this->designer());
            $this->fail('A designer cannot trash.');
        } catch (AuthorizationException) {
        }

        app(TrashDeliverable::class)($deliverable, $this->manager());
        $this->expectException(AuthorizationException::class);
        app(RestoreDeliverable::class)(Deliverable::withTrashed()->find($deliverable->id), $this->designer());
    }

    public function test_resolving_an_alert_marks_it_handled_once(): void
    {
        $deliverable = $this->deliverable();
        $alert = Alert::factory()->create(['deliverable_id' => $deliverable->id, 'kind' => AlertKind::DELAY_NOTICE]);

        app(ResolveAlert::class)($alert, $this->manager());
        $resolvedAt = $alert->fresh()->resolved_at;
        $this->freezeAt('2026-10-01 09:00:00');
        app(ResolveAlert::class)($alert->fresh(), $this->manager());

        $this->assertNotNull($resolvedAt);
        $this->assertEquals($resolvedAt, $alert->fresh()->resolved_at);
        $this->assertSame(1, $this->eventCount($deliverable, 'alert_resolved'));
    }

    public function test_resolving_a_work_order_alert_needs_no_deliverable(): void
    {
        $alert = Alert::factory()->create(['deliverable_id' => null, 'work_order_id' => WorkOrder::factory()->create()->id, 'kind' => AlertKind::WELCOME_EMAIL]);

        app(ResolveAlert::class)($alert, $this->manager());

        $this->assertNotNull($alert->fresh()->resolved_at);
    }
}
