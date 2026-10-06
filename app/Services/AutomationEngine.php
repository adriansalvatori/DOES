<?php

namespace App\Services;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Enums\SubtaskCategory;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Models\SystemTaskConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class AutomationEngine
{
    public function __construct(
        protected SlaEngine $slaEngine
    ) {}

    /**
     * Triggered when a new order is created.
     */
    public function handleOrderCreated(Order $order, string $source = 'app', ?string $actor = null): void
    {
        // 1. Mandatory Welcome Email task (only if card creation date is no older than a week / 7 days)
        $trelloCreatedAt = $order->trello_created_at ?? $order->created_at;
        $isOlderThanWeek = $trelloCreatedAt && $trelloCreatedAt->lt(now()->subDays(7));

        if (! $isOlderThanWeek) {
            $welcomeScheduledDate = $this->calculateWelcomeEmailScheduledDate($trelloCreatedAt);

            RelatedTask::create([
                'order_id' => $order->id,
                'title' => 'Enviar correo de bienvenida',
                'type' => RelatedTaskType::BIENVENIDA,
                'status' => 'todo',
                'assignee_id' => $order->getPrimaryDesignerId(),
                'scheduled_date' => $welcomeScheduledDate->toDateString(),
                'due_date' => $welcomeScheduledDate->toDateString(),
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

        $resolvedActor = $actor ?: ($source === 'trello' ? 'Trello' : (auth()->user()?->name ?? 'Usuario'));

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_CREATED',
            'actor' => $resolvedActor,
            'previous_value' => null,
            'new_value' => $order->company_name.' - '.$order->task_name,
            'metadata' => [
                'status' => $order->core_status->value,
                'source' => $source,
            ],
        ]);
    }

    /**
     * Triggered when order status changes.
     */
    public function handleStatusChanged(Order $order, CoreStatus $previousStatus, CoreStatus $newStatus, ?string $actor = null): void
    {
        if ($order->core_status !== $newStatus) {
            $order->update(['core_status' => $newStatus]);
        }

        $resolvedActor = $actor ?: (auth()->user()?->name ?? 'Automation');

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'CORE_STATUS_CHANGED',
            'actor' => $resolvedActor,
            'previous_value' => $previousStatus->value,
            'new_value' => $newStatus->value,
            'metadata' => ['timestamp' => now()->toIso8601String()],
        ]);

        // Set done_today to true when moved to status Camila, Client, or Production, otherwise reset to false
        if (in_array($newStatus, [CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::EN_PRODUCCION], true)) {
            $order->update(['done_today' => true]);
        } else {
            $order->update(['done_today' => false]);
        }

        // Set scheduled_date to today when entering TO_DO_TODAY if not already set or in past
        if ($newStatus === CoreStatus::TO_DO_TODAY) {
            $toUpdate = [];
            if (! $order->scheduled_date || $order->scheduled_date->isPast()) {
                $toUpdate['scheduled_date'] = now()->toDateString();
            }
            if ($previousStatus !== CoreStatus::TO_DO_TODAY && ! $order->origin_core_status) {
                $toUpdate['origin_core_status'] = $previousStatus;
                $toUpdate['origin_substatus'] = $order->substatus;
            }
            if (! empty($toUpdate)) {
                $order->update($toUpdate);
            }
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
            $order->update(['substatus' => Substatus::ENVIADO_EN_ALTA]);
        }

        // Handle transitions from ENVIADO A CAMILA -> TO DO TODAY
        if ($previousStatus === CoreStatus::ENVIADO_A_CAMILA && $newStatus === CoreStatus::TO_DO_TODAY) {
            $hasProofTask = $order->relatedTasks()->where('title', 'like', '%enviar proof al cliente%')->exists();
            if (! $hasProofTask && $order->substatus !== null) {
                $order->update([
                    'core_status' => CoreStatus::TO_DO_TODAY,
                    'substatus' => Substatus::CAMBIOS_CAMILA,
                ]);
            }
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
                    'actor' => $resolvedActor,
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
                'category' => SubtaskCategory::MANAGEMENT,
                'status' => 'todo',
                'assignee_id' => $order->getPrimaryDesignerId(),
                'scheduled_date' => now()->addWeekdays(1)->toDateString(),
                'due_date' => now()->addWeekdays(1)->toDateString(),
                'trigger_type' => 'CAMILA_TRANSITION',
                'priority' => $taskPriority,
                'is_work_task' => false,
            ]);
        }

        // Handle transitions from ENVIADO AL CLIENTE -> TO DO TODAY / ORDERS RECEIVED
        if ($previousStatus === CoreStatus::ENVIADO_AL_CLIENTE && ($newStatus === CoreStatus::TO_DO_TODAY || CoreStatus::isPendingDesign($newStatus))) {
            $this->handleClientResponse($order);

            // Clean up obsolete pending client follow-up tasks from previous client cycle
            RelatedTask::where('order_id', $order->id)
                ->where('type', RelatedTaskType::FOLLOW_UP_CLIENTE->value)
                ->where('status', '!=', 'done')
                ->whereNull('completed_at')
                ->forceDelete();
        }

        // Handle entering ENVIADO AL CLIENTE
        if ($newStatus === CoreStatus::ENVIADO_AL_CLIENTE) {
            $wasApproved = $order->approved;

            $updateData = [
                'substatus' => Substatus::WAITING_FOR_CLIENT,
                'client_last_response' => null,
                'last_meaningful_update' => now(),
                'approved' => false,
                'measures_confirmed' => false,
                'estimate_approved' => false,
            ];

            if ($previousStatus !== CoreStatus::ENVIADO_AL_CLIENTE) {
                $updateData['last_sent_to_client_at'] = now();
            }

            $order->update($updateData);

            if ($previousStatus !== CoreStatus::ENVIADO_AL_CLIENTE) {
                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'ORDER_SENT_TO_CLIENT',
                    'actor' => $resolvedActor,
                    'previous_value' => $previousStatus->value,
                    'new_value' => CoreStatus::ENVIADO_AL_CLIENTE->value,
                    'metadata' => [
                        'timestamp' => now()->toIso8601String(),
                        'revision_count' => $order->client_revision_count,
                    ],
                ]);
            }

            if ($wasApproved) {
                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'APPROVAL_RESET',
                    'actor' => $resolvedActor,
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

        // Handle transitions from ON HOLD -> Active Work Status (Resuming work)
        if ($previousStatus === CoreStatus::ON_HOLD && $newStatus !== CoreStatus::ON_HOLD && $newStatus !== CoreStatus::ARCHIVED) {
            $newDueDate = now()->addWeekdays(SlaEngine::RESUME_FROM_HOLD_SLA_DAYS);
            $this->slaEngine->updateDueDate(
                $order,
                $newDueDate,
                'Order resumed from ON HOLD - 2 day SLA reset',
                'RESUMED_FROM_ON_HOLD'
            );
            $order->removeFlag(Substatus::OVERDUE);
            $order->removeFlag(Substatus::ALMOST_OVERDUE);
            if ($order->substatus === Substatus::PAUSADO || $order->substatus === Substatus::CUSTOMER_SERVICE_REQUIRED || $order->substatus === Substatus::NO_RESPUESTA) {
                $order->substatus = null;
            }
            $order->save();
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
    public function processApproval(
        Order $order,
        bool $measuresConfirmed,
        bool $estimateApproved,
        string $approvalType = 'cliente',
        ?string $approvalNote = null,
        ?string $approvalImagePath = null,
        ?CoreStatus $targetStatus = null
    ): void {
        $isUrgente = $order->isUrgente();
        $targetDate = $isUrgente ? now() : now()->addWeekdays(1);
        $approvalLabel = $approvalType === 'camila' ? 'Aprobado por Camila' : 'Aprobado por Cliente';

        if ($isUrgente) {
            $order->addFlag(Substatus::URGENTE);
        }

        $order->update([
            'approved' => true,
            'approved_at' => now(),
            'approval_type' => $approvalType,
            'approval_note' => $approvalNote,
            'approval_image_path' => $approvalImagePath,
            'measures_confirmed' => $measuresConfirmed,
            'estimate_approved' => $estimateApproved,
            'current_due_date' => $targetDate->toDateString(),
            'flags' => $order->flags,
        ]);

        $slaMessage = $isUrgente
            ? "Order approved ({$approvalLabel}) - Urgent same-day SLA set"
            : "Order approved ({$approvalLabel}) - 24 hour SLA set";

        $this->slaEngine->updateDueDate(
            $order,
            $targetDate,
            $slaMessage,
            'ORDER_APPROVED'
        );

        $actor = auth()->user()?->name ?? 'Usuario';

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_APPROVED',
            'actor' => $actor,
            'previous_value' => 'approved: false',
            'new_value' => "{$approvalLabel} (Medidas: ".($measuresConfirmed ? 'SÍ' : 'NO').', Estimado: '.($estimateApproved ? 'SÍ' : 'NO').($isUrgente ? ', URGENTE' : '').')',
            'metadata' => [
                'approval_type' => $approvalType,
                'approval_type_label' => $approvalLabel,
                'approval_note' => $approvalNote,
                'approval_image' => $approvalImagePath ? Storage::url($approvalImagePath) : null,
                'measures_confirmed' => $measuresConfirmed,
                'estimate_approved' => $estimateApproved,
                'new_due_date' => $targetDate->toDateString(),
                'is_urgente' => $isUrgente,
                'sla_message' => $slaMessage,
            ],
        ]);

        if ($measuresConfirmed && $estimateApproved) {
            $finalStatus = $targetStatus ?? $order->getDesignerOrdersReceivedStatus();
            $finalSubstatus = ($finalStatus === CoreStatus::EN_PRODUCCION) ? Substatus::ENVIADO_EN_ALTA : Substatus::PONER_EN_ALTA;

            $order->update([
                'core_status' => $finalStatus,
                'substatus' => $finalSubstatus,
            ]);

            $this->checkAndCreateOverdueTask($order);

            $existingAltaTask = RelatedTask::where('order_id', $order->id)
                ->where(function ($q) {
                    $q->where('type', RelatedTaskType::PONER_ALTA->value)
                        ->orWhere('category', SubtaskCategory::PRODUCTION_ADJUSTMENTS->value)
                        ->orWhere('title', 'like', '%alta%');
                })
                ->where('status', '!=', 'done')
                ->first();

            if ($existingAltaTask && $finalStatus === CoreStatus::EN_PRODUCCION) {
                $existingAltaTask->update([
                    'status' => 'done',
                    'completed_at' => now(),
                    'category' => SubtaskCategory::PRODUCTION_ADJUSTMENTS,
                    'return_core_status' => CoreStatus::EN_PRODUCCION,
                ]);
            } else {
                RelatedTask::create([
                    'order_id' => $order->id,
                    'title' => 'Poner en alta',
                    'type' => RelatedTaskType::PONER_ALTA,
                    'category' => SubtaskCategory::PRODUCTION_ADJUSTMENTS,
                    'return_core_status' => CoreStatus::EN_PRODUCCION,
                    'status' => ($finalStatus === CoreStatus::EN_PRODUCCION) ? 'done' : 'todo',
                    'completed_at' => ($finalStatus === CoreStatus::EN_PRODUCCION) ? now() : null,
                    'assignee_id' => $order->getPrimaryDesignerId(),
                    'scheduled_date' => $targetDate->toDateString(),
                    'due_date' => $targetDate->toDateString(),
                    'trigger_type' => 'ORDER_APPROVED',
                    'priority' => $isUrgente ? 'urgent' : 'normal',
                    'is_work_task' => true,
                ]);
            }
        } elseif (! $measuresConfirmed) {
            // Missing measures -> High priority RESOLVER in ENTRANTE
            $order->update([
                'core_status' => CoreStatus::ENTRANTE,
                'substatus' => Substatus::BLOQUEADA,
                'blocking_reason' => BlockingReason::FALTAN_MEDIDAS,
                'current_due_date' => $targetDate->toDateString(),
            ]);

            RelatedTask::create([
                'order_id' => $order->id,
                'title' => 'RESOLVER: Medidas pendientes para orden aprobada',
                'type' => RelatedTaskType::RESOLVER,
                'status' => 'todo',
                'assignee_id' => $order->getPrimaryDesignerId(),
                'scheduled_date' => $targetDate->toDateString(),
                'due_date' => $targetDate->toDateString(),
                'trigger_type' => 'MISSING_MEASURES_APPROVED',
                'priority' => 'high',
            ]);
        } elseif ($measuresConfirmed && ! $estimateApproved) {
            // Approved but estimate missing -> Move to Orders Received with warning condition
            $targetStatus = $order->getDesignerOrdersReceivedStatus();

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
            'actor' => auth()->user()?->name ?? 'Usuario',
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
            $sentAt = $order->last_sent_to_client_at ?? $order->last_meaningful_update ?? $order->created_at;
            $daysInClient = $sentAt ? (int) $sentAt->diffInWeekdays(now()) : 0;

            if ($daysInClient >= 9) {
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
                    'metadata' => ['reason' => 'Client non-responsive for 9+ business days'],
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
            ->whereIn('core_status', array_merge(CoreStatus::designerQueueStatuses(), [CoreStatus::ENTRANTE]))
            ->get();

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

        // Client auto-transition for orders in TO DO TODAY marked Done with completed "enviar proof al cliente"
        $proofDoneOrders = Order::where('core_status', CoreStatus::TO_DO_TODAY)
            ->where('done_today', true)
            ->whereHas('relatedTasks', function ($q) {
                $q->where('title', 'like', '%enviar proof al cliente%')
                    ->where('status', 'done');
            })
            ->get();

        foreach ($proofDoneOrders as $order) {
            $previousStatus = $order->core_status;
            $order->update([
                'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
                'done_today' => true,
                'last_sent_to_client_at' => now(),
            ]);

            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'MOVED_TO_CLIENT_CAMILA_PREAPPROVED',
                'actor' => 'AutomationEngine',
                'previous_value' => $previousStatus->value,
                'new_value' => CoreStatus::ENVIADO_AL_CLIENTE->value,
                'metadata' => ['trigger' => 'Enviar proof al cliente completed'],
            ]);

            $this->handleStatusChanged($order, $previousStatus, CoreStatus::ENVIADO_AL_CLIENTE, 'AutomationEngine');
        }        // Check for orders due today
        $dueTodayOrders = Order::inWorkspace()->get()->filter(fn ($o) => $o->isDueToday());
        foreach ($dueTodayOrders as $order) {
            NotificationDispatcher::dispatch(
                eventType: 'order_due_today',
                label: 'Due Today',
                order: $order
            );
        }

        // Check for overdue orders and auto-create preventative delay tasks
        $overdueOrders = Order::inWorkspace()->get()->filter(fn ($o) => $o->isOverdue());
        foreach ($overdueOrders as $order) {
            NotificationDispatcher::dispatch(
                eventType: 'order_overdue',
                label: 'Overdue',
                order: $order,
                isUrgent: true
            );
            $this->checkAndCreateOverdueTask($order);
        }
    }

    /**
     * Automatically create a delay task when an order is overdue or due today past 2:30 PM.
     */
    public function checkAndCreateOverdueTask(Order $order): void
    {
        $allowedStatuses = array_merge(CoreStatus::designerQueueStatuses(), [CoreStatus::TO_DO_TODAY]);

        if (! in_array($order->core_status, $allowedStatuses, true)) {
            return;
        }

        // Orders with substatus PONER EN ALTA are internal production handoffs and do not notify client of delays
        $subVal = $order->substatus instanceof \BackedEnum ? $order->substatus->value : ($order->substatus?->value ?? (string) $order->substatus);
        if ($subVal === Substatus::PONER_EN_ALTA->value) {
            RelatedTask::where('order_id', $order->id)
                ->where('type', RelatedTaskType::CORREO_ATRASO)
                ->where('status', '!=', 'done')
                ->whereNull('completed_at')
                ->forceDelete();

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
                    'scheduled_date' => now()->toDateString(),
                    'due_date' => now()->toDateString(),
                    'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION',
                    'priority' => 'urgent',
                ]);
            } else {
                $existingTask->update([
                    'priority' => 'urgent',
                    'scheduled_date' => $existingTask->scheduled_date ?? now()->toDateString(),
                ]);
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
        $sysConfig = SystemTaskConfig::where('task_type', $type->value)->first();
        if ($sysConfig && ! $sysConfig->is_active) {
            return;
        }

        $exists = RelatedTask::where('order_id', $order->id)
            ->where('title', $title)
            ->when($order->last_sent_to_client_at, fn ($q) => $q->where('created_at', '>=', $order->last_sent_to_client_at))
            ->exists();

        if (! $exists) {
            $isFollowUp = in_array($type, [
                RelatedTaskType::FOLLOW_UP_CLIENTE,
                RelatedTaskType::FOLLOW_UP_CAMILA,
                RelatedTaskType::FOLLOW_UP_ALTA,
            ], true) || str_contains(strtolower($title), 'follow up');

            RelatedTask::create([
                'order_id' => $order->id,
                'title' => $title,
                'type' => $type,
                'category' => $isFollowUp ? SubtaskCategory::MANAGEMENT : null,
                'status' => 'todo',
                'assignee_id' => $order->getPrimaryDesignerId(),
                'scheduled_date' => now()->toDateString(),
                'due_date' => now()->toDateString(),
                'trigger_type' => 'CLIENT_FOLLOW_UP_CYCLE',
                'priority' => 'normal',
                'is_work_task' => ! $isFollowUp,
            ]);
        }
    }

    /**
     * Automatically evaluate subtasks scheduled for today or past dates (or all subtasks).
     * Marks the order done_today = true when all subtasks (or all today/past subtasks) are completed.
     * If originating from Sent to Client / Sent to Camila / Production, automatically returns the order upon completion.
     * If future pending work subtasks exist, moves the order back to the designer's column.
     */
    public function evaluateSubtaskCompletionAutoDone(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $allSubtasksQuery = RelatedTask::where('order_id', $order->id);
        $totalAllCount = (clone $allSubtasksQuery)->count();
        $uncompletedAllCount = (clone $allSubtasksQuery)
            ->whereNull('completed_at')
            ->where('status', '!=', 'done')
            ->count();
        $allTasksDone = ($totalAllCount > 0 && $uncompletedAllCount === 0);

        $todayWorkTasksQuery = RelatedTask::where('order_id', $order->id)
            ->where(function ($q) {
                $q->whereNull('is_work_task')
                    ->orWhere('is_work_task', true);
            })
            ->where(function ($q) {
                $q->whereNull('category')
                    ->orWhere('category', '!=', SubtaskCategory::MANAGEMENT->value);
            })
            ->whereNotIn('type', [
                RelatedTaskType::FOLLOW_UP_CLIENTE->value,
                RelatedTaskType::FOLLOW_UP_CAMILA->value,
                RelatedTaskType::FOLLOW_UP_ALTA->value,
            ])
            ->where('title', 'not like', '%follow up%')
            ->where('title', 'not like', '%followup%')
            ->where(function ($q) {
                $q->whereDate('scheduled_date', '<=', today())
                    ->orWhere(function ($sq) {
                        $sq->whereNull('scheduled_date')
                            ->whereDate('due_date', '<=', today());
                    });
            });

        $uncompletedTodayWorkTasks = (clone $todayWorkTasksQuery)
            ->whereNull('completed_at')
            ->where('status', '!=', 'done')
            ->get();

        // 1. Reopening check: If order is in Sent to Client / Camila, but has uncompleted subtasks for today (e.g. unchecked)
        if (in_array($order->core_status, [CoreStatus::ENVIADO_A_CAMILA, CoreStatus::ENVIADO_AL_CLIENTE], true)) {
            if ($uncompletedTodayWorkTasks->isNotEmpty()) {
                $prevStatus = $order->core_status;
                $dominantCategory = $uncompletedTodayWorkTasks->first()?->category ?? SubtaskCategory::detectFromContext('', $order);
                $targetSubstatus = $dominantCategory->triggerSubstatus() ?? match ($prevStatus) {
                    CoreStatus::ENVIADO_AL_CLIENTE => Substatus::CAMBIOS_CLIENTE,
                    CoreStatus::ENVIADO_A_CAMILA => Substatus::CAMBIOS_CAMILA,
                    default => null,
                };

                $order->update([
                    'origin_core_status' => $prevStatus,
                    'origin_substatus' => $order->substatus,
                    'core_status' => CoreStatus::TO_DO_TODAY,
                    'substatus' => $targetSubstatus,
                    'done_today' => false,
                ]);

                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'REOPENED_TO_WORKING_TODAY',
                    'actor' => 'AutomationEngine',
                    'previous_value' => $prevStatus->value,
                    'new_value' => CoreStatus::TO_DO_TODAY->value,
                    'metadata' => [
                        'reason' => 'Subtarea reabierta/desmarcada para hoy, pedido devuelto a Working Today',
                        'task_id' => $uncompletedTodayWorkTasks->first()?->id,
                    ],
                ]);
            } else {
                if (! $order->done_today) {
                    $order->update(['done_today' => true]);
                }
            }

            return;
        }

        // 2. Active working order check (TO_DO_TODAY)
        if ($order->core_status === CoreStatus::TO_DO_TODAY) {
            $totalTodayCount = (clone $todayWorkTasksQuery)->count();
            $uncompletedCount = $uncompletedTodayWorkTasks->count();

            if (($totalTodayCount > 0 && $uncompletedCount === 0) || $allTasksDone) {
                // All work subtasks for today are completed!
                $order->update(['done_today' => true]);
                $this->dismissPendingOverdueTasks($order);

                // Determine return core status:
                // 1. Check if any completed work task for today specifies an explicit return destination or review category
                $targetTask = RelatedTask::where('order_id', $order->id)
                    ->where(function ($q) {
                        $q->whereNull('is_work_task')->orWhere('is_work_task', true);
                    })
                    ->where(function ($q) {
                        $q->whereNull('category')
                            ->orWhere('category', '!=', SubtaskCategory::MANAGEMENT->value);
                    })
                    ->whereNotIn('type', [
                        RelatedTaskType::FOLLOW_UP_CLIENTE->value,
                        RelatedTaskType::FOLLOW_UP_CAMILA->value,
                        RelatedTaskType::FOLLOW_UP_ALTA->value,
                    ])
                    ->where(function ($q) {
                        $q->whereDate('scheduled_date', '<=', today())
                            ->orWhere(function ($sq) {
                                $sq->whereNull('scheduled_date')
                                    ->whereDate('due_date', '<=', today());
                            });
                    })
                    ->where(function ($q) {
                        $q->whereNotNull('return_core_status')
                            ->orWhereIn('category', [
                                SubtaskCategory::CLIENT_ADJUSTMENTS->value,
                                SubtaskCategory::CAMILA_ADJUSTMENTS->value,
                                SubtaskCategory::PRODUCTION_ADJUSTMENTS->value,
                            ])
                            ->orWhere('type', RelatedTaskType::PONER_ALTA->value)
                            ->orWhere('title', 'like', '%alta%');
                    })
                    ->latest('completed_at')
                    ->first();

                if (! $targetTask && $allTasksDone) {
                    $targetTask = RelatedTask::where('order_id', $order->id)
                        ->where(function ($q) {
                            $q->whereNull('is_work_task')->orWhere('is_work_task', true);
                        })
                        ->where(function ($q) {
                            $q->whereNull('category')
                                ->orWhere('category', '!=', SubtaskCategory::MANAGEMENT->value);
                        })
                        ->whereNotIn('type', [
                            RelatedTaskType::FOLLOW_UP_CLIENTE->value,
                            RelatedTaskType::FOLLOW_UP_CAMILA->value,
                            RelatedTaskType::FOLLOW_UP_ALTA->value,
                        ])
                        ->where(function ($q) {
                            $q->whereNotNull('return_core_status')
                                ->orWhereIn('category', [
                                    SubtaskCategory::CLIENT_ADJUSTMENTS->value,
                                    SubtaskCategory::CAMILA_ADJUSTMENTS->value,
                                    SubtaskCategory::PRODUCTION_ADJUSTMENTS->value,
                                ])
                                ->orWhere('type', RelatedTaskType::PONER_ALTA->value)
                                ->orWhere('title', 'like', '%alta%');
                        })
                        ->latest('completed_at')
                        ->first();
                }

                $returnStatus = null;
                if ($targetTask) {
                    $returnStatus = $targetTask->return_core_status ?? ($targetTask->isPonerEnAlta() ? CoreStatus::EN_PRODUCCION : $targetTask->category->defaultReturnCoreStatus());
                }

                // 2. If no task specified an explicit destination, fall back to the order's origin status
                if (! $returnStatus && $order->origin_core_status) {
                    $returnStatus = $order->origin_core_status;
                }

                // If return target is Sent to Client, Sent to Camila, or Production, execute auto-return
                if ($returnStatus && in_array($returnStatus, [CoreStatus::ENVIADO_AL_CLIENTE, CoreStatus::ENVIADO_A_CAMILA, CoreStatus::EN_PRODUCCION], true)) {
                    if ($returnStatus === CoreStatus::EN_PRODUCCION && ! $order->approved) {
                        return;
                    }
                    $prevStatus = $order->core_status;
                    $targetSubstatus = match ($returnStatus) {
                        CoreStatus::ENVIADO_AL_CLIENTE => Substatus::WAITING_FOR_CLIENT,
                        CoreStatus::ENVIADO_A_CAMILA => Substatus::CAMBIOS_CAMILA,
                        CoreStatus::EN_PRODUCCION => Substatus::ENVIADO_EN_ALTA,
                        default => $order->origin_substatus,
                    };

                    $order->update([
                        'origin_core_status' => null,
                        'origin_substatus' => null,
                    ]);

                    $this->handleStatusChanged($order, $prevStatus, $returnStatus, 'AutomationEngine');

                    if ($targetSubstatus) {
                        $order->update(['substatus' => $targetSubstatus]);
                    }

                    OrderEvent::create([
                        'order_id' => $order->id,
                        'event_type' => 'AUTO_RETURNED_AFTER_SUBTASK_COMPLETION',
                        'actor' => 'AutomationEngine',
                        'previous_value' => $prevStatus->value,
                        'new_value' => $returnStatus->value,
                        'metadata' => [
                            'reason' => 'Subtareas completadas para hoy, pedido devuelto a su estado de origen',
                            'return_status' => $returnStatus->value,
                        ],
                    ]);

                    return;
                }

                // If no auto-return status, check if pending work subtasks exist for future dates
                $hasFuturePendingWorkSubtasks = RelatedTask::where('order_id', $order->id)
                    ->where('scheduled_date', '>', today())
                    ->where(function ($q) {
                        $q->whereNull('is_work_task')->orWhere('is_work_task', true);
                    })
                    ->whereNull('completed_at')
                    ->where('status', '!=', 'done')
                    ->exists();

                if ($hasFuturePendingWorkSubtasks) {
                    $targetStatus = $order->getDesignerOrdersReceivedStatus();
                    $order->update([
                        'core_status' => $targetStatus,
                        'done_today' => true,
                        'origin_core_status' => null,
                        'origin_substatus' => null,
                    ]);

                    OrderEvent::create([
                        'order_id' => $order->id,
                        'event_type' => 'ROUTED_TO_DESIGNER_FUTURE_SUBTASKS',
                        'actor' => 'AutomationEngine',
                        'previous_value' => CoreStatus::TO_DO_TODAY->value,
                        'new_value' => $targetStatus->value,
                        'metadata' => ['reason' => 'Today subtasks completed, pending work subtasks scheduled for future dates'],
                    ]);
                }
            } else {
                if ($order->done_today) {
                    $order->update(['done_today' => false]);
                }
            }

            return;
        }

        // 3. For orders in other queues (designer queue, etc.)
        if ($allTasksDone) {
            if (! $order->done_today) {
                $order->update(['done_today' => true]);
            }
        } elseif ($uncompletedTodayWorkTasks->isNotEmpty()) {
            if ($order->done_today) {
                $order->update(['done_today' => false]);
            }
        }
    }

    /**
     * Calculate scheduled date for welcome email based on creation/entry time and 4:30 PM cutoff.
     */
    public function calculateWelcomeEmailScheduledDate(?Carbon $entryTime = null): Carbon
    {
        $time = $entryTime ? $entryTime->copy() : now();
        $today = now()->startOfDay();

        // If the order entry was in the past, do not schedule in the past; evaluate from today
        if ($time->copy()->startOfDay()->lt($today)) {
            $time = now();
        }

        // If entered on a weekend, advance to next business day (Monday)
        if ($time->isWeekend()) {
            return $time->nextWeekday()->startOfDay();
        }

        // Cutoff at 4:30 PM (16:30): if entered at or after 16:30, schedule for next business day
        $isPastCutoff = ($time->hour > 16 || ($time->hour === 16 && $time->minute >= 30));

        if ($isPastCutoff) {
            return $time->addWeekday()->startOfDay();
        }

        return $time->startOfDay();
    }
}
