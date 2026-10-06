<?php

namespace App\Livewire\Settings;

use App\Models\InstallationType;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Configuración de Tipos de Instalación')]
class InstallationTypes extends Component
{
    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $main_color = '#0284C7';

    public string $style_type = 'light'; // 'light' or 'solid'

    public string $bg_color = 'hsl(201, 96%, 96%)';

    public string $text_color = 'hsl(201, 80%, 30%)';

    public string $border_color = 'hsl(201, 96%, 86%)';

    public bool $is_active = true;

    public function mount(): void
    {
        $user = Auth::user();
        if ($user && (! $user->isAdmin() && ! $user->isCoordinator())) {
            abort(403, __('No tiene permisos para acceder a esta sección.'));
        }
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:100|unique:installation_types,name,'.$this->editingId,
            'main_color' => 'required|string|max:20',
            'style_type' => 'required|string|in:light,solid',
            'bg_color' => 'required|string|max:100',
            'text_color' => 'required|string|max:100',
            'border_color' => 'required|string|max:100',
            'is_active' => 'boolean',
        ];
    }

    public function updatedMainColor(): void
    {
        $this->recalculatePalette();
    }

    public function updatedStyleType(): void
    {
        $this->recalculatePalette();
    }

    public function setStyleType(string $type): void
    {
        $this->style_type = $type;
        $this->recalculatePalette();
    }

    public function recalculatePalette(): void
    {
        $palette = InstallationType::derivePaletteFromColor($this->main_color, $this->style_type);
        $this->bg_color = $palette['bg_color'];
        $this->text_color = $palette['text_color'];
        $this->border_color = $palette['border_color'];
    }

    public function selectPresetColor(string $hex): void
    {
        $this->main_color = $hex;
        $this->recalculatePalette();
    }

    public function openCreateModal(): void
    {
        $this->reset(['editingId', 'name', 'main_color', 'style_type', 'bg_color', 'text_color', 'border_color', 'is_active']);
        $this->main_color = '#0284C7';
        $this->style_type = 'solid';
        $this->is_active = true;
        $this->recalculatePalette();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $item = InstallationType::findOrFail($id);
        $this->editingId = $item->id;
        $this->name = $item->name;
        $this->main_color = $item->color ?? '#0284C7';
        $this->style_type = $item->style_type ?? 'solid';
        $this->bg_color = $item->bg_color ?: $this->main_color;
        $this->text_color = $item->text_color ?: '#FFFFFF';
        $this->border_color = $item->border_color ?: $this->main_color;
        $this->is_active = (bool) $item->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['name'] = mb_strtoupper($validated['name']);
        $validated['color'] = $this->main_color;
        $validated['style_type'] = $this->style_type;
        $validated['bg_color'] = $this->bg_color;
        $validated['text_color'] = $this->text_color;
        $validated['border_color'] = $this->border_color;
        $validated['is_active'] = $this->is_active;

        if ($this->editingId) {
            $item = InstallationType::findOrFail($this->editingId);
            $oldName = $item->name;
            $item->update($validated);

            // Appwide sync: update orders if name was renamed
            if ($oldName && $oldName !== $validated['name']) {
                Order::where('installation_type', $oldName)->update(['installation_type' => $validated['name']]);
                Order::whereJsonContains('installation_types', $oldName)->chunkById(100, function ($orders) use ($oldName, $validated) {
                    foreach ($orders as $order) {
                        $types = $order->installation_types ?? [];
                        $updated = array_map(fn ($t) => $t === $oldName ? $validated['name'] : $t, $types);
                        $order->installation_types = array_values(array_unique($updated));
                        $order->save();
                    }
                });
            }

            session()->flash('message', __('Tipo de instalación actualizado correctamente.'));
        } else {
            $maxSort = InstallationType::max('sort_order') ?? 0;
            $validated['sort_order'] = $maxSort + 1;
            InstallationType::create($validated);
            session()->flash('message', __('Nuevo tipo de instalación creado correctamente.'));
        }

        InstallationType::clearCache();
        $this->dispatch('installation-types-updated');

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'main_color', 'style_type', 'bg_color', 'text_color', 'border_color', 'is_active']);
    }

    public function delete(int $id): void
    {
        $item = InstallationType::findOrFail($id);
        $name = $item->name;
        $item->delete();
        InstallationType::clearCache();
        $this->dispatch('installation-types-updated');

        session()->flash('message', __('Opción de instalación ":name" eliminada.', ['name' => $name]));
    }

    public function toggleActive(int $id): void
    {
        $item = InstallationType::findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);
        InstallationType::clearCache();
        $this->dispatch('installation-types-updated');
    }

    public function updateColor(int $id, string $hex): void
    {
        $item = InstallationType::findOrFail($id);

        if (! preg_match('/^#[a-fA-F0-9]{6}$/', $hex) && ! preg_match('/^#[a-fA-F0-9]{3}$/', $hex)) {
            return;
        }

        $palette = InstallationType::derivePaletteFromColor($hex, $item->style_type ?? 'solid');
        $item->update($palette);

        InstallationType::clearCache();
        $this->dispatch('installation-types-updated');

        session()->flash('message', __('Color de ":name" actualizado correctamente.', ['name' => $item->name]));
    }

    public function toggleStyleType(int $id): void
    {
        $item = InstallationType::findOrFail($id);
        $newStyle = ($item->style_type === 'solid') ? 'light' : 'solid';
        $palette = InstallationType::derivePaletteFromColor($item->color ?? '#0284C7', $newStyle);
        $item->update($palette);

        InstallationType::clearCache();
        $this->dispatch('installation-types-updated');

        session()->flash('message', __('Estilo de ":name" actualizado.', ['name' => $item->name]));
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        $installationTypes = InstallationType::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $ordersCountByInstallation = [];
        foreach ($installationTypes as $instType) {
            $name = $instType->name;
            $ordersCountByInstallation[$name] = Order::query()
                ->where(function ($q) use ($name) {
                    $q->where('installation_type', $name)
                        ->orWhereJsonContains('installation_types', $name);
                })
                ->count();
        }

        return view('livewire.settings.installation-types', [
            'installationTypes' => $installationTypes,
            'ordersCountByInstallation' => $ordersCountByInstallation,
        ]);
    }
}
