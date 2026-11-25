<?php

namespace Tests\Feature\Domain;

use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Enums\CoreStatus;
use App\Models\Designer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shared fixtures for the delivery domain tests. Time is frozen on Wednesday 2026-09-30 09:00 unless a test moves it.
 */
abstract class DeliveryTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeAt('2026-09-30 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function freezeAt(string $moment): void
    {
        Carbon::setTestNow(Carbon::parse($moment));
    }

    protected function manager(): User
    {
        return User::factory()->coordinator()->create();
    }

    protected function reviewer(): User
    {
        return User::factory()->designer()->reviewer()->create(['name' => 'Camila']);
    }

    protected function customerService(): User
    {
        return User::factory()->customerService()->create();
    }

    /**
     * A designer user linked to a legacy designer that owns the given queue.
     */
    protected function designer(string $name = 'Adrián', CoreStatus $queue = CoreStatus::ADRIAN_ORDERS_RECEIVED): User
    {
        $user = User::factory()->designer()->create(['name' => $name]);
        $legacy = Designer::create(['name' => $name, 'active' => true, 'user_id' => $user->id, 'queue_status_value' => $queue->value]);
        $user->update(['designer_id' => $legacy->id]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function deliverable(DeliverableStatus $status = DeliverableStatus::INTAKE, array $attributes = [], ?WorkOrder $workOrder = null): Deliverable
    {
        return Deliverable::factory()->inStatus($status)->create(array_merge(
            ['work_order_id' => ($workOrder ?? WorkOrder::factory()->create())->id],
            $attributes,
        ));
    }

    protected function reviewerApproval(Deliverable $deliverable, ApprovalSource $source = ApprovalSource::REVIEWER): Approval
    {
        return Approval::factory()->create(['deliverable_id' => $deliverable->id, 'source' => $source, 'approved_at' => now()]);
    }

    protected function legacyRow(Deliverable $deliverable): object
    {
        return DB::table('orders')->where('id', $deliverable->id)->first();
    }

    protected function eventCount(Deliverable $deliverable, string $type): int
    {
        return DB::table('order_events')->where('order_id', $deliverable->id)->where('event_type', $type)->count();
    }
}
