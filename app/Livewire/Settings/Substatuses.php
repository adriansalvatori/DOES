<?php

namespace App\Livewire\Settings;

use App\Enums\CoreStatus;
use App\Models\Order;
use App\Models\Substatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Configuración de Subestatus')]
class Substatuses extends Component
{
    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $main_color = '#3B82F6';

    public string $style_type = 'light'; // 'light' or 'solid'

    public string $bg_color = '#EFF6FF';

    public string $text_color = '#1D4ED8';

    public string $border_color = '#BFDBFE';

    public ?string $core_status = null;

    public bool $is_default = false;

    public bool $is_global = false;

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
            'name' => 'required|string|max:100|unique:substatuses,name,'.$this->editingId,
            'core_status' => 'nullable|string',
            'is_default' => 'boolean',
            'is_global' => 'boolean',
            'main_color' => 'required|string|max:20',
            'style_type' => 'required|string|in:light,solid',
            'bg_color' => 'required|string|max:100',
            'text_color' => 'required|string|max:100',
            'border_color' => 'required|string|max:100',
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
        $palette = Substatus::derivePaletteFromColor($this->main_color, $this->style_type);
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
        $this->openCreateModalForCoreStatus(null);
    }

    public function openCreateModalForCoreStatus(?string $coreStatus = null): void
    {
        $this->reset(['editingId', 'name', 'core_status', 'is_default', 'is_global', 'main_color', 'style_type', 'bg_color', 'text_color', 'border_color']);
        $this->core_status = $coreStatus;
        $this->is_global = empty($coreStatus);
        $this->style_type = 'light';
        $this->selectPresetColor('#3B82F6');
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $sub = Substatus::findOrFail($id);
        $this->editingId = $sub->id;
        $this->name = $sub->name;
        $this->core_status = $sub->core_status?->value ?? $sub->core_status;
        $this->is_default = (bool) $sub->is_default;
        $this->is_global = (bool) $sub->is_global;
        $this->main_color = $sub->color ?? '#3B82F6';
        $this->style_type = $sub->style_type ?? 'light';
        $this->bg_color = $sub->bg_color;
        $this->text_color = $sub->text_color;
        $this->border_color = $sub->border_color;
        $this->showModal = true;
    }

    public function updatedIsGlobal($value): void
    {
        if ($value) {
            $this->core_status = null;
            $this->is_default = false;
        }
    }

    public function updatedCoreStatus($value): void
    {
        if (! empty($value)) {
            $this->is_global = false;
        }
    }

    public function save(): void
    {
        $validated = $this->validate();
        $validated['name'] = mb_strtoupper($validated['name']);
        $validated['color'] = $this->main_color;
        $validated['style_type'] = $this->style_type;

        if ($this->is_global) {
            $validated['core_status'] = null;
            $validated['is_default'] = false;
        }

        if (! empty($validated['core_status']) && $this->is_default) {
            Substatus::where('core_status', $validated['core_status'])->update(['is_default' => false]);
        }

        if ($this->editingId) {
            $sub = Substatus::findOrFail($this->editingId);
            $oldName = $sub->name;
            $sub->update($validated);

            if ($oldName && $oldName !== $validated['name']) {
                Order::where('substatus', $oldName)->update(['substatus' => $validated['name']]);
                Order::where('origin_substatus', $oldName)->update(['origin_substatus' => $validated['name']]);
            }

            session()->flash('message', 'Subestatus actualizado correctamente.');
        } else {
            $maxSort = Substatus::max('sort_order') ?? 0;
            $validated['sort_order'] = $maxSort + 1;
            Substatus::create($validated);
            session()->flash('message', 'Nuevo subestatus creado correctamente.');
        }

        $this->showModal = false;
        $this->reset(['editingId', 'name', 'core_status', 'is_default', 'is_global', 'main_color', 'style_type', 'bg_color', 'text_color', 'border_color']);
    }

    public function delete(int $id): void
    {
        $sub = Substatus::findOrFail($id);

        if ($sub->is_system) {
            session()->flash('error', 'Los subestatus del sistema no pueden eliminarse.');

            return;
        }

        $sub->delete();
        session()->flash('message', 'Subestatus eliminado correctamente.');
    }

    public function reorderSubstatuses(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            Substatus::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        session()->flash('message', __('Orden de subestatus actualizado correctamente.'));
    }

    public function moveUp(int $id): void
    {
        $sub = Substatus::findOrFail($id);
        $siblings = $this->getSiblingsFor($sub);
        $index = $siblings->search(fn ($item) => $item->id === $sub->id);

        if ($index !== false && $index > 0) {
            $ids = $siblings->pluck('id')->toArray();
            $temp = $ids[$index];
            $ids[$index] = $ids[$index - 1];
            $ids[$index - 1] = $temp;

            $this->reorderSubstatuses($ids);
        }
    }

    public function moveDown(int $id): void
    {
        $sub = Substatus::findOrFail($id);
        $siblings = $this->getSiblingsFor($sub);
        $index = $siblings->search(fn ($item) => $item->id === $sub->id);

        if ($index !== false && $index < $siblings->count() - 1) {
            $ids = $siblings->pluck('id')->toArray();
            $temp = $ids[$index];
            $ids[$index] = $ids[$index + 1];
            $ids[$index + 1] = $temp;

            $this->reorderSubstatuses($ids);
        }
    }

    protected function getSiblingsFor(Substatus $sub): Collection
    {
        return Substatus::query()
            ->when($sub->is_global, fn ($q) => $q->where(function ($subQ) {
                $subQ->where('is_global', true)->orWhereNull('core_status');
            }))
            ->when(! $sub->is_global && $sub->core_status, function ($q) use ($sub) {
                if (CoreStatus::isPendingDesign($sub->core_status)) {
                    $designerValues = array_map(fn ($s) => $s->value, CoreStatus::designerQueueStatuses());
                    $q->whereIn('core_status', $designerValues)->where('is_global', false);
                } else {
                    $q->where('core_status', $sub->core_status->value)->where('is_global', false);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        $allSubstatuses = Substatus::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $designerQueueValues = array_map(fn ($s) => $s->value, CoreStatus::designerQueueStatuses());

        $substatusesByCoreStatus = [];
        foreach (CoreStatus::cases() as $coreCase) {
            if (CoreStatus::isPendingDesign($coreCase)) {
                $substatusesByCoreStatus[$coreCase->value] = $allSubstatuses->filter(function ($sub) use ($designerQueueValues) {
                    return ! $sub->is_global && in_array($sub->core_status?->value ?? $sub->core_status, $designerQueueValues, true);
                });
            } else {
                $substatusesByCoreStatus[$coreCase->value] = $allSubstatuses->filter(function ($sub) use ($coreCase) {
                    return ! $sub->is_global && ($sub->core_status?->value ?? $sub->core_status) === $coreCase->value;
                });
            }
        }

        $globalSubstatuses = $allSubstatuses->filter(function ($sub) {
            return $sub->is_global || empty($sub->core_status);
        });

        // Display a single unified section for all designer queues instead of separate blocks
        $coreStatusesForDisplay = collect(CoreStatus::cases())->filter(function ($coreCase) {
            return $coreCase === CoreStatus::EURALIZ_ORDERS_RECEIVED || ! CoreStatus::isPendingDesign($coreCase);
        })->values();

        // Select options for dropdown (grouping designer queues)
        $dropdownStatuses = collect(CoreStatus::cases())->filter(function ($coreCase) {
            return $coreCase === CoreStatus::EURALIZ_ORDERS_RECEIVED || ! CoreStatus::isPendingDesign($coreCase);
        })->values();

        return view('livewire.settings.substatuses', [
            'substatuses' => $allSubstatuses,
            'substatusesByCoreStatus' => $substatusesByCoreStatus,
            'globalSubstatuses' => $globalSubstatuses,
            'coreStatuses' => $coreStatusesForDisplay,
            'dropdownStatuses' => $dropdownStatuses,
        ]);
    }
}
