<?php

namespace App\Services;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;

class ActionRequiredResolverService
{
    /**
     * Evaluate an order's blocking state and auto-resolve if conditions are met.
     */
    public function evaluateAndAutoResolve(Order $order, ?string $actor = null): bool
    {
        $actor = $actor ?? (auth()->user()?->name ?? 'Sistema');
        $resolved = false;

        // 1. Missing Measures Auto-Resolution
        // Condition: Order was blocked due to missing measures (or approved with measures_confirmed=false)
        if ($order->measures_confirmed && ($order->substatus === Substatus::BLOQUEADA || ($order->approved && ! $order->isBlocked()))) {
            $relatedMeasureTasks = $order->relatedTasks()
                ->where('status', '!=', 'done')
                ->whereNull('completed_at')
                ->whereIn('type', [RelatedTaskType::RESOLVER, RelatedTaskType::SOLICITAR_INFO])
                ->where(function ($q) {
                    $q->where('title', 'like', '%medida%')
                        ->orWhere('title', 'like', '%RESOLVER%');
                })
                ->get();

            foreach ($relatedMeasureTasks as $task) {
                $task->update([
                    'status' => 'done',
                    'completed_at' => now(),
                ]);
            }

            if ($order->substatus === Substatus::BLOQUEADA) {
                $order->unblock(__('Medidas confirmadas manualmente'), $actor);
                $resolved = true;
            }
        }

        // 2. Missing Estimate Approval Auto-Resolution
        // Condition: substatus is FALTA_APROBACION_ESTIMADO, but estimate_approved is now true
        if ($order->substatus === Substatus::FALTA_APROBACION_ESTIMADO && $order->estimate_approved) {
            $newSubstatus = $order->measures_confirmed ? Substatus::PONER_EN_ALTA : null;
            $order->update([
                'substatus' => $newSubstatus,
            ]);

            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'ESTIMATE_AUTO_RESOLVED',
                'actor' => $actor,
                'previous_value' => Substatus::FALTA_APROBACION_ESTIMADO->value,
                'new_value' => $newSubstatus?->value ?? 'Ninguno',
                'metadata' => ['reason' => 'Estimado aprobado manualmente'],
            ]);

            $resolved = true;
        }

        // 3. Trello Core Status Change Auto-Resolution
        // Condition: Order was BLOQUEADA or FALTA_APROBACION_ESTIMADO, but its core_status was moved to an active designer list or TO_DO_TODAY
        if ($order->isBlocked() && (CoreStatus::isPendingDesign($order->core_status) || $order->core_status === CoreStatus::TO_DO_TODAY)) {
            if ($order->core_status !== CoreStatus::ENTRANTE) {
                $order->unblock(__('Tarjeta movida fuera de lista de bloqueo en Trello'), $actor);
                $resolved = true;
            }
        }

        // 4. Reset done_today Anomaly
        // If done_today is true, but order is in TO_DO_TODAY / pending design and has open work subtasks or was rescheduled
        if ($order->done_today && ! in_array($order->core_status, [
            CoreStatus::ENVIADO_A_CAMILA,
            CoreStatus::ENVIADO_AL_CLIENTE,
            CoreStatus::EN_PRODUCCION,
            CoreStatus::ON_HOLD,
            CoreStatus::ARCHIVED,
        ], true)) {
            $hasUncompletedTodayTasks = $order->relatedTasks()
                ->where('status', '!=', 'done')
                ->whereNull('completed_at')
                ->where(function ($q) {
                    $q->whereDate('scheduled_date', '<=', today())
                        ->orWhere(function ($sq) {
                            $sq->whereNull('scheduled_date')
                                ->whereDate('due_date', '<=', today());
                        });
                })
                ->exists();

            if ($hasUncompletedTodayTasks) {
                $order->updateQuietly(['done_today' => false]);
                $resolved = true;
            }
        }

        return $resolved;
    }

    /**
     * Handle completion of a RelatedTask (e.g. RESOLVER / SOLICITAR_INFO).
     */
    public function handleRelatedTaskCompleted(RelatedTask $task, ?string $actor = null): void
    {
        $order = $task->order;
        if (! $order) {
            return;
        }

        $actor = $actor ?? (auth()->user()?->name ?? 'Sistema');

        $isResolverTask = in_array($task->type, [
            RelatedTaskType::RESOLVER,
            RelatedTaskType::SOLICITAR_INFO,
            RelatedTaskType::CORREO_ATRASO,
            RelatedTaskType::BLOCKED,
        ], true);

        if ($isResolverTask && ($order->substatus === Substatus::BLOQUEADA || $order->substatus === Substatus::FALTA_APROBACION_ESTIMADO || $order->customer_service_required)) {
            $otherOpenResolverTasks = $order->relatedTasks()
                ->where('id', '!=', $task->id)
                ->where('status', '!=', 'done')
                ->whereNull('completed_at')
                ->whereIn('type', [
                    RelatedTaskType::RESOLVER,
                    RelatedTaskType::SOLICITAR_INFO,
                    RelatedTaskType::CORREO_ATRASO,
                    RelatedTaskType::BLOCKED,
                ])
                ->exists();

            if (! $otherOpenResolverTasks) {
                $order->unblock(__('Tarea de resolución ":title" completada', ['title' => $task->title]), $actor);
            }
        }
    }

    /**
     * Check if Trello data parity has been reached for an order.
     */
    public function evaluateTrelloDataParity(Order $order, array $parsedData): bool
    {
        $cleanLocalComp = trim($order->company_name ?? '');
        $cleanParsedComp = trim($parsedData['company_name'] ?? '');
        $companyMatch = (empty($cleanParsedComp) || mb_strtolower($cleanLocalComp, 'UTF-8') === mb_strtolower($cleanParsedComp, 'UTF-8'));

        $cleanLocalTask = trim(preg_replace('/\s*\([^\)]+\)\s*/', ' ', $order->task_name ?? ''));
        $cleanParsedTask = trim(preg_replace('/\s*\([^\)]+\)\s*/', ' ', $parsedData['task_name'] ?? ''));
        $taskMatch = (empty($cleanParsedTask) || mb_strtolower($cleanLocalTask, 'UTF-8') === mb_strtolower($cleanParsedTask, 'UTF-8'));

        $cleanLocalWO = preg_replace('/^WO\s*/i', '', trim($order->wo_number ?? ''));
        $cleanTrelloWO = preg_replace('/^WO\s*/i', '', trim($parsedData['wo_number'] ?? ''));
        $woMatch = (empty($cleanTrelloWO) || $cleanLocalWO === $cleanTrelloWO);

        return $companyMatch && $taskMatch && $woMatch;
    }
}
