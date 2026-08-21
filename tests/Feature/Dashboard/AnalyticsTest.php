<?php

namespace Tests\Feature\Dashboard;

use App\Enums\CoreStatus;
use App\Livewire\Dashboard\Analytics;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analytics_dashboard_renders_successfully(): void
    {
        Order::create([
            'company_name' => 'EMPRESA ANALYTICS TEST',
            'task_name' => 'DISENO LOGO',
            'in_workspace' => true,
        ]);

        $this->get('/analytics')
            ->assertStatus(200)
            ->assertSee('Analytics Dashboard')
            ->assertSee('Centro de Control');

        Livewire::test(Analytics::class)
            ->assertSee('EMPRESA ANALYTICS TEST')
            ->assertSee('Carga Operativa Activa');
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
}
