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
}
