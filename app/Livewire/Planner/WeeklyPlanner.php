<?php

namespace App\Livewire\Planner;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Enums\SubtaskCategory;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Models\SubtaskPreset;
use App\Services\AutomationEngine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class WeeklyPlanner extends Component
{
    #[On('order-updated')]
    #[On('order-created')]
    #[On('order-deleted')]
    #[On('task-added')]
    #[On('subtask-updated')]
    #[On('subtask-deleted')]
    #[On('subtask-restored')]
    public function refreshPlanner(): void
    {
        // Automatically re-renders planner view when orders or subtasks are created, updated, duplicated, or deleted
    }

    public $selectedWeekStart;

    public $selectedDesignerFilter = 'all';

    public string $viewMode = 'by_designer';

    public string $plannerSortBy = 'priority';

    public $viewMonth;

    public string $unscheduledSearch = '';

    public string $backlogSearch = '';

    public bool $showSlaWarningModal = false;

    public bool $showSystemTasks = true;

    public array $slaWarningDetails = [];

    public array $recentlyDeletedSubtaskIds = [];

    public function closeSlaWarningModal()
    {
        $this->showSlaWarningModal = false;
        $this->slaWarningDetails = [];
    }

    public function mount()
    {
        $this->selectedWeekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->viewMonth = now()->format('Y-m');
        $this->viewMode = session('weekly_planner_view_mode', 'by_designer');
        $sessionSort = session('weekly_planner_sort_by', 'priority');
        $this->plannerSortBy = in_array($sessionSort, ['custom', 'priority']) ? $sessionSort : 'priority';
        $this->showSystemTasks = (bool) session('weekly_planner_show_system_tasks', true);

        $user = Auth::user();
        if ($user && $user->isDesigner() && $user->designer) {
            $this->selectedDesignerFilter = (string) $user->designer->id;
        }
    }

    public function updatedPlannerSortBy($value)
    {
        if (in_array($value, ['custom', 'priority'])) {
            session(['weekly_planner_sort_by' => $value]);
        }
    }

    public function changePlannerSortBy(string $mode)
    {
        if (in_array($mode, ['custom', 'priority'])) {
            $this->plannerSortBy = $mode;
            session(['weekly_planner_sort_by' => $mode]);
        }
    }

    public function reorderSubtasks(array $orderedTaskIds, ?string $dateString = null)
    {
        if (empty($orderedTaskIds)) {
            return;
        }

        $this->changePlannerSortBy('custom');

        foreach ($orderedTaskIds as $index => $taskId) {
            $updateData = ['sort_order' => $index];
            if (! empty($dateString)) {
                $updateData['scheduled_date'] = Carbon::parse($dateString)->toDateString();
            }
            RelatedTask::where('id', $taskId)->update($updateData);
        }

        $this->dispatch('order-updated');
    }

    public function sortSubtaskCollection($subtasksCollection)
    {
        return match ($this->plannerSortBy) {
            'priority' => $subtasksCollection->sortBy(function ($st) {
                $isDone = $st->isDone() ? 1 : 0;
                $order = $st->order;
                $isUrgente = ($order && $order->isUrgente()) || $st->priority === 'high';
                $isOverdue = $order && ($order->isOverdue() || ($st->scheduled_date && $order->current_due_date && $st->scheduled_date->gt($order->current_due_date)));

                $rank = match (true) {
                    $isDone => 5,
                    $isUrgente => 1,
                    $isOverdue => 2,
                    ! $isDone && $st->priority !== 'low' => 3,
                    ! $isDone && $st->priority === 'low' => 4,
                    default => 5,
                };

                $dueDateTimestamp = $order?->current_due_date ? $order->current_due_date->timestamp : PHP_INT_MAX;

                return [$rank, $st->sort_order ?? 0, $dueDateTimestamp, $st->id];
            })->values(),

            'client' => $subtasksCollection->sortBy(function ($st) {
                $isDone = $st->isDone() ? 1 : 0;
                $company = mb_strtolower($st->order?->company_name ?? $st->title);

                return [$isDone, $company, $st->sort_order ?? 0, $st->id];
            })->values(),

            'sla' => $subtasksCollection->sortBy(function ($st) {
                $isDone = $st->isDone() ? 1 : 0;
                $dueDateTimestamp = $st->order?->current_due_date ? $st->order->current_due_date->timestamp : PHP_INT_MAX;

                return [$isDone, $dueDateTimestamp, $st->sort_order ?? 0, $st->id];
            })->values(),

            default => $subtasksCollection->sortBy(fn ($st) => [$st->sort_order ?? 0, $st->id])->values(),
        };
    }

    public function updatedShowSystemTasks($value)
    {
        session(['weekly_planner_show_system_tasks' => (bool) $value]);
    }

    public function toggleShowSystemTasks()
    {
        $this->showSystemTasks = ! $this->showSystemTasks;
        session(['weekly_planner_show_system_tasks' => $this->showSystemTasks]);
    }

    public function updatedViewMode($value)
    {
        if (in_array($value, ['by_day', 'by_designer'])) {
            session(['weekly_planner_view_mode' => $value]);
        }
    }

    public function changeViewMode(string $mode)
    {
        if (in_array($mode, ['by_day', 'by_designer'])) {
            $this->viewMode = $mode;
            session(['weekly_planner_view_mode' => $mode]);
        }
    }

    public function previousWeek()
    {
        $this->selectedWeekStart = Carbon::parse($this->selectedWeekStart)->subWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->syncViewMonth();
    }

    public function nextWeek()
    {
        $this->selectedWeekStart = Carbon::parse($this->selectedWeekStart)->addWeek()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->syncViewMonth();
    }

    public function thisWeek()
    {
        $this->selectedWeekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->syncViewMonth();
    }

    public function jumpWeeks($weeks)
    {
        $this->selectedWeekStart = now()->addWeeks($weeks)->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->syncViewMonth();
    }

    public function selectWeekFromDate($dateString)
    {
        $this->selectedWeekStart = Carbon::parse($dateString)->startOfWeek(Carbon::MONDAY)->toDateString();
        $this->syncViewMonth();
    }

    public function previousMonth()
    {
        $this->viewMonth = Carbon::parse($this->viewMonth.'-01')->subMonth()->format('Y-m');
    }

    public function nextMonth()
    {
        $this->viewMonth = Carbon::parse($this->viewMonth.'-01')->addMonth()->format('Y-m');
    }

    protected function syncViewMonth()
    {
        $this->viewMonth = Carbon::parse($this->selectedWeekStart)->format('Y-m');
    }

    public function updatedSelectedWeekStart($value)
    {
        if ($value) {
            $this->selectedWeekStart = Carbon::parse($value)->startOfWeek(Carbon::MONDAY)->toDateString();
            $this->syncViewMonth();
        }
    }

    public function getMiniCalendarDaysProperty()
    {
        $viewDate = Carbon::parse($this->viewMonth.'-01');
        $startOfGrid = $viewDate->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $endOfGrid = $viewDate->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $selectedMonday = Carbon::parse($this->selectedWeekStart)->startOfWeek(Carbon::MONDAY)->toDateString();
        $currentMonday = now()->startOfWeek(Carbon::MONDAY)->toDateString();

        $grid = [];
        $current = $startOfGrid->copy();
        while ($current->lte($endOfGrid)) {
            $weekMonday = $current->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $grid[] = [
                'date' => $current->copy(),
                'date_string' => $current->toDateString(),
                'day_number' => $current->day,
                'is_current_month' => $current->month === $viewDate->month,
                'is_today' => $current->isToday(),
                'is_selected_week' => $weekMonday === $selectedMonday,
                'is_current_week' => $weekMonday === $currentMonday,
                'week_monday' => $weekMonday,
            ];
            $current->addDay();
        }

        return collect($grid);
    }

    public function getExistingSubtasksForDay(Carbon $scheduledDate, ?int $designerId = null, ?int $excludeTaskId = null)
    {
        $startDate = Carbon::parse($this->selectedWeekStart ?? now()->startOfWeek(Carbon::MONDAY)->toDateString())->startOfWeek(Carbon::MONDAY);
        $nextWeekMonday = $startDate->copy()->addWeek()->startOfWeek(Carbon::MONDAY);
        $isNextWeek = $scheduledDate->gte($nextWeekMonday);
        $isFirstDayOfWeek = ! $isNextWeek && $scheduledDate->toDateString() === $startDate->toDateString();

        $subtasks = RelatedTask::with(['order.clientLocation', 'order.designer', 'order.designers'])
            ->where(function ($q) {
                $q->whereNull('order_id')
                    ->orWhereHas('order', fn ($oq) => $oq->inWorkspace())
                    ->orWhere(function ($sq) {
                        $sq->whereHas('order', fn ($oq) => $oq->where('core_status', CoreStatus::ARCHIVED)->orWhereNotNull('archived_at'))
                            ->whereNull('trigger_type')
                            ->where(function ($tq) {
                                $tq->whereNull('type')
                                    ->orWhereNotIn('type', [
                                        RelatedTaskType::CORREO_ATRASO->value,
                                        RelatedTaskType::BIENVENIDA->value,
                                    ]);
                            });
                    });
            })
            ->where(function ($q) {
                $q->whereNotNull('scheduled_date')
                    ->orWhere(function ($sq) {
                        $sq->whereNull('scheduled_date')
                            ->whereNotNull('due_date');
                    });
            })
            ->when($excludeTaskId, fn ($q) => $q->where('id', '!=', $excludeTaskId))
            ->get();

        foreach ($subtasks as $st) {
            if (! $st->scheduled_date && $st->due_date) {
                $st->scheduled_date = $st->due_date;
            }
        }

        $designerSubtasks = $subtasks->filter(function ($st) use ($designerId) {
            if (! $designerId) {
                return true;
            }
            if ($st->assignee_id) {
                return (int) $st->assignee_id === (int) $designerId;
            }
            if ($st->order) {
                return (int) $st->order->designer_id === (int) $designerId || $st->order->designers->contains('id', $designerId);
            }

            return false;
        });

        $daySubtasks = $isNextWeek
            ? $designerSubtasks->filter(fn ($st) => $st->scheduled_date && $st->scheduled_date->gte(Carbon::parse($scheduledDate->toDateString())))
            : $designerSubtasks->filter(function ($st) use ($scheduledDate, $isFirstDayOfWeek) {
                if ($st->scheduled_date?->toDateString() === $scheduledDate->toDateString()) {
                    return true;
                }
                if ($isFirstDayOfWeek && $st->scheduled_date && $st->scheduled_date->lt($scheduledDate) && $st->status !== 'done') {
                    if ($st->order && $st->order->isArchived() && $st->isSystemTask()) {
                        return false;
                    }

                    return true;
                }

                return false;
            });

        return $this->sortSubtaskCollection($daySubtasks);
    }

    public function getNextSortOrderForDay(Carbon $scheduledDate, ?int $designerId = null, ?int $excludeTaskId = null): int
    {
        $existing = $this->getExistingSubtasksForDay($scheduledDate, $designerId, $excludeTaskId);

        foreach ($existing->values() as $index => $task) {
            if ($task->sort_order !== $index) {
                RelatedTask::where('id', $task->id)->update(['sort_order' => $index]);
                $task->sort_order = $index;
            }
        }

        return $existing->count();
    }

    public function scheduleOrder($orderId, $dateString)
    {
        $order = Order::findOrFail($orderId);
        $this->scheduleSubtask($orderId, $order->task_name ?: __('Trabajo programado'), $dateString);
    }

    public function scheduleSubtask($orderId = null, $title = '', $dateString = '', $designerId = null, $isWorkTask = true)
    {
        $scheduledDate = Carbon::parse($dateString);

        if (! empty($orderId)) {
            $order = Order::findOrFail($orderId);
            $assigneeId = $designerId ?: ($order->designer_id ?? $order->designers->first()?->id);
            $rawTitle = trim($title) ?: ($order->task_name ?: __('Trabajo programado'));
            $taskTitle = RelatedTask::cleanTitleForOrder($rawTitle, $order);

            $preset = SubtaskPreset::where('title', $rawTitle)->orWhere('title', $taskTitle)->first();
            $category = $preset?->category ?? SubtaskCategory::detectFromContext($taskTitle, $order);
            $isWorkTask = $preset ? (bool) $preset->is_work_task : (bool) $isWorkTask;
            if ($category === SubtaskCategory::MANAGEMENT || str_contains(strtolower($rawTitle), 'follow up') || str_contains(strtolower($taskTitle), 'follow up')) {
                $isWorkTask = false;
            }

            $nextSortOrder = $this->getNextSortOrderForDay($scheduledDate, $assigneeId);

            $subtask = RelatedTask::create([
                'order_id' => $order->id,
                'title' => $taskTitle,
                'type' => RelatedTaskType::SUBTASK->value,
                'category' => $category,
                'return_core_status' => $category->defaultReturnCoreStatus() ?? ($order->core_status !== CoreStatus::TO_DO_TODAY ? $order->core_status : null),
                'scheduled_date' => $scheduledDate->toDateString(),
                'assignee_id' => $assigneeId,
                'status' => 'todo',
                'priority' => 'normal',
                'is_work_task' => $isWorkTask,
                'sort_order' => $nextSortOrder,
            ]);

            $previousStatus = $order->core_status;
            $updateData = ['in_workspace' => true];

            if ($isWorkTask && $scheduledDate->isToday()) {
                $updateData['scheduled_date'] = $scheduledDate->toDateString();

                if ($order->core_status === CoreStatus::ARCHIVED) {
                    $updateData['core_status'] = CoreStatus::TO_DO_TODAY;
                    $updateData['substatus'] = Substatus::TICKET;
                    $updateData['archived_at'] = null;
                } elseif ($order->core_status === CoreStatus::EN_PRODUCCION) {
                    // Keep EN PRODUCCIÓN core status, subtask appears in Working Today
                } else {
                    $updateData['core_status'] = CoreStatus::TO_DO_TODAY;
                    if (! $order->origin_core_status && $previousStatus !== CoreStatus::TO_DO_TODAY) {
                        $updateData['origin_core_status'] = $previousStatus;
                        $updateData['origin_substatus'] = $order->substatus;
                    }
                    if ($category->triggerSubstatus()) {
                        $updateData['substatus'] = $category->triggerSubstatus();
                    } elseif ($previousStatus === CoreStatus::ENVIADO_A_CAMILA) {
                        $updateData['substatus'] = Substatus::CAMBIOS_CAMILA;
                    } elseif ($previousStatus === CoreStatus::ENVIADO_AL_CLIENTE) {
                        $updateData['substatus'] = Substatus::CAMBIOS_CLIENTE;
                    }
                }
            }

            if (! $order->designer_id && $order->designers->isEmpty() && $assigneeId) {
                $updateData['designer_id'] = $assigneeId;
            }

            $order->update($updateData);

            if ($isWorkTask && $scheduledDate->isToday() && $previousStatus !== CoreStatus::TO_DO_TODAY && $order->core_status === CoreStatus::TO_DO_TODAY) {
                app(AutomationEngine::class)->handleStatusChanged(
                    $order->fresh(),
                    $previousStatus,
                    CoreStatus::TO_DO_TODAY,
                    auth()->user()?->name ?? __('Diseñador')
                );
                $order->refresh();
            } elseif ($isWorkTask && $previousStatus === CoreStatus::ENVIADO_AL_CLIENTE) {
                app(AutomationEngine::class)->handleClientResponse($order);
                $order->refresh();
            }

            $order->refresh();

            // Log OrderEvent for timeline
            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'SUBTASK_SCHEDULED',
                'actor' => auth()->user()?->name ?? __('Diseñador'),
                'new_value' => $taskTitle,
                'metadata' => [
                    'task_id' => $subtask->id,
                    'task_title' => $taskTitle,
                    'category' => $category->value,
                    'date' => $scheduledDate->toDateString(),
                    'is_work_task' => $isWorkTask,
                ],
            ]);

            if (! $order->isSlaExempt() && $order->current_due_date && $scheduledDate->isAfter($order->current_due_date)) {
                $daysOverdue = (int) $order->current_due_date->diffInDays($scheduledDate);
                $this->slaWarningDetails = [
                    'company_name' => $order->company_name,
                    'task_name' => $taskTitle,
                    'scheduled_date' => $scheduledDate->format('d M, Y'),
                    'current_due_date' => $order->current_due_date->format('d M, Y'),
                    'days_overdue' => max(1, $daysOverdue),
                ];
                $this->showSlaWarningModal = true;
                session()->flash('warning', __('Atención: Subtarea ":title" programada para :company el :date supera la fecha límite del SLA (:due).', [
                    'title' => $taskTitle,
                    'company' => $order->company_name,
                    'date' => $scheduledDate->format('d M'),
                    'due' => $order->current_due_date->format('d M'),
                ]));
            } else {
                session()->flash('message', __('Subtarea ":title" programada para :company el :date.', [
                    'title' => $taskTitle,
                    'company' => $order->company_name,
                    'date' => $scheduledDate->format('d M'),
                ]));
            }
        } else {
            // Note type subtask (no order attached)
            $taskTitle = trim($title) ?: __('Nota sin nombre');
            $preset = SubtaskPreset::where('title', $taskTitle)->first();
            $category = $preset?->category ?? SubtaskCategory::MANAGEMENT;
            $isWorkTask = $preset ? (bool) $preset->is_work_task : (bool) $isWorkTask;

            $nextSortOrder = $this->getNextSortOrderForDay($scheduledDate, $designerId);

            $subtask = RelatedTask::create([
                'order_id' => null,
                'title' => $taskTitle,
                'type' => RelatedTaskType::SUBTASK->value,
                'category' => $category,
                'scheduled_date' => $scheduledDate->toDateString(),
                'assignee_id' => $designerId ?: null,
                'status' => 'todo',
                'priority' => 'normal',
                'is_work_task' => $isWorkTask,
                'sort_order' => $nextSortOrder,
            ]);

            session()->flash('message', __('Nota ":title" creada para el :date.', [
                'title' => $taskTitle,
                'date' => $scheduledDate->format('d M'),
            ]));
        }

        $this->changePlannerSortBy('custom');

        $this->dispatch('order-updated');
    }

    public function rescheduleSubtask($taskId, $dateString)
    {
        $subtask = RelatedTask::with('order')->findOrFail($taskId);
        $scheduledDate = Carbon::parse($dateString);

        $nextSortOrder = $this->getNextSortOrderForDay($scheduledDate, $subtask->assignee_id, (int) $taskId);

        $subtask->update([
            'scheduled_date' => $scheduledDate->toDateString(),
            'sort_order' => $nextSortOrder,
        ]);

        if ($subtask->is_work_task && $scheduledDate->isToday() && $subtask->order) {
            $order = $subtask->order;
            $updateData = ['in_workspace' => true, 'scheduled_date' => $scheduledDate->toDateString()];

            if ($order->core_status === CoreStatus::ARCHIVED) {
                $updateData['core_status'] = CoreStatus::TO_DO_TODAY;
                $updateData['substatus'] = Substatus::TICKET;
                $updateData['archived_at'] = null;
            } elseif ($order->core_status !== CoreStatus::EN_PRODUCCION) {
                $previousStatus = $order->core_status;
                $updateData['core_status'] = CoreStatus::TO_DO_TODAY;
                if (! $order->origin_core_status && $previousStatus !== CoreStatus::TO_DO_TODAY) {
                    $updateData['origin_core_status'] = $previousStatus;
                    $updateData['origin_substatus'] = $order->substatus;
                }
                $category = $subtask->category;
                if ($category->triggerSubstatus()) {
                    $updateData['substatus'] = $category->triggerSubstatus();
                } elseif ($previousStatus === CoreStatus::ENVIADO_A_CAMILA) {
                    $updateData['substatus'] = Substatus::CAMBIOS_CAMILA;
                } elseif ($previousStatus === CoreStatus::ENVIADO_AL_CLIENTE) {
                    $updateData['substatus'] = Substatus::CAMBIOS_CLIENTE;
                }
            }

            $order->update($updateData);

            if (isset($previousStatus) && $previousStatus !== CoreStatus::TO_DO_TODAY && $order->core_status === CoreStatus::TO_DO_TODAY) {
                app(AutomationEngine::class)->handleStatusChanged(
                    $order->fresh(),
                    $previousStatus,
                    CoreStatus::TO_DO_TODAY,
                    auth()->user()?->name ?? __('Diseñador')
                );
                $order->refresh();
                $subtask->refresh();
            }
        }

        if (! $subtask->isDone() && $subtask->order && ! $subtask->order->isSlaExempt() && ! $subtask->isFollowUp() && $subtask->order->current_due_date && $scheduledDate->isAfter($subtask->order->current_due_date)) {
            $daysOverdue = (int) $subtask->order->current_due_date->diffInDays($scheduledDate);
            $this->slaWarningDetails = [
                'company_name' => $subtask->order->company_name,
                'task_name' => $subtask->title,
                'scheduled_date' => $scheduledDate->format('d M, Y'),
                'current_due_date' => $subtask->order->current_due_date->format('d M, Y'),
                'days_overdue' => max(1, $daysOverdue),
            ];
            $this->showSlaWarningModal = true;
            session()->flash('warning', __('Atención: Subtarea ":title" reprogramada para el :date supera la fecha límite del SLA (:due).', [
                'title' => $subtask->title,
                'date' => $scheduledDate->format('d M'),
                'due' => $subtask->order->current_due_date->format('d M'),
            ]));
        } else {
            session()->flash('message', __('Subtarea ":title" reprogramada para el :date.', ['title' => $subtask->title, 'date' => $scheduledDate->format('d M')]));
        }

        $this->changePlannerSortBy('custom');

        $this->dispatch('order-updated');
    }

    public function toggleSubtaskComplete($taskId)
    {
        $subtask = RelatedTask::with('order')->findOrFail($taskId);

        if ($subtask->isNote()) {
            session()->flash('warning', __('Para marcar como completada, es obligatorio vincular la nota a una tarjeta existente del workspace.'));
            $this->dispatch('open-link-note-modal', taskId: $subtask->id, noteTitle: $subtask->title);

            return;
        }

        $order = $subtask->order;
        $willBeDone = $subtask->status !== 'done';

        // Check if completing a "Poner en alta" subtask
        if ($order && $willBeDone && $subtask->isPonerEnAlta()) {
            if (! $order->approved) {
                $this->dispatch('open-order-detail', orderId: $order->id, openApproval: true, targetStatus: CoreStatus::EN_PRODUCCION->value);
                session()->flash('warning', __('La orden requiere aprobación antes de ser enviada a producción.'));

                return;
            }
        }

        $newStatus = $willBeDone ? 'done' : 'todo';

        $subtask->update([
            'status' => $newStatus,
            'completed_at' => $newStatus === 'done' ? now() : null,
        ]);

        if ($order && $newStatus === 'done') {
            OrderEvent::create([
                'order_id' => $order->id,
                'event_type' => 'SUBTASK_COMPLETED',
                'actor' => auth()->user()?->name ?? __('Diseñador'),
                'new_value' => $subtask->title,
                'metadata' => [
                    'task_id' => $subtask->id,
                    'task_title' => $subtask->title,
                    'date' => $subtask->scheduled_date?->toDateString(),
                ],
            ]);

            if ($subtask->isPonerEnAlta() && $order->approved && $order->core_status !== CoreStatus::EN_PRODUCCION) {
                $previousStatus = $order->core_status;
                app(AutomationEngine::class)->handleStatusChanged(
                    $order,
                    $previousStatus,
                    CoreStatus::EN_PRODUCCION,
                    auth()->user()?->name ?? __('Diseñador')
                );
                $order->update([
                    'substatus' => Substatus::ENVIADO_EN_ALTA,
                    'done_today' => true,
                    'origin_core_status' => null,
                    'origin_substatus' => null,
                ]);

                OrderEvent::create([
                    'order_id' => $order->id,
                    'event_type' => 'MOVED_TO_PRODUCTION',
                    'actor' => auth()->user()?->name ?? __('Diseñador'),
                    'previous_value' => $previousStatus?->value,
                    'new_value' => CoreStatus::EN_PRODUCCION->value,
                    'metadata' => [
                        'trigger' => 'Subtarea Poner en Alta completada en Weekly Planner',
                        'task_id' => $subtask->id,
                        'task_title' => $subtask->title,
                    ],
                ]);

                session()->flash('message', __('Subtarea completada y orden :company enviada a Producción.', ['company' => $order->company_name]));
            }
        }

        $this->dispatch('order-updated');
    }

    public function updateTaskCategory(int|string $taskId, string $categoryValue): void
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $cat = SubtaskCategory::tryFrom($categoryValue) ?? SubtaskCategory::NEW_DESIGN;
            $updates = [
                'category' => $cat,
                'return_core_status' => $cat->defaultReturnCoreStatus() ?? $task->return_core_status,
            ];
            if ($cat === SubtaskCategory::MANAGEMENT) {
                $updates['is_work_task'] = false;
            }
            $task->update($updates);
            $this->dispatch('order-updated');
            $this->dispatch('subtask-updated');
        }
    }

    public function updateTaskType(int|string $taskId, bool $isWork): void
    {
        $task = RelatedTask::find($taskId);
        if ($task) {
            $updates = ['is_work_task' => $isWork];
            if ($isWork && $task->category === SubtaskCategory::MANAGEMENT) {
                $updates['category'] = SubtaskCategory::NEW_DESIGN;
                $updates['return_core_status'] = SubtaskCategory::NEW_DESIGN->defaultReturnCoreStatus();
            }
            $task->update($updates);
            $this->dispatch('order-updated');
            $this->dispatch('subtask-updated');
        }
    }

    public function linkSubtaskToOrder($taskId, $orderId)
    {
        $subtask = RelatedTask::findOrFail($taskId);
        $order = Order::findOrFail($orderId);

        $cleanedTitle = RelatedTask::cleanTitleForOrder($subtask->title, $order);
        $assigneeId = $subtask->assignee_id ?: ($order->designer_id ?? $order->designers->first()?->id);

        $subtask->update([
            'order_id' => $order->id,
            'title' => $cleanedTitle,
            'assignee_id' => $assigneeId,
        ]);

        $order->update(['in_workspace' => true]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'SUBTASK_SCHEDULED',
            'actor' => auth()->user()?->name ?? __('Diseñador'),
            'new_value' => $cleanedTitle,
            'metadata' => [
                'task_id' => $subtask->id,
                'task_title' => $cleanedTitle,
                'date' => $subtask->scheduled_date?->toDateString(),
                'is_work_task' => $subtask->is_work_task,
                'linked_from_note' => true,
            ],
        ]);

        session()->flash('message', __('Nota vinculada a :company como subtarea ":title".', [
            'company' => $order->company_name,
            'title' => $cleanedTitle,
        ]));

        $this->dispatch('order-updated');
    }

    public function deleteSubtask($taskId)
    {
        $subtask = RelatedTask::findOrFail($taskId);
        $subtask->delete();
        $this->recentlyDeletedSubtaskIds[] = (int) $taskId;

        $this->dispatch('subtask-deleted', message: __('Subtarea eliminada'));
        $this->dispatch('order-updated');
    }

    public function undoDeleteSubtask()
    {
        if (empty($this->recentlyDeletedSubtaskIds)) {
            $lastTrashed = RelatedTask::onlyTrashed()->latest('deleted_at')->first();
            if ($lastTrashed) {
                $this->recentlyDeletedSubtaskIds[] = (int) $lastTrashed->id;
            }
        }

        if (! empty($this->recentlyDeletedSubtaskIds)) {
            $taskId = array_pop($this->recentlyDeletedSubtaskIds);
            $subtask = RelatedTask::onlyTrashed()->find($taskId);

            if ($subtask) {
                $subtask->restore();
                $this->dispatch('subtask-restored');
                $this->dispatch('order-updated');
            }
        }
    }

    public function unscheduleOrder($orderId)
    {
        $order = Order::findOrFail($orderId);
        $updateData = [
            'scheduled_date' => null,
        ];

        if ($order->core_status === CoreStatus::TO_DO_TODAY) {
            $updateData['core_status'] = $order->getDesignerOrdersReceivedStatus();
        }

        $order->update($updateData);
        session()->flash('message', __('Orden :company quitada de la agenda.', ['company' => $order->company_name]));
    }

    public function toggleDoneToday($orderId)
    {
        $order = Order::findOrFail($orderId);
        $order->update(['done_today' => ! $order->done_today]);
        $this->dispatch('order-updated');
    }

    public function render()
    {
        $startDate = Carbon::parse($this->selectedWeekStart);
        $days = [];

        for ($i = 0; $i < 5; $i++) {
            $dayDate = $startDate->copy()->addDays($i);
            $days[] = [
                'day_name' => match ($i) {
                    0 => __('Lunes'),
                    1 => __('Martes'),
                    2 => __('Miércoles'),
                    3 => __('Jueves'),
                    4 => __('Viernes'),
                },
                'is_next_week' => false,
                'date' => $dayDate,
                'date_string' => $dayDate->toDateString(),
            ];
        }

        $nextWeekMonday = $startDate->copy()->addWeek()->startOfWeek(Carbon::MONDAY);
        $nextWeekFriday = $nextWeekMonday->copy()->addDays(4);

        $nextWeekItem = [
            'day_name' => __('Próxima Semana'),
            'is_next_week' => true,
            'date' => $nextWeekMonday,
            'date_string' => $nextWeekMonday->toDateString(),
            'range_label' => $nextWeekMonday->format('d M').' - '.$nextWeekFriday->format('d M'),
        ];

        $designerQuery = Designer::where('active', true)->internal()->with(['orders' => fn ($q) => $q->inWorkspace()->prioritizeUrgente()->with(['clientLocation', 'designers', 'designer'])]);
        if ($this->selectedDesignerFilter !== 'all') {
            $designerQuery->where('id', $this->selectedDesignerFilter);
        }
        $designers = $designerQuery->get();

        $subtasks = RelatedTask::with(['order.clientLocation', 'order.designer', 'order.designers'])
            ->where(function ($q) {
                $q->whereNull('order_id')
                    ->orWhereHas('order', fn ($oq) => $oq->inWorkspace())
                    ->orWhere(function ($sq) {
                        $sq->whereHas('order', fn ($oq) => $oq->where('core_status', CoreStatus::ARCHIVED)->orWhereNotNull('archived_at'))
                            ->whereNull('trigger_type')
                            ->where(function ($tq) {
                                $tq->whereNull('type')
                                    ->orWhereNotIn('type', [
                                        RelatedTaskType::CORREO_ATRASO->value,
                                        RelatedTaskType::BIENVENIDA->value,
                                    ]);
                            });
                    });
            })
            ->where(function ($q) {
                $q->whereNotNull('scheduled_date')
                    ->orWhere(function ($sq) {
                        $sq->whereNull('scheduled_date')
                            ->whereNotNull('due_date');
                    });
            })
            ->get();

        foreach ($subtasks as $st) {
            if (! $st->scheduled_date && $st->due_date) {
                $st->scheduled_date = $st->due_date;
            }
        }

        $unscheduledOrders = Order::inWorkspace()
            ->prioritizeUrgente()
            ->whereNotIn('core_status', [CoreStatus::EN_PRODUCCION, CoreStatus::ON_HOLD])
            ->when($this->selectedDesignerFilter !== 'all', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('designer_id', $this->selectedDesignerFilter)
                        ->orWhereHas('designers', fn ($d) => $d->where('designers.id', $this->selectedDesignerFilter));
                });
            })
            ->when(! $this->unscheduledSearch, fn ($q) => $q->whereNull('scheduled_date'))
            ->when($this->unscheduledSearch, fn ($q) => $q->search($this->unscheduledSearch))
            ->orderByRaw('current_due_date IS NULL, current_due_date ASC')
            ->get();

        $workspaceOrders = Order::inWorkspace()
            ->prioritizeUrgente()
            ->when($this->selectedDesignerFilter !== 'all', function ($q) {
                $q->where(function ($sub) {
                    $sub->where('designer_id', $this->selectedDesignerFilter)
                        ->orWhereHas('designers', fn ($d) => $d->where('designers.id', $this->selectedDesignerFilter));
                });
            })
            ->orderBy('company_name')
            ->get();

        $allWorkspaceOrders = Order::inWorkspace()
            ->with(['clientLocation'])
            ->prioritizeUrgente()
            ->orderBy('company_name')
            ->get();

        $workspaceOrdersList = $allWorkspaceOrders->map(function ($o) {
            $loc = $o->location_text ?? '';
            $text = $o->company_name
                .($loc ? ' ('.$loc.')' : '')
                .($o->task_name ? ' - '.$o->task_name : '');

            return [
                'id' => (string) $o->id,
                'company' => $o->company_name,
                'location' => $loc,
                'task' => $o->task_name ?? '',
                'wo_number' => $o->wo_number ?? '',
                'trello_card_title' => $o->trello_title ?? $o->trello_card_title ?? '',
                'responsible_person' => $o->responsible_person ?? '',
                'text' => $text,
                'designer_id' => (string) ($o->designer_id ?? $o->designers->first()?->id ?? ''),
            ];
        })->values();

        $backlogOrders = collect();
        if (! empty($this->backlogSearch)) {
            $backlogOrders = Order::inBacklog()
                ->prioritizeUrgente()
                ->search($this->backlogSearch)
                ->with(['designer', 'designers'])
                ->limit(15)
                ->get();
        }

        $workspaceSearchResults = collect();
        if (! empty($this->unscheduledSearch)) {
            $workspaceSearchResults = Order::inWorkspace()
                ->prioritizeUrgente()
                ->search($this->unscheduledSearch)
                ->with(['designer', 'designers'])
                ->limit(15)
                ->get();
        }

        $slaBreachedList = collect();
        foreach ($subtasks as $st) {
            if ($st->isDone()) {
                continue;
            }
            if (! $this->showSystemTasks && ! $st->isWorkTask()) {
                continue;
            }
            if ($st->order && $st->order->current_due_date && $st->scheduled_date && ! $st->isFollowUp() && ! $st->order->isSlaExempt()) {
                if ($st->scheduled_date->gt($st->order->current_due_date) || $st->order->isOverdue()) {
                    $daysOverdue = (int) max(1, $st->order->current_due_date->diffInDays($st->scheduled_date));
                    $slaBreachedList->push([
                        'type' => 'subtask',
                        'company_name' => $st->order->company_name,
                        'task_name' => $st->title,
                        'scheduled_date' => $st->scheduled_date->format('d M, Y'),
                        'current_due_date' => $st->order->current_due_date->format('d M, Y'),
                        'days_overdue' => $daysOverdue,
                        'is_work_task' => (bool) $st->is_work_task,
                        'is_system_task' => $st->isSystemTask(),
                    ]);
                }
            }
        }

        foreach ($designers as $des) {
            foreach ($des->orders as $ord) {
                if ($ord->done_today) {
                    continue;
                }
                if ($ord->current_due_date && $ord->scheduled_date && ! $ord->isSlaExempt()) {
                    if ($ord->scheduled_date->gt($ord->current_due_date) || $ord->isOverdue()) {
                        $alreadyAdded = $slaBreachedList->contains(fn ($item) => $item['company_name'] === $ord->company_name && $item['task_name'] === ($ord->task_name ?? 'Orden Principal'));
                        if (! $alreadyAdded) {
                            $daysOverdue = (int) max(1, $ord->current_due_date->diffInDays($ord->scheduled_date));
                            $slaBreachedList->push([
                                'type' => 'order',
                                'company_name' => $ord->company_name,
                                'task_name' => $ord->task_name ?? __('Orden Principal'),
                                'scheduled_date' => $ord->scheduled_date->format('d M, Y'),
                                'current_due_date' => $ord->current_due_date->format('d M, Y'),
                                'days_overdue' => $daysOverdue,
                            ]);
                        }
                    }
                }
            }
        }

        $daysWithNextWeek = array_merge($days, [$nextWeekItem]);

        return view('livewire.planner.weekly-planner', [
            'days' => $daysWithNextWeek,
            'weekDaysOnly' => $days,
            'nextWeekItem' => $nextWeekItem,
            'designers' => $designers,
            'allDesigners' => Designer::where('active', true)->internal()->get(),
            'subtasks' => $subtasks,
            'unscheduledOrders' => $unscheduledOrders,
            'workspaceOrders' => $workspaceOrders,
            'workspaceOrdersList' => $workspaceOrdersList,
            'workspaceSearchResults' => $workspaceSearchResults,
            'backlogOrders' => $backlogOrders,
            'slaBreachedList' => $slaBreachedList,
            'subtaskPresets' => SubtaskPreset::where('is_active', true)->orderBy('sort_order')->get(),
        ])->layout('components.layouts.app', ['title' => __('Planificador Semanal - ').config('app.name')]);
    }

    public function openAllSlaWarningsModal()
    {
        $this->showSlaWarningModal = true;
    }
}
