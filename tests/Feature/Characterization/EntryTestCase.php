<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Shared fixtures for the entry-point characterization tests.
 *
 * Time is frozen on a Wednesday so weekday arithmetic is deterministic.
 */
abstract class EntryTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $actor;

    protected Designer $euraliz;

    protected Designer $adrian;

    protected Designer $cesar;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-03-04 10:00:00'));

        $this->euraliz = Designer::create(['name' => 'Euralíz', 'active' => true]);
        $this->adrian = Designer::create(['name' => 'Adrián', 'active' => true]);
        $this->cesar = Designer::create(['name' => 'César', 'active' => true]);

        $this->actor = User::factory()->create(['name' => 'Entry Tester']);
        $this->actingAs($this->actor);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeOrder(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge([
            'designer_id' => $this->adrian->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeTask(Order $order, array $attributes = []): RelatedTask
    {
        return RelatedTask::create(array_merge([
            'order_id' => $order->id,
            'title' => 'Tarea de prueba',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'is_work_task' => true,
        ], $attributes));
    }

    protected function eventTypes(Order $order): array
    {
        return $order->events()->pluck('event_type')->all();
    }
}
