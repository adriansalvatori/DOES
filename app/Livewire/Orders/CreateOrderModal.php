<?php

namespace App\Livewire\Orders;

use App\Contracts\WorkOrderNumberGenerator;
use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Services\AutomationEngine;
use App\Services\ClientMatchingService;
use App\Services\StatusTransitionService;
use App\Services\TrelloSyncService;
use Carbon\Carbon;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateOrderModal extends Component
{
    public $showModal = false;

    public $isDuplicating = false;

    public $originalOrderId = null;

    public $woNumber = '';

    public $trelloCardId = '';

    public $createOnTrello = false;

    public $companyName = '';

    public $locationName = '';

    public $taskName = '';

    public $responsiblePerson = '';

    public $designerId = null;

    public $designerIds = [];

    public $coreStatus = CoreStatus::ENTRANTE->value;

    public $substatus = '';

    public array $flags = [];

    public $dueDate = '';

    public bool $showOnHoldModal = false;

    public string $onHoldReason = '';

    protected $rules = [
        'companyName' => 'required|string|max:255',
        'taskName' => 'required|string|max:255',
        'woNumber' => 'nullable|string|max:50',
        'responsiblePerson' => 'nullable|string|max:255',
        'designerIds' => 'nullable|array',
        'designerIds.*' => 'exists:designers,id',
        'coreStatus' => 'required|string',
        'substatus' => 'nullable|string',
        'flags' => 'nullable|array',
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
        $this->createOnTrello = false;
        $this->companyName = '';
        $this->locationName = '';
        $this->taskName = '';
        $this->responsiblePerson = '';
        $this->designerId = null;
        $this->designerIds = [];
        $this->substatus = '';
        $this->flags = [];
        $this->coreStatus = $this->determineCoreStatus()->value;
        $this->dueDate = now()->addWeekdays(2)->toDateString();

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
        $this->locationName = $original->location_name ?? '';
        $this->taskName = mb_strtoupper(($original->task_name ?? '').' (COPIA)', 'UTF-8');
        $this->responsiblePerson = $original->responsible_person ?? '';
        $this->designerId = $original->designer_id;
        $this->designerIds = $original->designers->pluck('id')->toArray();
        if (empty($this->designerIds) && $original->designer_id) {
            $this->designerIds = [$original->designer_id];
        }
        $this->substatus = $original->substatus ? $original->substatus->value : '';
        $this->flags = is_array($original->flags) ? $original->flags : [];
        $this->coreStatus = $this->determineCoreStatus()->value;
        $this->dueDate = $original->current_due_date ? $original->current_due_date->toDateString() : now()->addWeekdays(2)->toDateString();

        $this->showModal = true;
    }

    public function determineCoreStatus(): CoreStatus
    {
        // Regla 3: Si la orden tiene el subestatus "falta algo" (o "bloqueada"), entra en bloqueada (ENTRANTE) hasta que se quite
        if ($this->hasSubstatusOrFlag('FALTA ALGO') || $this->hasSubstatusOrFlag('BLOQUEADA')) {
            return CoreStatus::ENTRANTE;
        }

        // Regla 2: Si la orden tiene el subestatus urgente debe entrar directamente en working today
        if ($this->hasSubstatusOrFlag('URGENTE')) {
            return CoreStatus::TO_DO_TODAY;
        }

        // Regla 1: La orden siempre debe aparecer en el corestatus de orden recibida del diseñador seleccionado
        if (! empty($this->designerIds)) {
            $firstId = reset($this->designerIds);
            $designer = Designer::find($firstId);
            if ($designer) {
                return $designer->getQueueStatus();
            }
        }

        $lead = Designer::getLeadDesigner();
        if ($lead) {
            return $lead->getQueueStatus();
        }

        return CoreStatus::EURALIZ_ORDERS_RECEIVED;
    }

    public function hasSubstatusOrFlag(string $name): bool
    {
        $clean = mb_strtoupper(trim($name), 'UTF-8');

        if (! empty($this->substatus) && mb_strtoupper(trim($this->substatus), 'UTF-8') === $clean) {
            return true;
        }

        foreach ($this->flags as $flag) {
            if (mb_strtoupper(trim((string) $flag), 'UTF-8') === $clean) {
                return true;
            }
        }

        return false;
    }

    public function toggleDesigner($id)
    {
        $id = (int) $id;
        if (in_array($id, $this->designerIds)) {
            $this->designerIds = array_values(array_diff($this->designerIds, [$id]));
        } else {
            $this->designerIds[] = $id;

            $designer = Designer::find($id);
            if ($designer && $designer->is_external) {
                $lead = Designer::getLeadDesigner();
                if ($lead && ! in_array($lead->id, $this->designerIds)) {
                    $this->designerIds[] = $lead->id;
                }
            }
        }

        $this->coreStatus = $this->determineCoreStatus()->value;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showOnHoldModal = false;
        $this->onHoldReason = '';
        $this->isDuplicating = false;
        $this->originalOrderId = null;
        $this->designerIds = [];
        $this->flags = [];
    }

    public function generateWoNumber(?WorkOrderNumberGenerator $generator = null): void
    {
        $generator ??= app(WorkOrderNumberGenerator::class);

        $this->woNumber = $generator->generateNextDigits([
            'company_name' => mb_strtoupper(trim($this->companyName ?? ''), 'UTF-8'),
            'task_name' => mb_strtoupper(trim($this->taskName ?? ''), 'UTF-8'),
        ]);
    }

    public function selectSubstatus(?string $value = null): void
    {
        if (empty($value)) {
            $this->substatus = '';
            $this->flags = [];
            $this->coreStatus = $this->determineCoreStatus()->value;

            return;
        }

        $subModel = \App\Models\Substatus::where('name', $value)->first();
        $isGlobal = $subModel ? (bool) $subModel->is_global : (Substatus::tryFrom($value)?->isGlobal() ?? false);

        if ($isGlobal) {
            if (in_array($value, $this->flags, true)) {
                $this->flags = array_values(array_filter($this->flags, fn ($f) => $f !== $value));
            } else {
                $this->flags[] = $value;
            }
        } else {
            if ($this->substatus === $value) {
                $this->substatus = '';
            } else {
                $this->substatus = $value;
            }

            $this->updatedSubstatus($this->substatus);
        }

        $this->coreStatus = $this->determineCoreStatus()->value;
    }

    public function setDueDatePreset(string $preset): void
    {
        $target = match ($preset) {
            'today' => now()->toDateString(),
            'tomorrow' => now()->addDay()->toDateString(),
            default => '',
        };

        if ($this->dueDate === $target) {
            $this->dueDate = '';
        } else {
            $this->dueDate = $target;
        }
    }

    public function updatedSubstatus($value)
    {
        if ($value === Substatus::ENVIADO_EN_ALTA->value || $value === 'ENVIADO EN ALTA') {
            $this->coreStatus = CoreStatus::EN_PRODUCCION->value;
        } elseif ($value === Substatus::PAUSADO->value || $value === 'PAUSADO') {
            $this->coreStatus = CoreStatus::ON_HOLD->value;
            $this->openOnHoldModal();
        }
    }

    public function updatedCoreStatus($value)
    {
        $defaultSub = app(StatusTransitionService::class)->getDefaultSubstatus($value);
        if ($defaultSub) {
            $this->substatus = $defaultSub->value;
        }

        if ($value === CoreStatus::ON_HOLD->value || $value === 'ON HOLD' || $value === 'PAUSA') {
            $this->openOnHoldModal();
        }
    }

    public function openOnHoldModal(): void
    {
        $this->onHoldReason = '';
        $this->showOnHoldModal = true;
    }

    public function closeOnHoldModal(): void
    {
        $this->showOnHoldModal = false;
        $this->onHoldReason = '';
    }

    public function confirmOnHold(): void
    {
        $this->validate([
            'onHoldReason' => 'required|string|min:3',
        ], [
            'onHoldReason.required' => 'Debes ingresar un motivo para poner la orden en On Hold.',
            'onHoldReason.min' => 'El motivo debe tener al menos 3 caracteres.',
        ]);

        $this->showOnHoldModal = false;
        $this->save();
    }

    public function save()
    {
        $this->companyName = mb_strtoupper(trim($this->companyName ?? ''), 'UTF-8');
        $this->taskName = mb_strtoupper(trim($this->taskName ?? ''), 'UTF-8');

        $this->validate();

        $statusEnum = $this->determineCoreStatus();
        $this->coreStatus = $statusEnum->value;

        $substatusEnum = ! empty($this->substatus) ? Substatus::tryFrom($this->substatus) : null;
        $cleanWo = trim(preg_replace('/^WO\s*/i', '', $this->woNumber ?? ''));

        $cleanTrelloId = trim($this->trelloCardId ?? '');
        if (preg_match('/trello\.com\/c\/([^\/]+)/i', $cleanTrelloId, $matches)) {
            $cleanTrelloId = $matches[1];
        }

        $cleanLocation = ! empty($this->locationName) ? mb_strtoupper(trim($this->locationName), 'UTF-8') : null;
        $cleanCompany = mb_strtoupper(trim($this->companyName), 'UTF-8');

        $matched = app(ClientMatchingService::class)->matchOrCreate(
            $cleanCompany.($cleanLocation ? ' REF '.$cleanLocation : ''),
            ! empty($this->responsiblePerson) ? trim($this->responsiblePerson) : null
        );

        $order = Order::create([
            'wo_number' => ! empty($cleanWo) ? "WO {$cleanWo}" : null,
            'trello_card_id' => ! empty($cleanTrelloId) ? $cleanTrelloId : null,
            'company_name' => $matched['client'] ? $matched['client']->name : $cleanCompany,
            'location_name' => $cleanLocation,
            'client_id' => $matched['client']?->id,
            'client_location_id' => $matched['location']?->id,
            'task_name' => mb_strtoupper(trim($this->taskName), 'UTF-8'),
            'responsible_person' => ! empty($this->responsiblePerson) ? trim($this->responsiblePerson) : null,
            'designer_id' => ! empty($this->designerIds) ? reset($this->designerIds) : null,
            'core_status' => $statusEnum,
            'substatus' => $substatusEnum,
            'flags' => ! empty($this->flags) ? array_values($this->flags) : null,
            'current_due_date' => ! empty($this->dueDate) ? $this->dueDate : now()->addWeekdays(2)->toDateString(),
            'in_workspace' => true,
        ]);

        $order->syncDesigners($this->designerIds);

        if ($this->isDuplicating && $this->originalOrderId) {
            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'ORDER_DUPLICATED',
                'actor' => auth()->user()?->name ?? 'Usuario',
                'previous_value' => null,
                'new_value' => $order->core_status?->value,
                'metadata' => [
                    'duplicated_from_id' => $this->originalOrderId,
                    'comment' => "Duplicada a partir de la orden #{$this->originalOrderId}",
                ],
            ]);
        }

        if ($statusEnum === CoreStatus::ON_HOLD && ! empty($this->onHoldReason)) {
            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'MOVED_TO_ON_HOLD',
                'actor' => auth()->user()?->name ?? 'Usuario',
                'previous_value' => null,
                'new_value' => CoreStatus::ON_HOLD->value,
                'metadata' => [
                    'reason' => $this->onHoldReason,
                    'comment' => $this->onHoldReason,
                ],
            ]);
        }

        // Run automation hooks for new order
        app(AutomationEngine::class)->handleOrderCreated($order, source: 'app', actor: auth()->user()?->name ?? 'Usuario');

        if ($this->createOnTrello && empty($cleanTrelloId)) {
            app(TrelloSyncService::class)->createCardOnTrello($order);
        }

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
        if (! $this->showModal) {
            return view('livewire.orders.create-order-modal', [
                'designers' => collect(),
                'mostAvailableDesigner' => null,
                'mostAvailableCount' => 0,
                'designerWorkloads' => [],
                'coreStatuses' => [],
                'substatuses' => [],
                'existingCompanies' => collect(),
                'existingResponsibles' => collect(),
                'existingLocations' => collect(),
                'clientLocations' => [],
                'clientContacts' => [],
                'availableTrelloCards' => collect(),
            ]);
        }

        $client = ! empty($this->companyName)
            ? Client::with(['locations', 'contacts'])->where('name', mb_strtoupper(trim($this->companyName), 'UTF-8'))->first()
            : null;

        $clientLocations = $client ? $client->locations->pluck('name')->filter()->toArray() : [];
        $clientContacts = $client ? $client->contacts->pluck('name')->filter()->toArray() : [];

        $this->coreStatus = $this->determineCoreStatus()->value;
        $validSubstatuses = app(StatusTransitionService::class)->getValidSubstatuses($this->coreStatus);

        $hiddenSubstatuses = [
            'OVERDUE',
            'ALMOST OVERDUE',
            'PROCESO DE PERMISO',
            'BLOQUEADA',
        ];

        $validSubstatuses = $validSubstatuses->filter(function ($sub) use ($hiddenSubstatuses) {
            $name = $sub instanceof \App\Models\Substatus ? $sub->name : ($sub->value ?? (string) $sub);
            $clean = mb_strtoupper(trim($name), 'UTF-8');

            return ! in_array($clean, $hiddenSubstatuses, true);
        });

        $startOfWeek = now()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $startOfWeek->copy()->addDays(4)->endOfDay();
        $internalDesigners = Designer::where('active', true)->internal()->get();

        $subtasks = RelatedTask::with(['order.designers', 'order.designer'])
            ->where(function ($q) {
                $q->whereNull('order_id')
                    ->orWhereHas('order', fn ($oq) => $oq->where('in_workspace', true)->orWhere('core_status', CoreStatus::ARCHIVED));
            })
            ->where(function ($q) {
                $q->whereNotNull('scheduled_date')
                    ->orWhere(function ($sq) {
                        $sq->whereNull('scheduled_date')->whereNotNull('due_date');
                    });
            })
            ->get();

        foreach ($subtasks as $st) {
            if (! $st->scheduled_date && $st->due_date) {
                $st->scheduled_date = $st->due_date;
            }
        }

        $designerWorkloads = [];
        foreach ($internalDesigners as $des) {
            $count = $subtasks->filter(function ($st) use ($des, $startOfWeek, $endOfWeek) {
                $isAssigned = false;
                if ($st->assignee_id) {
                    $isAssigned = (int) $st->assignee_id === (int) $des->id;
                } elseif ($st->order) {
                    $isAssigned = (int) $st->order->designer_id === (int) $des->id || $st->order->designers->contains('id', $des->id);
                }
                if (! $isAssigned) {
                    return false;
                }

                $date = $st->scheduled_date;
                if (! $date) {
                    return false;
                }

                if ($date->between($startOfWeek, $endOfWeek)) {
                    return true;
                }
                if ($date->lt($startOfWeek) && $st->status !== 'done') {
                    return true;
                }

                return false;
            })->count();

            $designerWorkloads[$des->id] = $count;
        }

        asort($designerWorkloads);
        $mostAvailableDesignerId = array_key_first($designerWorkloads);
        $mostAvailableDesigner = $mostAvailableDesignerId ? $internalDesigners->firstWhere('id', $mostAvailableDesignerId) : null;
        $mostAvailableCount = $mostAvailableDesignerId ? ($designerWorkloads[$mostAvailableDesignerId] ?? 0) : 0;

        return view('livewire.orders.create-order-modal', [
            'designers' => Designer::where('active', true)->get(),
            'mostAvailableDesigner' => $mostAvailableDesigner,
            'mostAvailableCount' => $mostAvailableCount,
            'designerWorkloads' => $designerWorkloads,
            'coreStatuses' => CoreStatus::cases(),
            'substatuses' => $validSubstatuses,
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
            'existingLocations' => Order::whereNotNull('location_name')
                ->where('location_name', '!=', '')
                ->distinct()
                ->orderBy('location_name')
                ->pluck('location_name'),
            'clientLocations' => $clientLocations,
            'clientContacts' => $clientContacts,
            'availableTrelloCards' => Order::whereNotNull('trello_card_id')
                ->where('trello_card_id', '!=', '')
                ->orderBy('trello_created_at', 'desc')
                ->take(100)
                ->get(['id', 'trello_card_id', 'company_name', 'task_name', 'trello_title', 'wo_number']),
        ]);
    }
}
