<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Substatuses;
use App\Models\Substatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubstatusManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_substatuses_settings_page_renders_successfully(): void
    {
        Substatus::create([
            'name' => 'PRUEBA SUBESTATUS',
            'bg_color' => '#FEF2F2',
            'text_color' => '#B91C1C',
            'border_color' => '#FECACA',
            'is_system' => false,
            'sort_order' => 1,
        ]);

        $response = $this->get('/settings/substatuses');
        $response->assertStatus(200);
        $response->assertSee('Configuración de Subestatus');
        $response->assertSee('PRUEBA SUBESTATUS');
    }

    public function test_can_create_new_substatus(): void
    {
        Livewire::test(Substatuses::class)
            ->call('openCreateModal')
            ->set('name', 'NUEVO ESTADO CUSTOM')
            ->set('bg_color', '#ECFDF5')
            ->set('text_color', '#047857')
            ->set('border_color', '#A7F3D0')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('substatuses', [
            'name' => 'NUEVO ESTADO CUSTOM',
            'bg_color' => '#ECFDF5',
            'text_color' => '#047857',
            'border_color' => '#A7F3D0',
        ]);
    }

    public function test_can_edit_existing_substatus_colors(): void
    {
        $sub = Substatus::create([
            'name' => 'CAMBIOS TEST',
            'bg_color' => '#FAF5FF',
            'text_color' => '#7E22CE',
            'border_color' => '#E9D5FF',
            'is_system' => false,
            'sort_order' => 2,
        ]);

        Livewire::test(Substatuses::class)
            ->call('openEditModal', $sub->id)
            ->set('bg_color', '#111827')
            ->set('text_color', '#FFFFFF')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('substatuses', [
            'id' => $sub->id,
            'bg_color' => '#111827',
            'text_color' => '#FFFFFF',
        ]);
    }

    public function test_can_delete_custom_substatus(): void
    {
        $sub = Substatus::create([
            'name' => 'SUBESTATUS ELIMINABLE',
            'bg_color' => '#F3F4F6',
            'text_color' => '#374151',
            'border_color' => '#E5E7EB',
            'is_system' => false,
            'sort_order' => 3,
        ]);

        Livewire::test(Substatuses::class)
            ->call('delete', $sub->id);

        $this->assertDatabaseMissing('substatuses', [
            'id' => $sub->id,
        ]);
    }

    public function test_cannot_delete_system_substatus(): void
    {
        $sub = Substatus::create([
            'name' => 'URGENTE SYSTEM',
            'bg_color' => '#DC2626',
            'text_color' => '#FFFFFF',
            'border_color' => '#B91C1C',
            'is_system' => true,
            'sort_order' => 1,
        ]);

        Livewire::test(Substatuses::class)
            ->call('delete', $sub->id);

        $this->assertDatabaseHas('substatuses', [
            'id' => $sub->id,
        ]);
    }
}
