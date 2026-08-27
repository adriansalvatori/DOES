<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Planner\WeeklyPlanner;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NoteSubtasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_note_type_subtask_without_order(): void
    {
        $designer = Designer::create(['name' => 'Agustín', 'active' => true]);
        $todayStr = now()->toDateString();

        Livewire::test(WeeklyPlanner::class)
            ->call('scheduleSubtask', null, 'Revisión técnica de impresiones', $todayStr, $designer->id);

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => null,
            'title' => 'Revisión técnica de impresiones',
            'assignee_id' => $designer->id,
            'status' => 'todo',
        ]);
    }

    public function test_cannot_complete_note_subtask_without_linking_order(): void
    {
        $designer = Designer::create(['name' => 'Agustín', 'active' => true]);
        $note = RelatedTask::create([
            'order_id' => null,
            'title' => 'Cotizar vinil microperforado',
            'scheduled_date' => now()->toDateString(),
            'assignee_id' => $designer->id,
            'status' => 'todo',
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('toggleSubtaskComplete', $note->id)
            ->assertDispatched('open-link-note-modal');

        $this->assertEquals('todo', $note->fresh()->status);
        $this->assertNull($note->fresh()->completed_at);
    }

    public function test_linking_note_to_order_cleans_redundant_company_and_location_from_title(): void
    {
        $designer = Designer::create(['name' => 'Agustín', 'active' => true]);
        $order = Order::create([
            'company_name' => 'Talpa',
            'location_name' => 'Talpa 16',
            'task_name' => 'Remodelación',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $note = RelatedTask::create([
            'order_id' => null,
            'title' => 'Talpa 16 diseño nuevo de la cocina',
            'scheduled_date' => now()->toDateString(),
            'assignee_id' => $designer->id,
            'status' => 'todo',
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('linkSubtaskToOrder', $note->id, $order->id);

        $this->assertDatabaseHas('related_tasks', [
            'id' => $note->id,
            'order_id' => $order->id,
            'title' => 'Diseño nuevo de la cocina',
        ]);
    }

    public function test_after_linking_note_to_order_can_be_completed(): void
    {
        $designer = Designer::create(['name' => 'Agustín', 'active' => true]);
        $order = Order::create([
            'company_name' => 'Kudos',
            'task_name' => 'Branding',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
            'designer_id' => $designer->id,
        ]);

        $note = RelatedTask::create([
            'order_id' => null,
            'title' => 'Levantamiento de requerimientos',
            'scheduled_date' => now()->toDateString(),
            'assignee_id' => $designer->id,
            'status' => 'todo',
        ]);

        Livewire::test(WeeklyPlanner::class)
            ->call('linkSubtaskToOrder', $note->id, $order->id)
            ->call('toggleSubtaskComplete', $note->id);

        $this->assertEquals('done', $note->fresh()->status);
        $this->assertNotNull($note->fresh()->completed_at);
    }

    public function test_clean_title_helper_handles_various_formats(): void
    {
        $order = new Order([
            'company_name' => 'Talpa',
            'location_name' => 'Talpa 16',
            'location_text' => 'Talpa 16',
        ]);

        $cleaned1 = RelatedTask::cleanTitleForOrder('Talpa 16 diseño nuevo de la cocina', $order);
        $this->assertEquals('Diseño nuevo de la cocina', $cleaned1);

        $cleaned2 = RelatedTask::cleanTitleForOrder('Talpa - 16 - Ajustes de color', $order);
        $this->assertEquals('Ajustes de color', $cleaned2);
    }
}
