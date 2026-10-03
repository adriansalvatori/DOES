<?php

namespace Tests\Feature\Dashboard;

use App\Enums\CoreStatus;
use App\Livewire\Dashboard\Analytics;
use App\Models\Designer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_dashboard_renders_successfully(): void
    {
        $user = User::factory()->create();

        Order::create([
            'company_name' => 'EMPRESA ANALYTICS TEST',
            'task_name' => 'DISENO LOGO',
            'in_workspace' => true,
        ]);

        $this->actingAs($user)
            ->get('/analytics')
            ->assertStatus(200)
            ->assertSee(__('Analytics Dashboard'))
            ->assertSee(__('Centro de Control'));

        Livewire::test(Analytics::class)
            ->assertSee(__('Mapa de Órdenes en Curso'))
            ->assertSee(__('Carga de Trabajo por Diseñador'))
            ->assertSee(__('Órdenes en Diseño Activas'))
            ->assertSee(__('Cumplimiento del SLA'));
    }

    public function test_analytics_dashboard_excludes_backlog_orders(): void
    {
        Order::create([
            'company_name' => 'TARJETA EN BACKLOG',
            'task_name' => 'TAREA EN BACKLOG',
            'in_workspace' => false,
        ]);

        Livewire::test(Analytics::class)
            ->assertDontSee('TARJETA EN BACKLOG')
            ->assertSet('totalOrders', 0);
    }

    public function test_analytics_dashboard_excludes_in_production_orders(): void
    {
        Order::create([
            'company_name' => 'ORDEN EN PRODUCCION',
            'task_name' => 'IMPRESION BANNER',
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
        ]);

        Livewire::test(Analytics::class)
            ->assertDontSee('ORDEN EN PRODUCCION')
            ->assertViewHas('inProductionCount', 1);
    }

    public function test_analytics_dashboard_accurately_calculates_sla_and_designer_workload(): void
    {
        $designer = Designer::create([
            'name' => 'Diseñador Prueba',
            'active' => true,
        ]);

        // On-time order
        Order::create([
            'company_name' => 'EMPRESA A',
            'task_name' => 'ORDEN A TIEMPO',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'current_due_date' => now()->addDays(2),
            'in_workspace' => true,
        ]);

        // Overdue order
        Order::create([
            'company_name' => 'EMPRESA B',
            'task_name' => 'ORDEN VENCIDA',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'current_due_date' => now()->subDays(2),
            'in_workspace' => true,
        ]);

        Livewire::test(Analytics::class)
            ->assertSet('totalOrders', 2)
            ->assertViewHas('overdueCount', 1)
            ->assertViewHas('slaComplianceRate', 50.0)
            ->assertSee('Diseñador Prueba')
            ->assertSee('50%');
    }

    public function test_analytics_dashboard_designer_availability_counts_only_active_core_statuses(): void
    {
        $designerA = Designer::create([
            'name' => 'Diseñador A',
            'active' => true,
        ]);

        $designerB = Designer::create([
            'name' => 'Diseñador B',
            'active' => true,
        ]);

        // Included statuses for Designer A (total 3)
        Order::create([
            'company_name' => 'ENTRANTE A',
            'task_name' => 'TAREA 1',
            'designer_id' => $designerA->id,
            'core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            'in_workspace' => true,
        ]);

        Order::create([
            'company_name' => 'WORKING TODAY A',
            'task_name' => 'TAREA 2',
            'designer_id' => $designerA->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Order::create([
            'company_name' => 'SENT TO CAMILA A',
            'task_name' => 'TAREA 3',
            'designer_id' => $designerA->id,
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'in_workspace' => true,
        ]);

        // Excluded statuses for Designer A (should NOT count towards availability)
        Order::create([
            'company_name' => 'BLOCKED A',
            'task_name' => 'TAREA BLOQUEADA',
            'designer_id' => $designerA->id,
            'core_status' => CoreStatus::ENTRANTE, // Blocked
            'in_workspace' => true,
        ]);

        Order::create([
            'company_name' => 'CLIENT A',
            'task_name' => 'TAREA CLIENTE',
            'designer_id' => $designerA->id,
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'in_workspace' => true,
        ]);

        Order::create([
            'company_name' => 'HOLD A',
            'task_name' => 'TAREA HOLD',
            'designer_id' => $designerA->id,
            'core_status' => CoreStatus::ON_HOLD,
            'in_workspace' => true,
        ]);

        Order::create([
            'company_name' => 'PROD A',
            'task_name' => 'TAREA PRODUCCION',
            'designer_id' => $designerA->id,
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
        ]);

        // Designer B has 1 included order
        Order::create([
            'company_name' => 'WORKING TODAY B',
            'task_name' => 'TAREA B',
            'designer_id' => $designerB->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $test = Livewire::test(Analytics::class);

        $test->assertSee(__('Disponibilidad Real por Diseñador'));
        $test->assertSee('Diseñador A');
        $test->assertSee('Diseñador B');

        /** @var array $stats */
        $stats = $test->viewData('designerAvailabilityStats');

        $statsA = collect($stats)->firstWhere(function ($s) use ($designerA) {
            $id = is_array($s['designer']) ? $s['designer']['id'] : $s['designer']->id;

            return $id === $designerA->id;
        });
        $statsB = collect($stats)->firstWhere(function ($s) use ($designerB) {
            $id = is_array($s['designer']) ? $s['designer']['id'] : $s['designer']->id;

            return $id === $designerB->id;
        });

        $this->assertNotNull($statsA);
        $this->assertEquals(3, $statsA['total_active']);
        $this->assertEquals(1, $statsA['incoming_count']);
        $this->assertEquals(1, $statsA['working_today_count']);
        $this->assertEquals(1, $statsA['sent_to_camila_count']);

        $this->assertNotNull($statsB);
        $this->assertEquals(1, $statsB['total_active']);
        $this->assertEquals(0, $statsB['incoming_count']);
        $this->assertEquals(1, $statsB['working_today_count']);
        $this->assertEquals(0, $statsB['sent_to_camila_count']);
    }
}
