<?php

namespace App\Livewire\Orders;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\Order;
use App\Services\AutomationEngine;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateOrderModal extends Component
{
    public $showModal = false;

    public $woNumber = '';
    public $companyName = '';
    public $taskName = '';
    public $responsiblePerson = '';
    public $designerId = null;
    public $coreStatus = CoreStatus::ENTRANTE->value;
    public $substatus = '';
    public $dueDate = '';

    protected $rules = [
        'companyName' => 'required|string|max:255',
        'taskName' => 'required|string|max:255',
        'woNumber' => 'nullable|string|max:50',
        'responsiblePerson' => 'nullable|string|max:255',
        'designerId' => 'nullable|exists:designers,id',
        'coreStatus' => 'required|string',
        'substatus' => 'nullable|string',
        'dueDate' => 'nullable|date',
    ];

    #[On('open-create-order')]
    public function openModal($initialStatus = null)
    {
        $this->resetValidation();
        $this->woNumber = '';
        $this->companyName = '';
        $this->taskName = '';
        $this->responsiblePerson = '';
        $this->designerId = null;
        $this->coreStatus = $initialStatus ?: CoreStatus::ENTRANTE->value;
        $this->substatus = '';
        $this->dueDate = now()->addDays(2)->toDateString();
        
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
    }

    public function save()
    {
        $this->validate();

        $statusEnum = CoreStatus::tryFrom($this->coreStatus) ?: CoreStatus::ENTRANTE;
        $substatusEnum = !empty($this->substatus) ? Substatus::tryFrom($this->substatus) : null;
        $cleanWo = trim(preg_replace('/^WO\s*/i', '', $this->woNumber ?? ''));

        $order = Order::create([
            'wo_number' => !empty($cleanWo) ? "WO {$cleanWo}" : null,
            'company_name' => trim($this->companyName),
            'task_name' => trim($this->taskName),
            'responsible_person' => !empty($this->responsiblePerson) ? trim($this->responsiblePerson) : null,
            'designer_id' => $this->designerId ?: null,
            'core_status' => $statusEnum,
            'substatus' => $substatusEnum,
            'current_due_date' => !empty($this->dueDate) ? $this->dueDate : now()->addDays(2)->toDateString(),
            'in_workspace' => true,
        ]);

        // Run automation hooks for new order
        app(AutomationEngine::class)->handleOrderCreated($order);

        $this->closeModal();
        $this->dispatch('order-updated');

        session()->flash('message', "Orden '{$order->company_name}' creada exitosamente.");
    }

    public function render()
    {
        return view('livewire.orders.create-order-modal', [
            'designers' => Designer::where('active', true)->get(),
            'coreStatuses' => CoreStatus::cases(),
            'substatuses' => Substatus::cases(),
        ]);
    }
}
