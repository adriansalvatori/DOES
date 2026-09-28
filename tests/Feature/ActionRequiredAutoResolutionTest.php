<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Services\ActionRequiredResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActionRequiredAutoResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_measures_confirmed_true_auto_unblocks_order_and_completes_resolver_task()
    {
        $designer = Designer::create([
            'name' => 'Adrián',
            'email' => 'adrian@kudos.com',
            'badge_style' => 'bg-emerald-100 text-emerald-800',
        ]);

        $order = Order::create([
            'company_name' => 'TACO CABANA',
            'task_name' => 'WINDOW GRAPHICS',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'approved' => true,
            'measures_confirmed' => false,
            'estimate_approved' => true,
            'in_workspace' => true,
        ]);

        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'RESOLVER: Medidas pendientes para orden aprobada',
            'type' => RelatedTaskType::RESOLVER,
            'status' => 'todo',
        ]);

        // Manually update measures_confirmed to true (simulating user checking the box in card detail)
        $order->update([
            'measures_confirmed' => true,
        ]);

        $order->refresh();
        $task->refresh();

        $this->assertEquals(CoreStatus::ADRIAN_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->substatus);
        $this->assertEquals('done', $task->status);
    }

    public function test_setting_estimate_approved_true_auto_updates_substatus()
    {
        $order = Order::create([
            'company_name' => 'EL POLLO LOCO',
            'task_name' => 'MENU BOARD',
            'core_status' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
            'substatus' => Substatus::FALTA_APROBACION_ESTIMADO,
            'approved' => true,
            'measures_confirmed' => true,
            'estimate_approved' => false,
            'in_workspace' => true,
        ]);

        // Manually approve estimate
        $order->update([
            'estimate_approved' => true,
        ]);

        $order->refresh();
        $this->assertEquals(Substatus::PONER_EN_ALTA, $order->substatus);
    }

    public function test_completing_resolver_task_auto_unblocks_parent_order()
    {
        $designer = Designer::create([
            'name' => 'César',
            'email' => 'cesar@kudos.com',
            'badge_style' => 'bg-sky-100 text-sky-800',
        ]);

        $order = Order::create([
            'company_name' => 'BURGER KING',
            'task_name' => 'SIGNAGE',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'in_workspace' => true,
        ]);

        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'RESOLVER: Solicitar medidas exactas al cliente',
            'type' => RelatedTaskType::RESOLVER,
            'status' => 'todo',
        ]);

        // Completing the task
        $task->update([
            'status' => 'done',
        ]);

        $order->refresh();

        $this->assertEquals(CoreStatus::CESAR_ORDERS_RECEIVED, $order->core_status);
        $this->assertNull($order->substatus);
    }

    public function test_moving_blocked_order_to_active_status_auto_unblocks()
    {
        $designer = Designer::create([
            'name' => 'Euralíz',
            'email' => 'euraliz@kudos.com',
            'badge_style' => 'bg-purple-100 text-purple-800',
        ]);

        $order = Order::create([
            'company_name' => 'SUBWAY',
            'task_name' => 'POSTERS',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'in_workspace' => true,
        ]);

        // Simulating card move to TO_DO_TODAY
        $order->update([
            'core_status' => CoreStatus::TO_DO_TODAY,
        ]);

        $order->refresh();

        $this->assertEquals(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertNull($order->substatus);
    }

    public function test_trello_data_parity_evaluation()
    {
        $service = app(ActionRequiredResolverService::class);

        $order = Order::create([
            'company_name' => 'ACME CORP',
            'task_name' => 'LOGO DESIGN',
            'wo_number' => 'WO1234',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $matchingData = [
            'company_name' => 'Acme Corp',
            'task_name' => 'Logo Design',
            'wo_number' => '1234',
        ];

        $mismatchData = [
            'company_name' => 'Different Corp',
            'task_name' => 'Logo Design',
            'wo_number' => '1234',
        ];

        $this->assertTrue($service->evaluateTrelloDataParity($order, $matchingData));
        $this->assertFalse($service->evaluateTrelloDataParity($order, $mismatchData));
    }
}
