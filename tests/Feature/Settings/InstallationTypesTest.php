<?php

namespace Tests\Feature\Settings;

use App\Livewire\Overview\OverviewIndex;
use App\Livewire\Settings\InstallationTypes;
use App\Models\InstallationType;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class InstallationTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_installation_types_settings_page_renders_successfully(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($user)->get('/settings/installation-types');
        $response->assertStatus(200);
        $response->assertSee('Configuración de Tipos de Instalación');
        $response->assertSee('ENVIAR CURRIER');
        $response->assertSee('INSTALACION');
        $response->assertSee('BUSCAR PICASSO');
    }

    public function test_designer_user_cannot_access_installation_types_settings(): void
    {
        $designer = User::factory()->create(['role' => 'designer']);

        $response = $this->actingAs($designer)->get('/settings/installation-types');
        $response->assertStatus(403);
    }

    public function test_can_create_new_installation_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Livewire::actingAs($admin)
            ->test(InstallationTypes::class)
            ->call('openCreateModal')
            ->set('name', 'NUEVA RUTA EXPRESS')
            ->set('main_color', '#10B981')
            ->set('style_type', 'light')
            ->call('recalculatePalette')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('installation_types', [
            'name' => 'NUEVA RUTA EXPRESS',
            'color' => '#10B981',
            'style_type' => 'light',
        ]);
    }

    public function test_can_edit_existing_installation_type_and_cascades_to_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $item = InstallationType::firstOrCreate(
            ['name' => 'ENTREGA PARCIAL'],
            [
                'color' => '#64748B',
                'style_type' => 'light',
                'bg_color' => 'hsl(215, 25%, 96%)',
                'text_color' => 'hsl(215, 25%, 30%)',
                'border_color' => 'hsl(215, 25%, 85%)',
                'sort_order' => 12,
                'is_active' => true,
            ]
        );

        $order = Order::create([
            'wo_number' => 'WO 99999',
            'task_name' => 'Installation Test Order',
            'company_name' => 'Acme Corp',
            'installation_type' => 'ENTREGA PARCIAL',
        ]);

        Livewire::actingAs($admin)
            ->test(InstallationTypes::class)
            ->call('openEditModal', $item->id)
            ->set('name', 'ENTREGA PARCIAL REVISADA')
            ->set('main_color', '#EF4444')
            ->set('style_type', 'solid')
            ->call('recalculatePalette')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('installation_types', [
            'id' => $item->id,
            'name' => 'ENTREGA PARCIAL REVISADA',
            'color' => '#EF4444',
            'style_type' => 'solid',
        ]);

        $order->refresh();
        $this->assertEquals('ENTREGA PARCIAL REVISADA', $order->installation_type);
    }

    public function test_can_toggle_active_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = InstallationType::first();

        $initialStatus = $item->is_active;

        Livewire::actingAs($admin)
            ->test(InstallationTypes::class)
            ->call('toggleActive', $item->id);

        $item->refresh();
        $this->assertNotEquals($initialStatus, $item->is_active);
    }

    public function test_can_delete_installation_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $item = InstallationType::create([
            'name' => 'TIPO PARA BORRAR',
            'color' => '#64748B',
            'style_type' => 'light',
            'bg_color' => '#f8fafc',
            'text_color' => '#334155',
            'border_color' => '#cbd5e1',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(InstallationTypes::class)
            ->call('delete', $item->id);

        $this->assertDatabaseMissing('installation_types', [
            'id' => $item->id,
        ]);
    }

    public function test_cache_is_refreshed_on_modification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Prime cache
        $cached = InstallationType::getAllCached();
        $this->assertTrue(Cache::has(InstallationType::CACHE_KEY));

        Livewire::actingAs($admin)
            ->test(InstallationTypes::class)
            ->call('openCreateModal')
            ->set('name', 'CACHE TEST TYPE')
            ->set('main_color', '#3B82F6')
            ->set('style_type', 'light')
            ->call('recalculatePalette')
            ->call('save');

        // Cache must have been forgotten
        $newCached = InstallationType::getAllCached();
        $this->assertTrue($newCached->contains('name', 'CACHE TEST TYPE'));
    }

    public function test_overview_renders_dynamic_installation_options(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = Order::create([
            'wo_number' => 'WO 77777',
            'task_name' => 'Installation Overview Test',
            'company_name' => 'Test Corp',
            'installation_type' => 'BUSCAR PICASSO',
        ]);

        Livewire::actingAs($admin)
            ->test(OverviewIndex::class)
            ->assertSee('BUSCAR PICASSO')
            ->call('updateInstallationType', $order->id, '4OVER')
            ->assertDispatched('order-updated');

        $order->refresh();
        $this->assertEquals('4OVER', $order->installation_type);
    }
}
