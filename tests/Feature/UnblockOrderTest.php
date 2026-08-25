<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Livewire\Resolver\ResolverList;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnblockOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_can_be_unblocked_and_returned_to_designer_status()
    {
        $designer = Designer::create([
            'name' => 'César',
            'email' => 'cesar@kudos.com',
            'badge_style' => 'bg-sky-100 text-sky-800 border-sky-300 font-semibold',
        ]);

        $order = Order::create([
            'company_name' => 'LA CHULA TAQUERIA',
            'task_name' => 'TRUCK VEHICLE WRAP',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'in_workspace' => true,
        ]);

        RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Bloqueado: Test Task',
            'type' => RelatedTaskType::BLOCKED,
            'status' => 'todo',
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_BLOCKED',
            'created_at' => now()->subDays(3),
        ]);

        $order->unblock('Medidas confirmadas por el cliente');

        $order->refresh();

        $this->assertEquals(CoreStatus::CESAR_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertFalse((bool) $order->customer_service_required);
        $this->assertNull($order->blocking_reason);

        // Check related task completed
        $task = RelatedTask::where('order_id', $order->id)->first();
        $this->assertEquals('done', $task->status);

        // Check OrderEvent created
        $unblockEvent = OrderEvent::where('order_id', $order->id)
            ->where('event_type', 'ORDER_UNBLOCKED')
            ->first();

        $this->assertNotNull($unblockEvent);
        $this->assertEquals('Medidas confirmadas por el cliente', $unblockEvent->metadata['reason']);
        $this->assertArrayHasKey('blocked_duration', $unblockEvent->metadata);
    }

    public function test_resolver_list_unblock_modal_flow()
    {
        $designer = Designer::create([
            'name' => 'Adrián',
            'email' => 'adrian@kudos.com',
            'badge_style' => 'bg-emerald-100 text-emerald-800 border-emerald-300 font-semibold',
        ]);

        $order = Order::create([
            'company_name' => 'TACO BELL',
            'task_name' => 'BANNER DESIGN',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'in_workspace' => true,
        ]);

        Livewire::test(ResolverList::class)
            ->call('openUnblockModal', $order->id)
            ->assertSet('showUnblockModal', true)
            ->assertSet('unblockOrderId', $order->id)
            ->call('selectPresetReason', 'Estimado aprobado')
            ->assertSet('unblockReason', 'Estimado aprobado')
            ->call('confirmUnblock')
            ->assertSet('showUnblockModal', false)
            ->assertDispatched('order-updated');

        $order->refresh();
        $this->assertEquals(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->substatus);
    }

    public function test_automatic_related_tasks_trigger_timeline_event()
    {
        $order = Order::create([
            'company_name' => 'PLAZA FIESTA',
            'task_name' => 'BUSINESS CARDS',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Enviar correo de atraso preventivo',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'todo',
            'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION',
            'priority' => 'urgent',
        ]);

        $event = OrderEvent::where('order_id', $order->id)
            ->where('event_type', 'AUTOMATIC_TASK_TRIGGERED')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('AutomationEngine', $event->actor);
        $this->assertEquals('Enviar correo de atraso preventivo', $event->new_value);
        $this->assertEquals('AUTOMATIC_OVERDUE_DETECTION', $event->metadata['trigger_type']);
        $this->assertEquals('urgent', $event->metadata['priority']);
    }
}
