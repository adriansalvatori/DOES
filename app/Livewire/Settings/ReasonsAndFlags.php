<?php

namespace App\Livewire\Settings;

use App\Domain\Delivery\Models\WorkflowLabel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Block, hold and close reasons and flags with English and Spanish labels.
 * The codes belong to the domain enums, so only labels and the active switch can change; codes cannot be added or deleted.
 */
#[Layout('components.layouts.app')]
class ReasonsAndFlags extends Component
{
    #[Url(as: 'tab')]
    public string $kind = 'block_reason';

    public ?int $editingId = null;

    public string $label_en = '';

    public string $label_es = '';

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || (! $user->isAdmin() && ! $user->isCoordinator())) {
            abort(403, __('You don\'t have permission to access this section.'));
        }

        $this->normalizeKind();
    }

    public function updatedKind(): void
    {
        $this->normalizeKind();
        $this->cancelEdit();
    }

    public function edit(int $id): void
    {
        $label = WorkflowLabel::query()->where('kind', $this->kind)->findOrFail($id);

        $this->editingId = $label->id;
        $this->label_en = $label->label_en;
        $this->label_es = $label->label_es;
        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'label_en', 'label_es']);
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate([
            'label_en' => ['required', 'string', 'max:100'],
            'label_es' => ['required', 'string', 'max:100'],
        ], [], [
            'label_en' => __('English label'),
            'label_es' => __('Spanish label'),
        ]);

        $label = WorkflowLabel::query()->where('kind', $this->kind)->findOrFail($this->editingId);
        $label->update([
            'label_en' => trim($this->label_en),
            'label_es' => trim($this->label_es),
        ]);

        $this->cancelEdit();
        session()->flash('message', __('Label saved.'));
    }

    public function toggleActive(int $id): void
    {
        $label = WorkflowLabel::query()->where('kind', $this->kind)->findOrFail($id);
        $label->update(['active' => ! $label->active]);
    }

    public function render(): View
    {
        return view('livewire.settings.reasons-and-flags', [
            'labels' => WorkflowLabel::query()->where('kind', $this->kind)->orderBy('sort')->orderBy('id')->get(),
            'kinds' => [
                'block_reason' => __('Block reasons'),
                'hold_reason' => __('Hold reasons'),
                'close_reason' => __('Close reasons'),
                'deliverable_flag' => __('Flags'),
            ],
        ])->title(__('Reasons & flags').' - '.config('app.name'));
    }

    private function normalizeKind(): void
    {
        if (! in_array($this->kind, WorkflowLabel::KINDS, true)) {
            $this->kind = 'block_reason';
        }
    }
}
