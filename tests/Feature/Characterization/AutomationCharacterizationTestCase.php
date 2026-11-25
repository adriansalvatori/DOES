<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Shared fixtures for the automation / SLA characterization tests.
 *
 * Time is frozen on Wednesday 2026-09-30 09:00 unless a test calls freezeAt().
 */
abstract class AutomationCharacterizationTestCase extends TestCase
{
    use RefreshDatabase;

    protected Designer $adrian;

    protected Designer $euraliz;

    protected Designer $cesar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeAt('2026-09-30 09:00:00');
        $this->adrian = Designer::create(['name' => 'Adrián', 'active' => true]);
        $this->euraliz = Designer::create(['name' => 'Euralíz', 'active' => true]);
        $this->cesar = Designer::create(['name' => 'César', 'active' => true]);
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

    protected function engine(): AutomationEngine
    {
        return app(AutomationEngine::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeOrder(array $attributes = []): Order
    {
        return Order::create(array_merge([
            'company_name' => 'Acme Signs',
            'task_name' => 'Storefront sign',
            'designer_id' => $this->adrian->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ], $attributes));
    }

    /**
     * Create a task without firing model events (no auto-done evaluation, no AUTOMATIC_TASK_TRIGGERED event).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeTask(Order $order, array $attributes = []): RelatedTask
    {
        return RelatedTask::withoutEvents(fn () => RelatedTask::create(array_merge([
            'order_id' => $order->id,
            'title' => 'Some task',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
        ], $attributes)));
    }

    /**
     * @return Collection<int, OrderEvent>
     */
    protected function eventsOf(Order $order, string $type): Collection
    {
        return OrderEvent::where('order_id', $order->id)->where('event_type', $type)->orderBy('id')->get();
    }

    protected function openTaskTitles(Order $order): array
    {
        return RelatedTask::where('order_id', $order->id)
            ->where('status', '!=', 'done')
            ->orderBy('id')
            ->pluck('title')
            ->all();
    }
}
