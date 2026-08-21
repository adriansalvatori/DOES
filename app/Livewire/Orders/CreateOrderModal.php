<?php

namespace App\Livewire\Orders;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Services\AutomationEngine;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateOrderModal extends Component
{
    public $showModal = false;

    public $isDuplicating = false;

    public $originalOrderId = null;

    public $woNumber = '';

    public $trelloCardId = '';

    public $companyName = '';

    public $taskName = '';

    public $responsiblePerson = '';

    public $designerId = null;

    public $designerIds = [];

    public $coreStatus = CoreStatus::ENTRANTE->value;

    public $substatus = '';

    public $dueDate = '';

    protected $rules = [
        'companyName' => 'required|string|max:255',
        'taskName' => 'required|string|max:255',
        'woNumber' => 'nullable|string|max:50',
        'responsiblePerson' => 'nullable|string|max:255',
        'designerIds' => 'nullable|array',
        'designerIds.*' => 'exists:designers,id',
        'coreStatus' => 'required|string',
        'substatus' => 'nullable|string',
        'dueDate' => 'nullable|date',
    ];

    #[On('open-create-order')]
    public function openModal($initialStatus = null)
    {
        $this->resetValidation();
        $this->isDuplicating = false;
        $this->originalOrderId = null;

        $this->woNumber = '';
        $this->trelloCardId = '';
        $this->companyName = '';
        $this->taskName = '';
        $this->responsiblePerson = '';
        $this->designerId = null;
        $this->designerIds = [];
        $this->coreStatus = $initialStatus ?: CoreStatus::ENTRANTE->value;
        $this->substatus = '';
        $this->dueDate = now()->addDays(2)->toDateString();

        $this->showModal = true;
    }

    #[On('open-duplicate-order')]
    public function openDuplicateModal($orderId)
    {
        $this->resetValidation();
        $original = Order::with('designers')->find($orderId);
        if (! $original) {
            return;
        }

        $this->isDuplicating = true;
        $this->originalOrderId = $original->id;

        $this->woNumber = preg_replace('/^WO\s*/i', '', $original->wo_number ?? '');
        $this->trelloCardId = $original->trello_card_id ?? '';
        $this->companyName = $original->company_name ?? '';
        $this->taskName = ($original->task_name ?? '').' (Copia)';
        $this->responsiblePerson = $original->responsible_person ?? '';
        $this->designerId = $original->designer_id;
        $this->designerIds = $original->designers->pluck('id')->toArray();
        if (empty($this->designerIds) && $original->designer_id) {
            $this->designerIds = [$original->designer_id];
        }
        $this->coreStatus = $original->core_status ? $original->core_status->value : CoreStatus::ENTRANTE->value;
        $this->substatus = $original->substatus ? $original->substatus->value : '';
        $this->dueDate = $original->current_due_date ? $original->current_due_date->toDateString() : now()->addDays(2)->toDateString();

        $this->showModal = true;
    }

    public function toggleDesigner($id)
    {
        $id = (int) $id;
        if (in_array($id, $this->designerIds)) {
            $this->designerIds = array_values(array_diff($this->designerIds, [$id]));
        } else {
            $this->designerIds[] = $id;
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->isDuplicating = false;
        $this->originalOrderId = null;
        $this->designerIds = [];
    }

    public function save()
    {
        $this->validate();

        $statusEnum = CoreStatus::tryFrom($this->coreStatus) ?: CoreStatus::ENTRANTE;
        $substatusEnum = ! empty($this->substatus) ? Substatus::tryFrom($this->substatus) : null;
        $cleanWo = trim(preg_replace('/^WO\s*/i', '', $this->woNumber ?? ''));

        $cleanTrelloId = trim($this->trelloCardId ?? '');
        if (preg_match('/trello\.com\/c\/([^\/]+)/i', $cleanTrelloId, $matches)) {
            $cleanTrelloId = $matches[1];
        }

        $order = Order::create([
            'wo_number' => ! empty($cleanWo) ? "WO {$cleanWo}" : null,
            'trello_card_id' => ! empty($cleanTrelloId) ? $cleanTrelloId : null,
            'company_name' => trim($this->companyName),
            'task_name' => trim($this->taskName),
            'responsible_person' => ! empty($this->responsiblePerson) ? trim($this->responsiblePerson) : null,
            'designer_id' => ! empty($this->designerIds) ? reset($this->designerIds) : null,
            'core_status' => $statusEnum,
            'substatus' => $substatusEnum,
            'current_due_date' => ! empty($this->dueDate) ? $this->dueDate : now()->addDays(2)->toDateString(),
            'in_workspace' => true,
        ]);

        $order->syncDesigners($this->designerIds);

        if ($this->isDuplicating && $this->originalOrderId) {
            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'ORDER_DUPLICATED',
                'actor' => 'User',
                'previous_value' => null,
                'new_value' => $order->core_status?->value,
                'metadata' => [
                    'duplicated_from_id' => $this->originalOrderId,
                    'comment' => "Duplicada a partir de la orden #{$this->originalOrderId}",
                ],
            ]);
        }

        // Run automation hooks for new order
        app(AutomationEngine::class)->handleOrderCreated($order);

        $this->closeModal();
        $this->dispatch('order-updated');

        if ($this->isDuplicating) {
            session()->flash('message', "Copia de la orden '{$order->company_name}' creada exitosamente.");
        } else {
            session()->flash('message', "Orden '{$order->company_name}' creada exitosamente.");
        }
    }

    public function render()
    {
        return view('livewire.orders.create-order-modal', [
            'designers' => Designer::where('active', true)->get(),
            'coreStatuses' => CoreStatus::cases(),
            'substatuses' => Substatus::cases(),
            'existingCompanies' => Order::inWorkspace()
                ->whereNotNull('company_name')
                ->where('company_name', '!=', '')
                ->distinct()
                ->orderBy('company_name')
                ->pluck('company_name'),
            'existingResponsibles' => Order::inWorkspace()
                ->whereNotNull('responsible_person')
                ->where('responsible_person', '!=', '')
                ->distinct()
                ->orderBy('responsible_person')
                ->pluck('responsible_person'),
            'availableTrelloCards' => Order::whereNotNull('trello_card_id')
                ->where('trello_card_id', '!=', '')
                ->orderBy('trello_created_at', 'desc')
                ->take(100)
                ->get(['id', 'trello_card_id', 'company_name', 'task_name', 'trello_title', 'wo_number']),
        ]);
    }
}
