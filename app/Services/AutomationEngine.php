<?php

namespace App\Services;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use Carbon\Carbon;

class AutomationEngine
{
    public function __construct(
        protected SlaEngine $slaEngine
    ) {}

    /**
     * Triggered when a new order is created.
     */
    public function handleOrderCreated(Order $order): void
    {
        // 1. Mandatory Welcome Email task (only if card creation date is no older than a week / 7 days)
        $trelloCreatedAt = $order->trello_created_at ?? $order->created_at;
        $isOlderThanWeek = $trelloCreatedAt && $trelloCreatedAt->lt(now()->subDays(7));

        if (! $isOlderThanWeek) {
            RelatedTask::create([
                'order_id' => $order->id,
                'title' => 'Enviar correo de bienvenida',
                'type' => RelatedTaskType::BIENVENIDA,
                'status' => 'todo',
                'assignee_id' => $order->getPrimaryDesignerId(),
                'due_date' => now()->toDateString(),
                'trigger_type' => 'NEW_ORDER_CREATED',
                'priority' => 'high',
            ]);
        }

        // 2. Initial due date
        $dueDate = $this->slaEngine->calculateDueDate($order->core_status, now());
        $order->update([
            'start_date' => now()->toDateString(),
            'original_due_date' => $dueDate->toDateString(),
            'current_due_date' => $dueDate->toDateString(),
            'last_meaningful_update' => now(),
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_CREATED',
            'actor' => 'AutomationEngine',
            'previous_value' => null,
            'new_value' => $order->company_name.' - '.$order->task_name,
            'metadata' => ['status' => $order->core_status->value],
        ]);
    }

    /**
     * Triggered when order status changes.
     */
    public function handleStatusChanged(Order $order, CoreStatus $previousStatus, CoreStatus $newStatus): void
    {
        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'CORE_STATUS_CHANGED',
            'actor' => 'User/Automation',
            'previous_value' => $previousStatus->value,
            'new_value' => $newStatus->value,
            'metadata' => ['timestamp' => now()->toIso8601String()],
        ]);

        // Reset done_today when status changes or is re-asserted
        $order->update(['done_today' => false]);

        // Set scheduled_date to today when entering TO_DO_TODAY if not already set or in past
        if ($newStatus === CoreStatus::TO_DO_TODAY && (! $order->scheduled_date || $order->scheduled_date->isPast())) {
            $order->update(['scheduled_date' => now()->toDateString()]);
        }

        // Handle transitions to completion / client / camila / production / on hold / archived states
        if (in_array($newStatus, [CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::EN_PRODUCCION, CoreStatus::ON_HOLD, CoreStatus::ARCHIVED], true)) {
            RelatedTask::where('order_id', $order->id)
                ->where('type', RelatedTaskType::BIENVENIDA->value)
                ->whereNull('completed_at')
                ->where('status', '!=', 'done')
                ->forceDelete();

            $this->dismissTriggeredSubtasks($order);
        }

        // Handle transitions to ARCHIVED
        if ($newStatus === CoreStatus::ARCHIVED) {
            $order->update([
                'archived_at' => $order->archived_at ?? now(),
            ]);
        }

        // Handle transitions to EN_PRODUCCION
        if ($newStatus === CoreStatus::EN_PRODUCCION) {
            $order->update(['substatus' => Substatus::ENVIADO_EN_ALTA, 'done_today' => false]);
        }

        // Handle transitions from ENVIADO A CAMILA -> TO DO TODAY
        if ($previousStatus === CoreStatus::ENVIADO_A_CAMILA && $newStatus === CoreStatus::TO_DO_TODAY) {
            $order->update(['substatus' => Substatus::CAMBIOS_CAMILA]);
        }

        // Handle transitions to ENVIADO A CAMILA
        if ($newStatus === CoreStatus::ENVIADO_A_CAMILA) {
            $wasApproved = $order->approved;

            $order->update([
                'approved' => false,
                'measures_confirmed' => false,
                'estimate_approved' => false,
            ]);

            if ($wasApproved) {
                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'APPROVAL_RESET',
                    'actor' => 'User/Automation',
                    'previous_value' => 'approved: true',
                    'new_value' => 'approved: false (Sent to Camila, requires new approval)',
                    'metadata' => ['timestamp' => now()->toIso8601String()],
                ]);
            }

            $isDueTodayOrOverdue = $order->isDueToday() || $order->isOverdue();
            $taskTitle = $isDueTodayOrOverdue ? 'Llamar a Camila' : 'Follow Up Camila';
            $taskPriority = $isDueTodayOrOverdue ? 'urgent' : 'normal';

            RelatedTask::create([
                'order_id' => $order->id,
                'title' => $taskTitle,
                'type' => RelatedTaskType::FOLLOW_UP_CAMILA,
                'status' => 'todo',
                'assignee_id' => $order->getPrimaryDesignerId(),
                'due_date' => now()->addWeekdays(1)->toDateString(),
                'trigger_type' => 'CAMILA_TRANSITION',
                'priority' => $taskPriority,
            ]);
        }

        // Handle transitions from ENVIADO AL CLIENTE -> TO DO TODAY / ORDERS RECEIVED
        if ($previousStatus === CoreStatus::ENVIADO_AL_CLIENTE && ($newStatus === CoreStatus::TO_DO_TODAY || CoreStatus::isPendingDesign($newStatus))) {
            $this->handleClientResponse($order);
        }

        // Handle entering ENVIADO AL CLIENTE
        if ($newStatus === CoreStatus::ENVIADO_AL_CLIENTE) {
            $wasApproved = $order->approved;

            $order->update([
                'substatus' => Substatus::WAITING_FOR_CLIENT,
                'client_last_response' => null,
                'last_meaningful_update' => now(),
                'approved' => false,
                'measures_confirmed' => false,
                'estimate_approved' => false,
            ]);

            if ($wasApproved) {
                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'APPROVAL_RESET',
                    'actor' => 'User/Automation',
                    'previous_value' => 'approved: true',
                    'new_value' => 'approved: false (Re-sent to client, requires new approval)',
                    'metadata' => ['timestamp' => now()->toIso8601String()],
                ]);
            }
        }

        // Handle entering ON HOLD manually
        if ($newStatus === CoreStatus::ON_HOLD && $order->substatus !== Substatus::NO_RESPUESTA && $order->substatus !== Substatus::CUSTOMER_SERVICE_REQUIRED) {
            $order->update(['substatus' => Substatus::PAUSADO]);
        }
    }

    /**
     * Handle client response / revision cycle.
     */
    public function handleClientResponse(Order $order): void
    {
        $order->increment('client_revision_count');
        $newDueDate = now()->addWeekdays(SlaEngine::CLIENT_CHANGES_SLA_DAYS);

        $this->slaEngine->updateDueDate(
            $order,
            $newDueDate,
            'Client revisions received - 2 day SLA reset',
            'CLIENT_REVISION_RECEIVED'
        );

        $order->update([
            'substatus' => Substatus::CAMBIOS_CLIENTE,
            'client_last_response' => now(),
        ]);
    }

    /**
     * Process Approval Button workflow.
     */
    public function processApproval(Order $order, bool $measuresConfirmed, bool $estimateApproved): void
    {
        $tomorrow = now()->addWeekdays(1);

        $order->update([
            'approved' => true,
            'measures_confirmed' => $measuresConfirmed,
            'estimate_approved' => $estimateApproved,
            'current_due_date' => $tomorrow->toDateString(),
        ]);

        $this->slaEngine->updateDueDate(
            $order,
            $tomorrow,
            'Order approved - 24 hour SLA set',
            'ORDER_APPROVED'
        );

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_APPROVED',
            'actor' => 'User',
            'previous_value' => 'approved: false',
            'new_value' => 'measures: '.($measuresConfirmed ? 'YES' : 'NO').', estimate: '.($estimateApproved ? 'YES' : 'NO'),
            'metadata' => [
                'measures_confirmed' => $measuresConfirmed,
                'estimate_approved' => $estimateApproved,
                'new_due_date' => $tomorrow->toDateString(),
            ],
        ]);

        if ($measuresConfirmed && $estimateApproved) {
            // Fully Approved -> Move to designer Orders Received + PONER EN ALTA
            $targetStatus = match ($order->designer?->name) {
                'Adrián' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
                'César' => CoreStatus::CESAR_ORDERS_RECEIVED,
                default => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            };

            $order->update([
                'core_status' => $targetStatus,
                'substatus' => Substatus::PONER_EN_ALTA,
            ]);
        } elseif (! $measuresConfirmed) {
            // Missing measures -> High priority RESOLVER in ENTRANTE
            $order->update([
                'core_status' => CoreStatus::ENTRANTE,
                'substatus' => Substatus::BLOQUEADA,
                'blocking_reason' => BlockingReason::FALTAN_MEDIDAS,
                'current_due_date' => $tomorrow->toDateString(),
            ]);

            RelatedTask::create([
                'order_id' => $order->id,
                'title' => 'RESOLVER: Medidas pendientes para orden aprobada',
                'type' => RelatedTaskType::RESOLVER,
                'status' => 'todo',
                'assignee_id' => $order->getPrimaryDesignerId(),
                'due_date' => $tomorrow->toDateString(),
                'trigger_type' => 'MISSING_MEASURES_APPROVED',
                'priority' => 'high',
            ]);
        } elseif ($measuresConfirmed && ! $estimateApproved) {
            // Approved but estimate missing -> Move to Orders Received with warning condition
            $targetStatus = match ($order->designer?->name) {
                'Adrián' => CoreStatus::ADRIAN_ORDERS_RECEIVED,
                'César' => CoreStatus::CESAR_ORDERS_RECEIVED,
                default => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            };

            $order->update([
                'core_status' => $targetStatus,
                'substatus' => Substatus::FALTA_APROBACION_ESTIMADO,
            ]);
        }
    }

    /**
     * Resolve Delay workflow after delay email task completed.
     */
    public function resolveDelay(Order $order, Carbon $clientPromisedDate, string $reason): void
    {
        $this->slaEngine->updateDueDate(
            $order,
            $clientPromisedDate,
            $reason,
            'DELAY_RESOLVED_CLIENT_PROMISED_DATE',
            $clientPromisedDate,
            'User'
        );

        $order->update([
            'substatus' => null,
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'DELAY_RESOLVED',
            'actor' => 'User',
            'previous_value' => 'OVERDUE',
            'new_value' => 'Promised Date: '.$clientPromisedDate->toDateString(),
            'metadata' => ['reason' => $reason],
        ]);
    }

    /**
     * Periodic cron / background evaluation of client follow-ups and auto On-Hold transitions.
     */
    public function runDailyAutomations(): void
    {
        $clientOrders = Order::where('core_status', CoreStatus::ENVIADO_AL_CLIENTE)->get();

        foreach ($clientOrders as $order) {
            $daysInClient = $order->updated_at ? $order->updated_at->diffInWeekdays(now()) : 0;

            if ($daysInClient > 9) {
                $order->update([
                    'core_status' => CoreStatus::ON_HOLD,
                    'substatus' => Substatus::CUSTOMER_SERVICE_REQUIRED,
                    'customer_service_required' => true,
                ]);

                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'AUTO_MOVED_ON_HOLD',
                    'actor' => 'AutomationEngine',
                    'previous_value' => CoreStatus::ENVIADO_AL_CLIENTE->value,
                    'new_value' => CoreStatus::ON_HOLD->value,
                    'metadata' => ['reason' => 'More than 9 days without client response'],
                ]);
            } elseif ($daysInClient >= 3 && $daysInClient < 6) {
                $this->ensureTaskExists($order, 'Follow Up Cliente #1', RelatedTaskType::FOLLOW_UP_CLIENTE);
            } elseif ($daysInClient >= 6 && $daysInClient < 9) {
                $this->ensureTaskExists($order, 'Follow Up Cliente #2', RelatedTaskType::FOLLOW_UP_CLIENTE);
            } elseif ($daysInClient === 9) {
                $this->ensureTaskExists($order, 'Follow Up Cliente #3', RelatedTaskType::FOLLOW_UP_CLIENTE);
            }
        }

        // Auto-revert orders in TO DO TODAY that were NOT marked done today back to designer status
        $uncompletedTodayOrders = Order::where('core_status', CoreStatus::TO_DO_TODAY)
            ->where('done_today', false)
            ->get();

        foreach ($uncompletedTodayOrders as $order) {
            $targetStatus = $order->getDesignerOrdersReceivedStatus();
            $order->update([
                'core_status' => $targetStatus,
            ]);

            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'AUTO_REVERTED_TO_DESIGNER_LIST',
                'actor' => 'AutomationEngine',
                'previous_value' => CoreStatus::TO_DO_TODAY->value,
                'new_value' => $targetStatus->value,
                'metadata' => ['reason' => 'End of day automatic reversion for uncompleted order'],
            ]);

            if ($order->trello_card_id) {
                try {
                    app(TrelloSyncService::class)->updateCardOnTrello($order);
                } catch (\Throwable $e) {
                }
            }
        }

        // Auto-promote orders scheduled for today into TO DO TODAY
        $scheduledForToday = Order::inWorkspace()
            ->whereDate('scheduled_date', '<=', today())
            ->whereIn('core_status', [
                CoreStatus::EURALIZ_ORDERS_RECEIVED,
                CoreStatus::ADRIAN_ORDERS_RECEIVED,
                CoreStatus::CESAR_ORDERS_RECEIVED,
                CoreStatus::ENTRANTE,
            ])->get();

        foreach ($scheduledForToday as $order) {
            $order->update(['core_status' => CoreStatus::TO_DO_TODAY]);
            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'SCHEDULED_TODAY_PROMOTED',
                'actor' => 'AutomationEngine',
                'previous_value' => $order->core_status?->value,
                'new_value' => CoreStatus::TO_DO_TODAY->value,
                'metadata' => ['reason' => 'Scheduled date reached'],
            ]);
        }

        // Production auto-transition for orders in TO DO TODAY marked Done with PONER EN ALTA
        $altaDoneOrders = Order::where('core_status', CoreStatus::TO_DO_TODAY)
            ->where('substatus', Substatus::PONER_EN_ALTA)
            ->where('done_today', true)
            ->get();

        foreach ($altaDoneOrders as $order) {
            $order->update([
                'core_status' => CoreStatus::EN_PRODUCCION,
                'substatus' => null,
                'done_today' => false,
            ]);

            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'MOVED_TO_PRODUCTION',
                'actor' => 'AutomationEngine',
                'previous_value' => CoreStatus::TO_DO_TODAY->value,
                'new_value' => CoreStatus::EN_PRODUCCION->value,
                'metadata' => ['trigger' => 'ALTA completed'],
            ]);
        }

        // Check for overdue orders and auto-create preventative delay tasks
        $overdueOrders = Order::inWorkspace()->get()->filter(fn ($o) => $o->isOverdue());
        foreach ($overdueOrders as $order) {
            $this->checkAndCreateOverdueTask($order);
        }
    }

    /**
     * Automatically create a delay task when an order is overdue or due today past 2:30 PM.
     */
    public function checkAndCreateOverdueTask(Order $order): void
    {
        $allowedStatuses = [
            CoreStatus::EURALIZ_ORDERS_RECEIVED,
            CoreStatus::ADRIAN_ORDERS_RECEIVED,
            CoreStatus::CESAR_ORDERS_RECEIVED,
            CoreStatus::TO_DO_TODAY,
        ];

        if (! in_array($order->core_status, $allowedStatuses, true)) {
            return;
        }

        $now = now();
        $isPastTwoThirty = ($now->hour > 14 || ($now->hour === 14 && $now->minute >= 30));

        if ($order->isOverdue() || ($order->isDueToday() && $isPastTwoThirty)) {
            $taskTitle = 'Enviar correo de atraso preventivo';
            $existingTask = RelatedTask::where('order_id', $order->id)
                ->where('type', RelatedTaskType::CORREO_ATRASO)
                ->where('status', '!=', 'done')
                ->whereNull('completed_at')
                ->first();

            if (! $existingTask) {
                RelatedTask::create([
                    'order_id' => $order->id,
                    'title' => $taskTitle,
                    'type' => RelatedTaskType::CORREO_ATRASO,
                    'status' => 'todo',
                    'assignee_id' => $order->getPrimaryDesignerId(),
                    'due_date' => now()->toDateString(),
                    'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION',
                    'priority' => 'urgent',
                ]);
            } else {
                $existingTask->update(['priority' => 'urgent']);
            }
        }
    }

    /**
     * Dismiss / cancel unfulfilled system-triggered tasks when order is completed or transitioned.
     */
    public function dismissTriggeredSubtasks(Order $order, array $exceptTypes = []): void
    {
        $exceptValues = array_map(fn ($t) => $t instanceof \BackedEnum ? $t->value : $t, $exceptTypes);

        RelatedTask::where('order_id', $order->id)
            ->where('status', '!=', 'done')
            ->whereNull('completed_at')
            ->where('type', '!=', RelatedTaskType::SUBTASK->value)
            ->where(function ($q) {
                $q->whereNotNull('trigger_type')
                    ->orWhereIn('type', [
                        RelatedTaskType::BIENVENIDA->value,
                        RelatedTaskType::CORREO_ATRASO->value,
                        RelatedTaskType::FOLLOW_UP_CLIENTE->value,
                        RelatedTaskType::FOLLOW_UP_CAMILA->value,
                        RelatedTaskType::RESOLVER->value,
                    ]);
            })
            ->when(! empty($exceptValues), fn ($q) => $q->whereNotIn('type', $exceptValues))
            ->forceDelete();
    }

    /**
     * Dismiss / cancel unfulfilled delay email tasks when order is completed or transitioned.
     */
    public function dismissPendingOverdueTasks(Order $order): void
    {
        $this->dismissTriggeredSubtasks($order);
    }

    protected function ensureTaskExists(Order $order, string $title, RelatedTaskType $type): void
    {
        $exists = RelatedTask::where('order_id', $order->id)
            ->where('title', $title)
            ->exists();

        if (! $exists) {
            RelatedTask::create([
                'order_id' => $order->id,
                'title' => $title,
                'type' => $type,
                'status' => 'todo',
                'assignee_id' => $order->getPrimaryDesignerId(),
                'due_date' => now()->toDateString(),
                'trigger_type' => 'CLIENT_FOLLOW_UP_CYCLE',
                'priority' => 'normal',
            ]);
        }
    }
}
