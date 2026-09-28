<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\Substatus as SubstatusEnum;
use App\Models\Order;
use App\Services\StatusTransitionService;
use Database\Seeders\SubstatusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusTransitionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SubstatusSeeder::class);
    }

    public function test_get_valid_substatuses_returns_belonging_and_global_substatuses(): void
    {
        $service = app(StatusTransitionService::class);
        $substatuses = $service->getValidSubstatuses(CoreStatus::ENVIADO_AL_CLIENTE);

        $names = $substatuses->pluck('name')->toArray();

        $this->assertContains('WAITING FOR CLIENT', $names);
        $this->assertContains('CAMBIOS CLIENTE', $names);
        $this->assertContains('NO RESPUESTA', $names);
        $this->assertContains('TICKET', $names); // Global
        $this->assertContains('POTENTIAL CUSTOMER', $names); // Global
        $this->assertContains('URGENTE', $names); // Global
    }

    public function test_get_default_substatus_returns_correct_enum(): void
    {
        $service = app(StatusTransitionService::class);

        $this->assertEquals(SubstatusEnum::BLOQUEADA, $service->getDefaultSubstatus(CoreStatus::ENTRANTE));
        $this->assertEquals(SubstatusEnum::WAITING_FOR_CLIENT, $service->getDefaultSubstatus(CoreStatus::ENVIADO_AL_CLIENTE));
        $this->assertEquals(SubstatusEnum::CAMBIOS_CAMILA, $service->getDefaultSubstatus(CoreStatus::ENVIADO_A_CAMILA));
        $this->assertEquals(SubstatusEnum::ENVIADO_EN_ALTA, $service->getDefaultSubstatus(CoreStatus::EN_PRODUCCION));
        $this->assertEquals(SubstatusEnum::PAUSADO, $service->getDefaultSubstatus(CoreStatus::ON_HOLD));
    }

    public function test_normalizes_substatus_when_core_status_changes(): void
    {
        $order = Order::create([
            'company_name' => 'Empresa Test',
            'task_name' => 'Tarea Test',
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => SubstatusEnum::BLOQUEADA,
            'in_workspace' => true,
        ]);

        $order->update([
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);

        $this->assertEquals(SubstatusEnum::WAITING_FOR_CLIENT, $order->fresh()->substatus);
    }

    public function test_preserves_global_substatus_when_core_status_changes(): void
    {
        $order = Order::create([
            'company_name' => 'Empresa Test 2',
            'task_name' => 'Tarea Test 2',
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => SubstatusEnum::TICKET,
            'in_workspace' => true,
        ]);

        $order->update([
            'core_status' => CoreStatus::EN_PRODUCCION,
        ]);

        $this->assertEquals(SubstatusEnum::TICKET, $order->fresh()->substatus);
    }
}
