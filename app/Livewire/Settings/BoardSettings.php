<?php

namespace App\Livewire\Settings;

use App\Application\Delivery\Queries\BoardLimits;
use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Models\Setting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Default WIP limit, board column caps and a read-only view of each column's exit rule.
 */
#[Layout('components.layouts.app')]
class BoardSettings extends Component
{
    public int $defaultWipLimit = 4;

    public int $defaultColumnCap = 25;

    /** @var array<string, string> Optional cap per status code, empty means the default. */
    public array $columnCaps = [];

    public function mount(BoardLimits $limits): void
    {
        $user = Auth::user();
        if (! $user || (! $user->isAdmin() && ! $user->isCoordinator())) {
            abort(403, __('You don\'t have permission to access this section.'));
        }

        $this->defaultWipLimit = $limits->defaultWipLimit();
        $this->defaultColumnCap = $limits->defaultColumnCap();

        $overrides = $limits->columnCapOverrides();
        foreach (DeliverableStatus::cases() as $status) {
            $this->columnCaps[$status->value] = isset($overrides[$status->value]) ? (string) $overrides[$status->value] : '';
        }
    }

    public function save(): void
    {
        $this->validate([
            'defaultWipLimit' => ['required', 'integer', 'min:1', 'max:50'],
            'defaultColumnCap' => ['required', 'integer', 'min:5', 'max:200'],
            'columnCaps.*' => ['nullable', 'integer', 'min:5', 'max:200'],
        ], [], [
            'defaultWipLimit' => __('Default WIP limit'),
            'defaultColumnCap' => __('Cards per column'),
            'columnCaps.*' => __('Column cap'),
        ]);

        Setting::set('default_wip_limit', $this->defaultWipLimit);
        Setting::set('board_column_cap', $this->defaultColumnCap);
        Setting::set('board_column_caps', json_encode(array_map('intval', array_filter($this->columnCaps, fn ($cap): bool => $cap !== '' && $cap !== null))));

        session()->flash('message', __('Board settings saved.'));
    }

    /**
     * What a deliverable needs before it may leave each column, as enforced by the workflow.
     *
     * @return array<string, string>
     */
    public static function exitRules(): array
    {
        return [
            DeliverableStatus::INTAKE->value => __('Nothing special: a manager plans it or a designer pulls it.'),
            DeliverableStatus::READY->value => __('A designer starts it; the designer must have room under the WIP limit.'),
            DeliverableStatus::IN_DESIGN->value => __('The designer sends it to internal review.'),
            DeliverableStatus::INTERNAL_REVIEW->value => __('The reviewer approves it and it is sent to the client together with its batch.'),
            DeliverableStatus::CLIENT_REVIEW->value => __('The client approves with confirmed measurements and an approved estimate.'),
            DeliverableStatus::PRODUCTION_FILES->value => __('All required gate tasks are done.'),
            DeliverableStatus::READY_FOR_PRODUCTION->value => __('Production pulls it into the queue.'),
            DeliverableStatus::PRODUCTION_QUEUE->value => __('Production starts it.'),
            DeliverableStatus::IN_PRODUCTION->value => __('Production marks it as produced.'),
            DeliverableStatus::PRODUCED->value => __('It moves on to fulfillment.'),
            DeliverableStatus::FULFILLMENT->value => __('A receipt is logged, or it is closed with a reason.'),
            DeliverableStatus::DONE->value => __('Only a manager can reopen it.'),
        ];
    }

    public function render(): View
    {
        $columns = [];
        foreach (Area::cases() as $area) {
            $columns[$area->value] = DeliverableStatus::inArea($area);
        }

        return view('livewire.settings.board-settings', [
            'areas' => Area::cases(),
            'columns' => $columns,
            'rules' => self::exitRules(),
        ])->title(__('Board & WIP').' - '.config('app.name'));
    }
}
