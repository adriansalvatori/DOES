<?php

namespace Tests\Feature\Orders;

use App\Livewire\Orders\CreateOrderModal;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateOrderModalAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_displays_most_available_designer_indicator(): void
    {
        $user = User::factory()->create();

        $designerA = Designer::create([
            'name' => 'Adrián',
            'slug' => 'adrian',
            'active' => true,
            'is_external' => false,
        ]);

        $designerB = Designer::create([
            'name' => 'César',
            'slug' => 'cesar',
            'active' => true,
            'is_external' => false,
        ]);

        $thisMonday = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $order = Order::create([
            'company_name' => 'Test Company',
            'task_name' => 'Test Task',
            'wo_number' => 'WO 99999',
            'in_workspace' => true,
            'designer_id' => $designerB->id,
        ]);

        // Create 2 tasks for César this week
        RelatedTask::create([
            'order_id' => $order->id,
            'assignee_id' => $designerB->id,
            'title' => 'Cesar Task 1',
            'scheduled_date' => $thisMonday->copy()->toDateString(),
            'status' => 'pending',
        ]);
        RelatedTask::create([
            'order_id' => $order->id,
            'assignee_id' => $designerB->id,
            'title' => 'Cesar Task 2',
            'scheduled_date' => $thisMonday->copy()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        // Adrian has 0 tasks, so Adrian should be the most available designer
        $component = Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->assertSee('Más disponible esta semana:')
            ->assertSee('Adrián')
            ->assertDontSee('0 subtareas');

        // Clicking toggleDesigner selects Adrian
        $component->call('toggleDesigner', $designerA->id);
        $this->assertContains($designerA->id, $component->get('designerIds'));
    }
}
