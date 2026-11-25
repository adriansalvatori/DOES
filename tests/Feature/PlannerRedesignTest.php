<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Livewire\Planner\WeeklyPlanner;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlannerRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function seedCard(): array
    {
        $designer = Designer::create(['name' => 'Carlos', 'active' => true]);
        $order = Order::create([
            'company_name' => 'Taqueria Norte',
            'task_name' => 'Menu Board',
            'wo_number' => 'WO-4821',
            'location_name' => 'Tijuana Centro',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);
        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Ajuste de colores',
            'type' => RelatedTaskType::SUBTASK,
            'status' => 'todo',
            'scheduled_date' => now()->startOfWeek()->toDateString(),
            'assignee_id' => $designer->id,
        ]);

        return [$designer, $order, $task];
    }

    public function test_cards_render_wo_tag_location_and_move_menu_in_both_views(): void
    {
        $this->seedCard();

        foreach (['by_designer', 'by_day'] as $mode) {
            Livewire::test(WeeklyPlanner::class)
                ->call('changeViewMode', $mode)
                ->assertSeeHtml('WO-4821')
                ->assertSeeHtml('bg-wo')
                ->assertSeeHtml('text-pin')
                ->assertSee('Tijuana Centro')
                ->assertSeeHtml('data-planner-task-menu')
                ->assertSee('Move to')
                ->assertDontSeeHtml('animate-pulse');
        }
    }

    public function test_move_menu_calls_reorder_with_week_day(): void
    {
        [$designer, , $task] = $this->seedCard();
        $target = now()->startOfWeek()->addDays(2)->toDateString();

        Livewire::test(WeeklyPlanner::class)
            ->assertSeeHtml("reorderSubtasks([{$task->id}], '{$target}', {$designer->id})")
            ->call('reorderSubtasks', [$task->id], $target, $designer->id);

        $this->assertSame($target, $task->fresh()->scheduled_date->toDateString());
    }
}
