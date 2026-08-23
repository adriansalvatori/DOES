<?php

namespace Tests\Feature\Settings;

use App\Enums\RelatedTaskType;
use App\Livewire\Settings\SubtaskPresets;
use App\Models\SubtaskPreset;
use App\Models\SystemTaskConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubtaskPresetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_subtask_presets_settings_page(): void
    {
        SubtaskPreset::create([
            'title' => 'Test Subtask Preset',
            'emoji' => '🚀',
            'color_theme' => 'emerald',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->get('/settings/subtasks')
            ->assertStatus(200)
            ->assertSee('Plantillas de Subtareas')
            ->assertSee('Test Subtask Preset');
    }

    public function test_can_create_new_subtask_preset(): void
    {
        Livewire::test(SubtaskPresets::class)
            ->set('title', 'Modificar tipografías')
            ->set('emoji', '🎨')
            ->set('color_theme', 'purple')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('subtask_presets', [
            'title' => 'Modificar tipografías',
            'emoji' => '🎨',
            'color_theme' => 'purple',
            'is_active' => 1,
        ]);
    }

    public function test_can_toggle_subtask_preset_active_status(): void
    {
        $preset = SubtaskPreset::create([
            'title' => 'Prueba Toggle',
            'emoji' => '⚡',
            'color_theme' => 'amber',
            'is_active' => true,
        ]);

        Livewire::test(SubtaskPresets::class)
            ->call('toggleActive', $preset->id);

        $this->assertDatabaseHas('subtask_presets', [
            'id' => $preset->id,
            'is_active' => 0,
        ]);
    }

    public function test_can_delete_subtask_preset(): void
    {
        $preset = SubtaskPreset::create([
            'title' => 'Eliminar Subtarea',
            'emoji' => '🗑️',
            'color_theme' => 'rose',
        ]);

        Livewire::test(SubtaskPresets::class)
            ->call('delete', $preset->id);

        $this->assertDatabaseMissing('subtask_presets', [
            'id' => $preset->id,
        ]);
    }

    public function test_can_toggle_system_task_active_status(): void
    {
        $sysTask = SystemTaskConfig::create([
            'task_type' => RelatedTaskType::FOLLOW_UP_CLIENTE->value,
            'title' => 'Follow Up Cliente',
            'category' => 'Cliente',
            'description' => 'Descripción de prueba',
            'is_active' => true,
        ]);

        Livewire::test(SubtaskPresets::class)
            ->call('toggleSystemTaskActive', $sysTask->id);

        $this->assertDatabaseHas('system_task_configs', [
            'id' => $sysTask->id,
            'is_active' => 0,
        ]);
    }
}
