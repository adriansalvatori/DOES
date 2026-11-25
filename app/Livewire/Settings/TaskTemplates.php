<?php

namespace App\Livewire\Settings;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\TaskTemplate;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Task templates (replace the subtask presets): work tasks and gates, named in English and Spanish.
 */
#[Layout('components.layouts.app')]
class TaskTemplates extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name_en = '';

    public string $name_es = '';

    public string $kind = 'work';

    public ?string $advances_to = null;

    public bool $required = false;

    public bool $active = true;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || (! $user->isAdmin() && ! $user->isCoordinator())) {
            abort(403, __('You don\'t have permission to access this section.'));
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $template = TaskTemplate::findOrFail($id);

        $this->resetValidation();
        $this->editingId = $template->id;
        $this->name_en = $template->name_en;
        $this->name_es = $template->name_es;
        $this->kind = $template->kind;
        $this->advances_to = $template->advances_to?->value;
        $this->required = $template->required;
        $this->active = $template->active;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function updatedKind(): void
    {
        if ($this->kind === 'work') {
            $this->advances_to = null;
            $this->required = false;
        }
    }

    public function save(): void
    {
        $this->validate([
            'name_en' => ['required', 'string', 'max:120'],
            'name_es' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::in(['work', 'gate'])],
            'advances_to' => [
                Rule::requiredIf($this->kind === 'gate'),
                'nullable',
                Rule::in(array_map(fn (DeliverableStatus $status): string => $status->value, DeliverableStatus::cases())),
            ],
            'required' => ['boolean'],
            'active' => ['boolean'],
        ], [
            'advances_to.required' => __('A gate must say which status it advances to.'),
        ], [
            'name_en' => __('English name'),
            'name_es' => __('Spanish name'),
        ]);

        $isGate = $this->kind === 'gate';
        $attributes = [
            'name_en' => trim($this->name_en),
            'name_es' => trim($this->name_es),
            'kind' => $this->kind,
            'advances_to' => $isGate ? $this->advances_to : null,
            'required' => $isGate && $this->required,
            'active' => $this->active,
        ];

        if ($this->editingId) {
            TaskTemplate::findOrFail($this->editingId)->update($attributes);
        } else {
            TaskTemplate::create($attributes + ['sort' => (int) TaskTemplate::max('sort') + 1]);
        }

        $this->closeModal();
        session()->flash('message', __('Task template saved.'));
    }

    public function toggleActive(int $id): void
    {
        $template = TaskTemplate::findOrFail($id);
        $template->update(['active' => ! $template->active]);
    }

    public function move(int $id, string $direction): void
    {
        $templates = TaskTemplate::query()->orderBy('sort')->orderBy('id')->get()->values();
        $index = $templates->search(fn (TaskTemplate $template): bool => $template->id === $id);
        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($index === false || $target < 0 || $target >= $templates->count()) {
            return;
        }

        $ordered = $templates->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach ($ordered as $position => $template) {
            if ($template->sort !== $position + 1) {
                $template->update(['sort' => $position + 1]);
            }
        }
    }

    public function delete(int $id): void
    {
        TaskTemplate::findOrFail($id)->delete();
        session()->flash('message', __('Task template deleted.'));
    }

    public function render(): View
    {
        return view('livewire.settings.task-templates', [
            'templates' => TaskTemplate::query()->orderBy('sort')->orderBy('id')->get(),
            'statuses' => DeliverableStatus::cases(),
        ])->title(__('Task templates').' - '.config('app.name'));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name_en', 'name_es', 'kind', 'advances_to', 'required']);
        $this->active = true;
        $this->resetValidation();
    }
}
