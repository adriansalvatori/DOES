<?php

namespace App\Livewire\Settings;

use App\Models\SubtaskPreset;
use App\Models\SystemTaskConfig;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Configuración de Subtareas y Tareas del Sistema')]
class SubtaskPresets extends Component
{
    public string $search = '';

    public string $activeTab = 'presets'; // 'presets' or 'system'

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $emoji = 'sparkles';

    public string $color_theme = 'sky';

    public bool $is_active = true;

    public bool $is_work_task = true;

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:100|unique:subtask_presets,title,'.$this->editingId,
            'emoji' => 'nullable|string|max:50',
            'color_theme' => 'required|string|in:sky,purple,emerald,amber,rose,violet,indigo,stone',
            'is_active' => 'boolean',
            'is_work_task' => 'boolean',
        ];
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function toggleSystemTaskActive(int $id): void
    {
        $config = SystemTaskConfig::findOrFail($id);
        $config->update(['is_active' => ! $config->is_active]);
        session()->flash('message', "Estado de '{$config->title}' actualizado correctamente.");
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingId', 'title', 'emoji', 'color_theme', 'is_active', 'is_work_task']);
        $this->emoji = 'sparkles';
        $this->color_theme = 'sky';
        $this->is_active = true;
        $this->is_work_task = true;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $preset = SubtaskPreset::findOrFail($id);
        $this->editingId = $preset->id;
        $this->title = $preset->title;
        $this->emoji = $preset->emoji ?? '';
        $this->color_theme = $preset->color_theme ?? 'sky';
        $this->is_active = (bool) $preset->is_active;
        $this->is_work_task = (bool) $preset->is_work_task;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->editingId) {
            $preset = SubtaskPreset::findOrFail($this->editingId);
            $preset->update($validated);
            session()->flash('message', 'Plantilla de subtarea actualizada correctamente.');
        } else {
            $maxSort = SubtaskPreset::max('sort_order') ?? 0;
            $validated['sort_order'] = $maxSort + 1;
            SubtaskPreset::create($validated);
            session()->flash('message', 'Nueva plantilla de subtarea creada correctamente.');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'title', 'emoji', 'color_theme', 'is_active', 'is_work_task']);
    }

    public function toggleActive(int $id): void
    {
        $preset = SubtaskPreset::findOrFail($id);
        $preset->update(['is_active' => ! $preset->is_active]);
        session()->flash('message', 'Estado de la subtarea actualizado.');
    }

    public function updateOrder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            SubtaskPreset::where('id', $id)->update(['sort_order' => $index + 1]);
        }
        session()->flash('message', 'Orden de las subtareas actualizado.');
    }

    public function moveUp(int $id): void
    {
        $preset = SubtaskPreset::findOrFail($id);
        $prev = SubtaskPreset::where('sort_order', '<', $preset->sort_order)
            ->orderBy('sort_order', 'desc')
            ->first();

        if ($prev) {
            $temp = $preset->sort_order;
            $preset->update(['sort_order' => $prev->sort_order]);
            $prev->update(['sort_order' => $temp]);
        }
    }

    public function moveDown(int $id): void
    {
        $preset = SubtaskPreset::findOrFail($id);
        $next = SubtaskPreset::where('sort_order', '>', $preset->sort_order)
            ->orderBy('sort_order', 'asc')
            ->first();

        if ($next) {
            $temp = $preset->sort_order;
            $preset->update(['sort_order' => $next->sort_order]);
            $next->update(['sort_order' => $temp]);
        }
    }

    public function delete(int $id): void
    {
        $preset = SubtaskPreset::findOrFail($id);
        $preset->delete();
        session()->flash('message', 'Plantilla de subtarea eliminada correctamente.');
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        $presets = SubtaskPreset::query()
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $systemTaskConfigs = SystemTaskConfig::query()
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%')->orWhere('description', 'like', '%'.$this->search.'%'))
            ->orderBy('category')
            ->orderBy('id')
            ->get();

        return view('livewire.settings.subtask-presets', [
            'presets' => $presets,
            'systemTaskConfigs' => $systemTaskConfigs,
        ]);
    }
}
