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
        $this->assertContains('EXTERNO', $names); // Global
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

    public function test_updates_core_status_when_substatus_changes(): void
    {
        $order = Order::create([
            'company_name' => 'Empresa Test 3',
            'task_name' => 'Tarea Test 3',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => null,
            'in_workspace' => true,
        ]);

        $order->update([
            'substatus' => SubstatusEnum::ENVIADO_EN_ALTA,
        ]);

        $this->assertEquals(CoreStatus::EN_PRODUCCION, $order->fresh()->core_status);
    }

    public function test_externo_is_global_yellow_substatus_and_preserved_on_core_status_change(): void
    {
        $this->assertTrue(SubstatusEnum::EXTERNO->isGlobal());
        $this->assertStringContainsString('FEFCE8', SubstatusEnum::EXTERNO->badgeStyle());

        $order = Order::create([
            'company_name' => 'Empresa Externa',
            'task_name' => 'Tarea Externa',
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => SubstatusEnum::EXTERNO,
            'in_workspace' => true,
        ]);

        $order->update([
            'core_status' => CoreStatus::EN_PRODUCCION,
        ]);

        $this->assertEquals(SubstatusEnum::EXTERNO, $order->fresh()->substatus);
    }

    public function test_archived_substatuses_handling_and_normalization(): void
    {
        $service = app(StatusTransitionService::class);
        $validArchived = $service->getValidSubstatuses(CoreStatus::ARCHIVED)->pluck('name')->toArray();

        $this->assertContains('FINALIZADA !', $validArchived);
        $this->assertContains('CANCELADA', $validArchived);
        $this->assertContains('CLIENTE NO RESPONSIVE', $validArchived);

        // Order archived from production normalizes to default FINALIZADA ! and sets archived_at
        $order = Order::create([
            'company_name' => 'Empresa Archivar',
            'task_name' => 'Tarea Archivar',
            'core_status' => CoreStatus::EN_PRODUCCION,
            'substatus' => SubstatusEnum::ENVIADO_EN_ALTA,
            'in_workspace' => true,
        ]);

        $order->update(['core_status' => CoreStatus::ARCHIVED]);

        $fresh = $order->fresh();
        $this->assertEquals(SubstatusEnum::FINALIZADA, $fresh->substatus);
        $this->assertNotNull($fresh->archived_at);

        // Setting CLIENTE_NO_RESPONSIVE substatus sets core_status to ARCHIVED
        $order2 = Order::create([
            'company_name' => 'Empresa No Responsive',
            'task_name' => 'Tarea No Responsive',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'substatus' => SubstatusEnum::WAITING_FOR_CLIENT,
            'in_workspace' => true,
        ]);

        $order2->update(['substatus' => SubstatusEnum::CLIENTE_NO_RESPONSIVE]);

        $fresh2 = $order2->fresh();
        $this->assertEquals(CoreStatus::ARCHIVED, $fresh2->core_status);
        $this->assertEquals(SubstatusEnum::CLIENTE_NO_RESPONSIVE, $fresh2->substatus);
        $this->assertNotNull($fresh2->archived_at);

        // Restoring order from ARCHIVED to EN_PRODUCCION resets substatus and clears archived_at
        $fresh2->update(['core_status' => CoreStatus::EN_PRODUCCION]);

        $freshRestored = $fresh2->fresh();
        $this->assertEquals(SubstatusEnum::ENVIADO_EN_ALTA, $freshRestored->substatus);
        $this->assertNull($freshRestored->archived_at);
    }
}
