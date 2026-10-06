<?php

namespace Tests\Feature\Settings;

use App\Enums\CoreStatus;
use App\Livewire\Settings\Substatuses;
use App\Models\Substatus;
use App\Models\User;
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

        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/settings/substatuses');
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

    public function test_can_reorder_substatuses_manually(): void
    {
        $sub1 = Substatus::create([
            'name' => 'STATUS ONE',
            'core_status' => CoreStatus::ARCHIVED->value,
            'sort_order' => 1,
        ]);
        $sub2 = Substatus::create([
            'name' => 'STATUS TWO',
            'core_status' => CoreStatus::ARCHIVED->value,
            'sort_order' => 2,
        ]);
        $sub3 = Substatus::create([
            'name' => 'STATUS THREE',
            'core_status' => CoreStatus::ARCHIVED->value,
            'sort_order' => 3,
        ]);

        // Reorder sub3 to top, then sub1, then sub2
        Livewire::test(Substatuses::class)
            ->call('reorderSubstatuses', [$sub3->id, $sub1->id, $sub2->id]);

        $this->assertEquals(1, $sub3->fresh()->sort_order);
        $this->assertEquals(2, $sub1->fresh()->sort_order);
        $this->assertEquals(3, $sub2->fresh()->sort_order);

        // Verify that getArchivedSubstatuses reflects this new manual order
        $archivedNames = Substatus::getArchivedSubstatuses()->pluck('name')->values()->toArray();
        $this->assertEquals('STATUS THREE', $archivedNames[0]);
        $this->assertEquals('STATUS ONE', $archivedNames[1]);
        $this->assertEquals('STATUS TWO', $archivedNames[2]);
    }

    public function test_can_move_substatus_up_and_down(): void
    {
        $sub1 = Substatus::create([
            'name' => 'ALPHA',
            'core_status' => CoreStatus::TO_DO_TODAY->value,
            'sort_order' => 1,
        ]);
        $sub2 = Substatus::create([
            'name' => 'BETA',
            'core_status' => CoreStatus::TO_DO_TODAY->value,
            'sort_order' => 2,
        ]);

        // Move BETA up
        Livewire::test(Substatuses::class)
            ->call('moveUp', $sub2->id);

        $this->assertEquals(1, $sub2->fresh()->sort_order);
        $this->assertEquals(2, $sub1->fresh()->sort_order);

        // Move BETA down
        Livewire::test(Substatuses::class)
            ->call('moveDown', $sub2->id);

        $this->assertEquals(2, $sub2->fresh()->sort_order);
        $this->assertEquals(1, $sub1->fresh()->sort_order);
    }
}
