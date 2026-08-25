<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Livewire\Planner\WeeklyPlanner;
use App\Livewire\Resolver\ResolverList;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkingTodayStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduling_order_for_today_moves_it_to_working_today(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'TAQUERIA TEST',
            'task_name' => 'Logo Design',
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $todayStr = now()->toDateString();

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleOrder', $order->id, $todayStr);

        $order->refresh();
        $this->assertEquals($todayStr, $order->scheduled_date->toDateString());
        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);
    }

    public function test_scheduling_order_for_future_date_retains_designer_status(): void
    {
        $designer = Designer::create(['name' => 'César', 'active' => true]);

        $order = Order::create([
            'company_name' => 'FUTURE BRAND',
            'task_name' => 'Flyer Print',
            'core_status' => CoreStatus::CESAR_ORDERS_RECEIVED,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $futureDateStr = now()->addDays(5)->toDateString();

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleOrder', $order->id, $futureDateStr);

        $order->refresh();
        $this->assertNull($order->scheduled_date);
        $this->assertEquals(CoreStatus::CESAR_ORDERS_RECEIVED, $order->core_status);
        $subtask = RelatedTask::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals($futureDateStr, $subtask->scheduled_date->toDateString());
    }

    public function test_unscheduling_order_in_working_today_reverts_to_designer_status(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'PIZZA PALACE',
            'task_name' => 'Box Layout',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'scheduled_date' => now()->toDateString(),
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('unscheduleOrder', $order->id);

        $order->refresh();
        $this->assertNull($order->scheduled_date);
        $this->assertEquals(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
    }

    public function test_done_today_suppresses_is_overdue_and_is_due_today(): void
    {
        $order = Order::create([
            'company_name' => 'OVERDUE SHOP',
            'task_name' => 'Signage',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'current_due_date' => now()->subDays(2)->toDateString(),
            'in_workspace' => true,
            'done_today' => true,
        ]);

        $this->assertFalse($order->isOverdue());
        $this->assertFalse($order->isDueToday());
    }

    public function test_daily_automations_promotes_today_orders_and_reverts_uncompleted_today_orders(): void
    {
        $designer = Designer::create(['name' => 'Euralíz', 'active' => true]);

        // Scheduled for today -> should be promoted
        $scheduledOrder = Order::create([
            'company_name' => 'PROMOTED BIZ',
            'task_name' => 'Card Design',
            'core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            'scheduled_date' => now()->toDateString(),
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        // In working today, not done -> should revert
        $uncompletedOrder = Order::create([
            'company_name' => 'UNCOMPLETED BIZ',
            'task_name' => 'Banner Design',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'done_today' => false,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        app(AutomationEngine::class)->runDailyAutomations();

        $scheduledOrder->refresh();
        $uncompletedOrder->refresh();

        $this->assertEquals(CoreStatus::TO_DO_TODAY, $scheduledOrder->core_status);
        $this->assertEquals(CoreStatus::EURALIZ_ORDERS_RECEIVED, $uncompletedOrder->core_status);
    }

    public function test_resolver_list_surfaces_done_today_orders_with_action_buttons(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'RESOLVER BIZ',
            'task_name' => 'Decal Print',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'done_today' => true,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(ResolverList::class)
            ->assertSee('RESOLVER BIZ')
            ->call('sendToCamila', $order->id);

        $order->refresh();
        $this->assertEquals(CoreStatus::ENVIADO_A_CAMILA, $order->core_status);
        $this->assertFalse($order->done_today);
    }

    public function test_only_working_today_orders_with_done_today_appear_in_action_required(): void
    {
        $designer = Designer::create(['name' => 'Adrián', 'active' => true]);

        // Order in Working Today marked done -> SHOULD appear in Action Required
        $todayDoneOrder = Order::create([
            'company_name' => 'ACTION REQUIRED TODAY',
            'task_name' => 'Vectorization',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'done_today' => true,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        // Past scheduled order in ENVIADO_A_CAMILA -> SHOULD NOT appear in Action Required
        $pastCamilaOrder = Order::create([
            'company_name' => 'PAST CAMILA ORDER',
            'task_name' => 'Camila Review',
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'scheduled_date' => now()->subDays(3)->toDateString(),
            'done_today' => true,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        Livewire::test(ResolverList::class)
            ->assertSee('ACTION REQUIRED TODAY')
            ->assertDontSee('PAST CAMILA ORDER');

        $this->assertEquals(1, Order::getActionRequiredCount());
    }

    public function test_resolver_list_only_shows_tasks_for_orders_in_workspace(): void
    {
        $workspaceOrder = Order::create([
            'company_name' => 'WORKSPACE ORDER',
            'task_name' => 'Signage',
            'in_workspace' => true,
            'core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED,
        ]);

        $archivedOrder = Order::create([
            'company_name' => 'ARCHIVED ORDER',
            'task_name' => 'Poster',
            'in_workspace' => false,
            'core_status' => CoreStatus::ARCHIVED,
        ]);

        RelatedTask::create([
            'order_id' => $workspaceOrder->id,
            'title' => 'Correo atraso workspace',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'todo',
        ]);

        RelatedTask::create([
            'order_id' => $archivedOrder->id,
            'title' => 'Correo atraso backlog',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'todo',
        ]);

        Livewire::test(ResolverList::class)
            ->assertSee('WORKSPACE ORDER')
            ->assertDontSee('ARCHIVED ORDER');
    }
}
