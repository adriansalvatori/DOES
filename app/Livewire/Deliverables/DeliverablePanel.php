<?php

namespace App\Livewire\Deliverables;

use App\Application\Delivery\Actions\AddComment;
use App\Application\Delivery\Actions\AddTask;
use App\Application\Delivery\Actions\ApproveByReviewer;
use App\Application\Delivery\Actions\AttachUploadedFile;
use App\Application\Delivery\Actions\BlockDeliverable;
use App\Application\Delivery\Actions\ChangeDueTarget;
use App\Application\Delivery\Actions\ChangeReviewer;
use App\Application\Delivery\Actions\CloseDeliverable;
use App\Application\Delivery\Actions\CompleteTask;
use App\Application\Delivery\Actions\DuplicateDeliverable;
use App\Application\Delivery\Actions\LogReceipt;
use App\Application\Delivery\Actions\MarkProduced;
use App\Application\Delivery\Actions\MoveDeliverable;
use App\Application\Delivery\Actions\PlanDeliverable;
use App\Application\Delivery\Actions\PullToProduction;
use App\Application\Delivery\Actions\PutOnHold;
use App\Application\Delivery\Actions\RecordClientApproval;
use App\Application\Delivery\Actions\RecordClientChanges;
use App\Application\Delivery\Actions\ReopenDeliverable;
use App\Application\Delivery\Actions\ReopenTask;
use App\Application\Delivery\Actions\RequestChangesByReviewer;
use App\Application\Delivery\Actions\RequestProductionSample;
use App\Application\Delivery\Actions\ResolveBlock;
use App\Application\Delivery\Actions\Resume;
use App\Application\Delivery\Actions\SendBatchToClient;
use App\Application\Delivery\Actions\SetTaskPoints;
use App\Application\Delivery\Actions\StartFileUpload;
use App\Application\Delivery\Actions\StartProduction;
use App\Application\Delivery\Actions\TrashDeliverable;
use App\Application\Delivery\Actions\UpdateDeliverableDetails;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\Area;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Enums\Priority;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\DeliverableEvent;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Support\PointsEstimator;
use App\Domain\Delivery\Support\Roles;
use App\Enums\UserRole;
use App\Models\Media;
use App\Models\OrderComment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The slide-over that replaces the legacy order detail modal. Every change goes through an Action; the heavy
 * tabs are only queried while they are the active tab.
 */
class DeliverablePanel extends Component
{
    public const TABS = ['tasks', 'approval', 'comments', 'timeline'];

    public const SHEETS = ['block', 'hold', 'close', 'resolve', 'reopen', 'trash', 'plan', 'receipt', 'reviewerChanges', 'clientChanges', 'sample'];

    public bool $isOpen = false;

    public ?int $deliverableId = null;

    public string $tab = 'tasks';

    public ?string $sheet = null;

    public string $sheetReason = '';

    public string $sheetNote = '';

    public string $planAssigneeId = '';

    public string $planDay = '';

    public bool $planOverride = false;

    public string $receiptMethod = '';

    public ?string $noticeText = null;

    public string $noticeTone = 'ok';

    public bool $editing = false;

    /** @var array<string, mixed> */
    public array $details = [];

    /** @var array<string, mixed> */
    public array $edit = [];

    public string $newTaskTitle = '';

    public bool $newTaskIsGate = false;

    public string $approvalSource = 'client';

    public bool $measurementsConfirmed = false;

    public bool $estimateApproved = false;

    public string $approvalNote = '';

    public string $approvalEvidence = '';

    public string $newComment = '';

    #[On('open-deliverable')]
    public function open(int $deliverableId): void
    {
        $deliverable = Deliverable::query()->find($deliverableId);

        if ($deliverable === null || $this->isHiddenFromViewer($deliverable)) {
            $this->closePanel();

            return;
        }

        $this->resetPanelState();
        $this->deliverableId = $deliverableId;
        $this->isOpen = true;
    }

    public function closePanel(): void
    {
        $this->resetPanelState();
        $this->isOpen = false;
        $this->deliverableId = null;
    }

    public function showTab(string $tab): void
    {
        if (in_array($tab, self::TABS, true)) {
            $this->tab = $tab;
        }
    }

    public function openSheet(string $sheet): void
    {
        if (! in_array($sheet, self::SHEETS, true)) {
            return;
        }

        $this->resetValidation();
        $this->sheet = $sheet;
        $this->sheetReason = '';
        $this->sheetNote = '';
        $this->planAssigneeId = (string) ($this->deliverable?->assignee_id ?? '');
        $this->planDay = ($this->deliverable?->planned_for ?? now())->toDateString();
        $this->planOverride = false;
        $this->receiptMethod = $this->deliverable?->workOrder?->fulfillment_method?->value ?? '';
    }

    public function closeSheet(): void
    {
        $this->sheet = null;
        $this->resetValidation();
    }

    public function dismissNotice(): void
    {
        $this->noticeText = null;
    }

    #[Computed]
    public function deliverable(): ?Deliverable
    {
        if ($this->deliverableId === null) {
            return null;
        }

        return Deliverable::query()
            ->with(['workOrder.client', 'workOrder.clientLocation', 'phase', 'assignee', 'reviewer', 'openBlock.owner', 'openHold'])
            ->find($this->deliverableId);
    }

    /**
     * @return Collection<int, Task>
     */
    #[Computed]
    public function tasks(): Collection
    {
        return $this->deliverable->tasks()->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Approval>
     */
    #[Computed]
    public function standingApprovals(): Collection
    {
        return $this->deliverable->approvals()->standing()->with('approver')->latest('id')->get();
    }

    /**
     * @return Collection<int, OrderComment>
     */
    #[Computed]
    public function comments(): Collection
    {
        return OrderComment::query()->with('user')->where('order_id', $this->deliverableId)->latest('created_at')->limit(30)->get();
    }

    /**
     * @return Collection<int, Media>
     */
    #[Computed]
    public function files(): Collection
    {
        return Media::query()->where('order_id', $this->deliverableId)->latest('id')->limit(30)->get();
    }

    /**
     * @return Collection<int, DeliverableEvent>
     */
    #[Computed]
    public function events(): Collection
    {
        return $this->deliverable->events()->latest('id')->limit(60)->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function assignableUsers(): Collection
    {
        return User::query()
            ->designTeam()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Active reviewers and managers, for the reviewer field.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function reviewerOptions(): Collection
    {
        return User::query()
            ->where('active', true)
            ->where(fn ($query) => $query
                ->where('is_reviewer', true)
                ->orWhere('is_lead', true)
                ->orWhere(fn ($staff) => $staff->withAnyRole(UserRole::ADMIN, UserRole::COORDINATOR)))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function startEditing(): void
    {
        if (! $this->allows('updateDetails')) {
            return;
        }

        $this->details = $this->currentDetails();
        $this->edit = $this->details;
        $this->editing = true;
        $this->resetValidation();
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->resetValidation();
    }

    public function saveDetails(UpdateDeliverableDetails $update): void
    {
        $deliverable = $this->deliverable;
        $validPhaseIds = Phase::query()->where('work_order_id', $deliverable->work_order_id)->pluck('id')->all();

        $this->validate([
            'edit.name' => ['required', 'string', 'max:255'],
            'edit.size' => ['nullable', 'string', 'max:255'],
            'edit.material' => ['nullable', 'string', 'max:255'],
            'edit.quantity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'edit.files_url' => ['nullable', 'string', 'max:500'],
            'edit.trello_card_url' => ['nullable', 'string', 'max:500'],
            'edit.phase_id' => ['nullable', Rule::in($validPhaseIds)],
            'edit.flags' => ['array'],
            'edit.flags.*' => [Rule::enum(DeliverableFlag::class)],
        ], [
            'edit.name.required' => __('The deliverable name is required.'),
            'edit.quantity.integer' => __('The quantity must be a whole number.'),
            'edit.quantity.min' => __('The quantity must be at least 1.'),
            'edit.phase_id.in' => __('Choose a phase of this work order.'),
        ]);

        $flags = array_values(array_filter(
            DeliverableFlag::cases(),
            fn (DeliverableFlag $flag): bool => in_array($flag->value, $this->edit['flags'] ?? [], true),
        ));

        $changes = [
            'name' => (string) $this->edit['name'],
            'size' => $this->blankToNull($this->edit['size'] ?? null),
            'material' => $this->blankToNull($this->edit['material'] ?? null),
            'quantity' => ($this->edit['quantity'] ?? '') === '' || $this->edit['quantity'] === null ? null : (int) $this->edit['quantity'],
            'files_url' => $this->blankToNull($this->edit['files_url'] ?? null),
            'trello_card_url' => $this->blankToNull($this->edit['trello_card_url'] ?? null),
            'phase_id' => ($this->edit['phase_id'] ?? '') === '' || $this->edit['phase_id'] === null ? null : (int) $this->edit['phase_id'],
            'flags' => $flags,
        ];

        $saved = $this->attempt(function () use ($update, $deliverable, $changes): void {
            ($update)($deliverable, $changes, auth()->user());
        }, __('Details saved.'));

        if ($saved) {
            $this->editing = false;
        }
    }

    /**
     * Hands the review to another reviewer (empty clears it). The field asks for confirmation before calling this.
     */
    public function changeReviewer(?int $reviewerId): void
    {
        $deliverable = $this->deliverable;
        $reviewer = $reviewerId !== null ? User::query()->find($reviewerId) : null;

        if ($reviewerId !== null && $reviewer === null) {
            return;
        }

        $this->attempt(fn () => app(ChangeReviewer::class)($deliverable, $reviewer, auth()->user()), __('Reviewer changed.'));
    }

    /**
     * Moves the due target (an empty reason is fine). The field asks for confirmation before calling this.
     */
    /**
     * Sets the priority. The field asks for confirmation before calling this.
     */
    public function changePriority(?string $priority): void
    {
        $deliverable = $this->deliverable;
        $value = Priority::tryFrom((string) $priority);

        if ($deliverable === null || $value === null) {
            return;
        }

        $this->attempt(fn () => app(UpdateDeliverableDetails::class)($deliverable, ['priority' => $value], auth()->user()), __('Priority changed.'));
    }

    /**
     * Sets a task's Fibonacci points (empty clears them); the deliverable's points follow as the sum. The field asks
     * for confirmation before calling this.
     */
    public function changeTaskPoints(int $taskId, ?string $points): void
    {
        $task = Task::query()->where('order_id', $this->deliverableId)->find($taskId);
        $value = $points === null || $points === '' ? null : (int) $points;

        if ($task === null || ($value !== null && ! in_array($value, PointsEstimator::TASK_SCALE, true))) {
            return;
        }

        $this->attempt(fn () => app(SetTaskPoints::class)($task, $value, auth()->user()), __('Points changed.'));
    }

    public function changeDueTarget(string $date, string $reason = ''): void
    {
        $validator = validator(['date' => $date, 'reason' => $reason], ['date' => ['required', 'date_format:Y-m-d'], 'reason' => ['nullable', 'string', 'max:255']], [
            'date.required' => __('Choose the new due date.'),
            'date.date_format' => __('Choose the new due date.'),
        ]);

        if ($validator->fails()) {
            $this->notify($validator->errors()->first(), 'danger');

            return;
        }

        $deliverable = $this->deliverable;
        $day = CarbonImmutable::parse($date);
        $note = $this->blankToNull($reason);

        $this->attempt(fn () => app(ChangeDueTarget::class)($deliverable, $day, $note, auth()->user()), __('Due date changed.'));
    }

    public function addComment(): void
    {
        $deliverable = $this->deliverable;
        $body = $this->newComment;

        $added = $this->attempt(fn () => app(AddComment::class)($deliverable, $body, auth()->user()), __('Comment posted.'), announce: false);

        if ($added) {
            $this->reset('newComment');
        }
    }

    /**
     * Opens a Google Drive upload session for the browser. Null (and a notice) when it cannot start.
     *
     * @return array{url: string}|null
     */
    public function prepareUpload(string $name, string $mimeType, int $size): ?array
    {
        $deliverable = $this->deliverable;
        $url = null;
        $origin = request()->headers->get('Origin') ?? request()->getSchemeAndHttpHost();

        $this->attempt(function () use ($deliverable, $name, $mimeType, $size, $origin, &$url): void {
            $url = app(StartFileUpload::class)($deliverable, $name, $mimeType, $size, auth()->user(), $origin);
        }, null, announce: false);

        return $url === null ? null : ['url' => $url];
    }

    /**
     * Records a file the browser finished uploading to Drive.
     */
    public function finishUpload(string $driveFileId): void
    {
        $deliverable = $this->deliverable;

        $this->attempt(fn () => app(AttachUploadedFile::class)($deliverable, $driveFileId, auth()->user()), __('File attached.'), announce: false);
    }

    public function runPrimary(): void
    {
        $deliverable = $this->deliverable;
        $action = $this->primaryAction($deliverable);

        if ($action === null) {
            return;
        }

        $actor = auth()->user();

        match ($action['key']) {
            'move' => $this->attempt(fn () => app(MoveDeliverable::class)($deliverable, $deliverable->status->next(), $actor), $action['label']),
            'pull' => $this->attempt(fn () => app(PullToProduction::class)($deliverable, $actor), $action['label']),
            'start' => $this->attempt(fn () => app(StartProduction::class)($deliverable, $actor), $action['label']),
            'produced' => $this->attempt(fn () => app(MarkProduced::class)($deliverable, $actor), $action['label']),
            'approve' => $this->approveByReviewer(),
            'sendToClient' => $this->attempt(fn () => app(SendBatchToClient::class)($deliverable, $actor), __('Sent to the client.')),
            'recordApproval' => $this->showTab('approval'),
            'receipt' => $this->openSheet('receipt'),
        };
    }

    public function approveByReviewer(): void
    {
        $deliverable = $this->deliverable;

        $this->attempt(fn () => app(ApproveByReviewer::class)($deliverable, null, auth()->user()), __('Approved by the reviewer.'));
    }

    public function submitReviewerChanges(): void
    {
        $this->validate(['sheetNote' => ['required', 'string', 'max:1000']], ['sheetNote.required' => __('Say what needs to change.')]);
        $deliverable = $this->deliverable;
        $note = trim($this->sheetNote);

        $this->finishSheet($this->attempt(fn () => app(RequestChangesByReviewer::class)($deliverable, $note, auth()->user()), __('Sent back to design.')));
    }

    public function submitClientChanges(): void
    {
        $this->validate(['sheetNote' => ['required', 'string', 'max:1000']], ['sheetNote.required' => __('Say what the client asked to change.')]);
        $deliverable = $this->deliverable;
        $note = trim($this->sheetNote);

        $this->finishSheet($this->attempt(fn () => app(RecordClientChanges::class)($deliverable, $note, auth()->user()), __('Client changes recorded; back in design.')));
    }

    public function submitClientApproval(): void
    {
        $this->validate([
            'approvalSource' => ['required', Rule::in([ApprovalSource::CLIENT->value, ApprovalSource::MANAGER->value])],
            'approvalNote' => ['nullable', 'string', 'max:1000'],
            'approvalEvidence' => ['nullable', 'string', 'max:500'],
        ], [
            'approvalSource.in' => __('Choose how the client answered.'),
        ]);

        $deliverable = $this->deliverable;
        $source = ApprovalSource::from($this->approvalSource);
        $measurements = $this->measurementsConfirmed;
        $estimate = $this->estimateApproved;
        $note = $this->blankToNull($this->approvalNote);
        $evidence = $this->blankToNull($this->approvalEvidence);

        $recorded = $this->attempt(function () use ($deliverable, $source, $measurements, $estimate, $note, $evidence): void {
            app(RecordClientApproval::class)($deliverable, $source, $measurements, $estimate, $note, $evidence, auth()->user());
        }, $measurements && $estimate ? __('Client approval recorded.') : __('Client approval recorded; the missing items block this deliverable.'));

        if ($recorded) {
            $this->reset('measurementsConfirmed', 'estimateApproved', 'approvalNote', 'approvalEvidence');
        }
    }

    public function toggleTask(int $taskId): void
    {
        $task = Task::query()->where('order_id', $this->deliverableId)->find($taskId);

        if ($task === null) {
            return;
        }

        $before = $this->deliverable->status;
        $actor = auth()->user();

        $this->attempt(function () use ($task, $actor): void {
            $task->isDone() ? app(ReopenTask::class)($task, $actor) : app(CompleteTask::class)($task, $actor);
        }, null);

        $after = $this->deliverable?->status;
        if ($after !== null && $after !== $before) {
            $this->notify(__('Moved to :status.', ['status' => $after->label()]), 'ok');
        }
    }

    public function addTask(): void
    {
        $this->validate(['newTaskTitle' => ['required', 'string', 'max:255']], ['newTaskTitle.required' => __('The task needs a title.')]);

        $deliverable = $this->deliverable;
        $next = $deliverable->status->next();
        $isGate = $this->newTaskIsGate && $next !== null;
        $title = $this->newTaskTitle;

        $added = $this->attempt(function () use ($deliverable, $title, $isGate, $next): void {
            app(AddTask::class)(
                $deliverable,
                $title,
                $isGate ? TaskKind::GATE_CUSTOM : TaskKind::WORK,
                $isGate ? $next : null,
                null,
                auth()->user(),
            );
        }, __('Task added.'));

        if ($added) {
            $this->reset('newTaskTitle', 'newTaskIsGate');
        }
    }

    public function submitBlock(): void
    {
        $this->validate([
            'sheetReason' => ['required', Rule::enum(BlockReason::class)],
            'sheetNote' => [Rule::requiredIf($this->sheetReason === BlockReason::OTHER->value), 'nullable', 'string', 'max:1000'],
        ], [
            'sheetReason.required' => __('Choose a reason.'),
            'sheetNote.required' => __('Add a note for this reason.'),
        ]);

        $deliverable = $this->deliverable;
        $reason = BlockReason::from($this->sheetReason);
        $note = $this->blankToNull($this->sheetNote);

        $this->finishSheet($this->attempt(fn () => app(BlockDeliverable::class)($deliverable, $reason, $note, auth()->user()), __('Blocked.')));
    }

    public function submitSample(): void
    {
        $this->validate(['sheetNote' => ['nullable', 'string', 'max:1000']]);

        $deliverable = $this->deliverable;
        $note = $this->blankToNull($this->sheetNote);

        $this->finishSheet($this->attempt(fn () => app(RequestProductionSample::class)($deliverable, $note, auth()->user()), __('Production sample requested. It starts in Production files.')));
    }

    /**
     * Sets the points of a deliverable that has no tasks (empty clears them). With tasks, its points are their sum.
     * The field asks for confirmation before calling this.
     */
    public function changePoints(?string $points): void
    {
        $deliverable = $this->deliverable;
        $value = $points === null || $points === '' ? null : (int) $points;

        if ($deliverable === null || ($value !== null && ! in_array($value, PointsEstimator::SCALE, true))) {
            return;
        }

        $this->attempt(fn () => app(UpdateDeliverableDetails::class)($deliverable, ['points' => $value], auth()->user()), __('Points changed.'));
    }

    public function submitHold(): void
    {
        $this->validate(['sheetReason' => ['required', Rule::enum(HoldReason::class)], 'sheetNote' => ['nullable', 'string', 'max:1000']], [
            'sheetReason.required' => __('Choose a reason.'),
        ]);

        $deliverable = $this->deliverable;
        $reason = HoldReason::from($this->sheetReason);
        $note = $this->blankToNull($this->sheetNote);

        $this->finishSheet($this->attempt(fn () => app(PutOnHold::class)($deliverable, $reason, $note, auth()->user()), __('Put on hold.')));
    }

    public function submitResolve(): void
    {
        $this->validate(['sheetNote' => ['nullable', 'string', 'max:1000']]);

        $deliverable = $this->deliverable;
        $note = $this->blankToNull($this->sheetNote);

        $this->finishSheet($this->attempt(function () use ($deliverable, $note): void {
            foreach ($deliverable->blocks()->whereNull('resolved_at')->get() as $block) {
                app(ResolveBlock::class)($block, $note, auth()->user());
            }
        }, __('Block resolved.')));
    }

    public function resume(): void
    {
        $deliverable = $this->deliverable;

        $this->attempt(fn () => app(Resume::class)($deliverable, auth()->user()), __('Resumed.'));
    }

    public function submitClose(): void
    {
        $this->validate(['sheetReason' => ['required', Rule::enum(CloseReason::class)->only(CloseReason::selectable())], 'sheetNote' => ['nullable', 'string', 'max:1000']], [
            'sheetReason.required' => __('Choose a reason.'),
        ]);

        $deliverable = $this->deliverable;
        $reason = CloseReason::from($this->sheetReason);
        $note = $this->blankToNull($this->sheetNote);

        $this->finishSheet($this->attempt(fn () => app(CloseDeliverable::class)($deliverable, $reason, $note, auth()->user()), __('Closed.')));
    }

    public function submitReopen(): void
    {
        $this->validate(['sheetNote' => ['nullable', 'string', 'max:1000']]);

        $deliverable = $this->deliverable;
        $note = $this->blankToNull($this->sheetNote);

        $this->finishSheet($this->attempt(fn () => app(ReopenDeliverable::class)($deliverable, $note, auth()->user()), __('Reopened.')));
    }

    public function submitTrash(): void
    {
        $deliverable = $this->deliverable;

        $trashed = $this->attempt(fn () => app(TrashDeliverable::class)($deliverable, auth()->user()), __('Moved to the trash.'));

        if ($trashed) {
            $this->closePanel();
        }
    }

    public function duplicate(): void
    {
        $deliverable = $this->deliverable;
        $copy = null;

        $duplicated = $this->attempt(function () use ($deliverable, &$copy): void {
            $copy = app(DuplicateDeliverable::class)($deliverable, auth()->user());
        }, __('Duplicated; showing the copy.'));

        if ($duplicated && $copy !== null) {
            $message = $this->noticeText;
            $this->resetPanelState();
            $this->deliverableId = $copy->getKey();
            $this->notify($message, 'ok');
        }
    }

    public function submitPlan(): void
    {
        $this->validate([
            'planAssigneeId' => ['required', Rule::in($this->assignableUsers->pluck('id')->map(fn (int $id): string => (string) $id)->all())],
            'planDay' => ['required', 'date'],
        ], [
            'planAssigneeId.required' => __('Choose who will do it.'),
            'planAssigneeId.in' => __('Choose who will do it.'),
            'planDay.required' => __('Choose the day.'),
            'planDay.date' => __('Choose the day.'),
        ]);

        $deliverable = $this->deliverable;
        $assignee = User::query()->findOrFail((int) $this->planAssigneeId);
        $day = CarbonImmutable::parse($this->planDay);
        $override = $this->planOverride && Roles::isManager(auth()->user());

        $this->finishSheet($this->attempt(fn () => app(PlanDeliverable::class)($deliverable, $assignee, $day, auth()->user(), $override), __('Planned.')));
    }

    public function submitReceipt(): void
    {
        $this->validate(['receiptMethod' => ['required', Rule::enum(FulfillmentMethod::class)], 'sheetNote' => ['nullable', 'string', 'max:1000']], [
            'receiptMethod.required' => __('Choose how it was handed over.'),
        ]);

        $deliverable = $this->deliverable;
        $method = FulfillmentMethod::from($this->receiptMethod);
        $note = $this->blankToNull($this->sheetNote);

        $this->finishSheet($this->attempt(fn () => app(LogReceipt::class)($deliverable, $method, auth()->user(), null, $note), __('Receipt logged; the deliverable is done.')));
    }

    public function render()
    {
        $deliverable = $this->isOpen ? $this->deliverable : null;

        if ($this->isOpen && ($deliverable === null || $this->isHiddenFromViewer($deliverable))) {
            $this->closePanel();
            $deliverable = null;
        }

        if ($deliverable === null) {
            return view('livewire.deliverables.deliverable-panel', ['deliverable' => null]);
        }

        $abilities = collect(['updateDetails', 'move', 'block', 'resolveBlock', 'putOnHold', 'resume', 'close', 'reopen', 'duplicate', 'trash', 'plan', 'approveByReviewer', 'requestChangesByReviewer', 'recordClientApproval', 'recordClientChanges', 'manageTasks', 'logReceipt', 'changeReviewer', 'changeDueTarget', 'addComment', 'attachFile', 'requestProductionSample'])
            ->mapWithKeys(fn (string $ability): array => [$ability => $this->allows($ability)]);

        $workOrder = $deliverable->workOrder;

        return view('livewire.deliverables.deliverable-panel', [
            'deliverable' => $deliverable,
            'can' => $abilities,
            'primary' => $this->primaryAction($deliverable),
            'stepper' => $this->stepper($deliverable->status),
            'workOrder' => $workOrder,
            'phases' => $this->editing && $workOrder !== null ? Phase::query()->where('work_order_id', $workOrder->getKey())->orderBy('position')->get(['id', 'name']) : collect(),
            'blockReasons' => BlockReason::cases(),
            'holdReasons' => HoldReason::cases(),
            'closeReasons' => CloseReason::selectable(),
            'fulfillmentMethods' => FulfillmentMethod::cases(),
            'flags' => DeliverableFlag::cases(),
            'fieldLabels' => $this->fieldLabels(),
            'phaseNames' => $this->editing && $workOrder !== null ? Phase::query()->where('work_order_id', $workOrder->getKey())->pluck('name', 'id')->all() : [],
            'flagLabels' => collect(DeliverableFlag::cases())->mapWithKeys(fn (DeliverableFlag $flag): array => [$flag->value => $flag->label()])->all(),
        ]);
    }

    /**
     * The next sensible move for this deliverable and viewer, decided on the server every time it is shown or run.
     *
     * @return array{key: string, label: string}|null
     */
    public function primaryAction(?Deliverable $deliverable): ?array
    {
        if ($deliverable === null || $deliverable->status->isClosed() || $deliverable->openBlock !== null || $deliverable->openHold !== null) {
            return null;
        }

        $user = auth()->user();
        $status = $deliverable->status;
        $next = $status->next();

        return match ($status) {
            DeliverableStatus::INTERNAL_REVIEW => $this->reviewAction($deliverable),
            DeliverableStatus::CLIENT_REVIEW => $user?->can('recordClientApproval', $deliverable)
                ? ['key' => 'recordApproval', 'label' => __('Record client approval')]
                : null,
            DeliverableStatus::READY_FOR_PRODUCTION => $user?->can('moveInProduction', $deliverable)
                ? ['key' => 'pull', 'label' => __('Pull to production')]
                : null,
            DeliverableStatus::PRODUCTION_QUEUE => $user?->can('moveInProduction', $deliverable)
                ? ['key' => 'start', 'label' => __('Start production')]
                : null,
            DeliverableStatus::IN_PRODUCTION => $user?->can('moveInProduction', $deliverable)
                ? ['key' => 'produced', 'label' => __('Mark as produced')]
                : null,
            DeliverableStatus::PRODUCED, DeliverableStatus::FULFILLMENT => $user?->can('logReceipt', $deliverable)
                ? ['key' => 'receipt', 'label' => __('Log receipt')]
                : null,
            default => $next !== null && $user?->can('move', $deliverable)
                ? ['key' => 'move', 'label' => __('Move to :status', ['status' => $next->label()])]
                : null,
        };
    }

    /**
     * @return array{key: string, label: string}|null
     */
    private function reviewAction(Deliverable $deliverable): ?array
    {
        $user = auth()->user();
        $approved = $deliverable->approvals()->standing()
            ->whereIn('source', [ApprovalSource::REVIEWER->value, ApprovalSource::MANAGER->value])
            ->exists();

        if ($approved) {
            return $user?->can('sendBatchToClient', $deliverable) ? ['key' => 'sendToClient', 'label' => __('Send to client')] : null;
        }

        return $user?->can('approveByReviewer', $deliverable) ? ['key' => 'approve', 'label' => __('Approve')] : null;
    }

    /**
     * The status bar: the statuses of each area with their position relative to the current one.
     *
     * @return list<array{area: string, steps: list<array{label: string, state: string}>}>
     */
    private function stepper(DeliverableStatus $current): array
    {
        return collect(Area::cases())->map(fn (Area $area): array => [
            'area' => $area->label(),
            'steps' => collect(DeliverableStatus::inArea($area))->map(fn (DeliverableStatus $status): array => [
                'label' => $status->label(),
                'state' => match (true) {
                    $status === $current => 'current',
                    $status->position() < $current->position() => 'past',
                    default => 'future',
                },
            ])->all(),
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    private function fieldLabels(): array
    {
        return [
            'name' => __('Name'),
            'size' => __('Size'),
            'material' => __('Material'),
            'quantity' => __('Quantity'),
            'files_url' => __('Files link'),
            'trello_card_url' => __('Trello card'),
            'phase_id' => __('Phase'),
            'flags' => __('Flags'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentDetails(): array
    {
        $deliverable = $this->deliverable;

        return [
            'name' => (string) $deliverable->task_name,
            'size' => (string) ($deliverable->size ?? ''),
            'material' => (string) ($deliverable->material ?? ''),
            'quantity' => $deliverable->quantity,
            'files_url' => (string) ($deliverable->files_url ?? ''),
            'trello_card_url' => (string) ($deliverable->trello_card_url ?? ''),
            'phase_id' => $deliverable->phase_id,
            'flags' => $deliverable->flag_codes?->map(fn (DeliverableFlag $flag): string => $flag->value)->values()->all() ?? [],
        ];
    }

    private function allows(string $ability): bool
    {
        $deliverable = $this->deliverable;
        $user = auth()->user();

        return $deliverable !== null && $user !== null && $user->can($ability, $deliverable);
    }

    /**
     * Plain designers never see blocked work.
     */
    private function isHiddenFromViewer(Deliverable $deliverable): bool
    {
        $user = auth()->user();

        if ($user === null || ! $user->isDesigner()) {
            return false;
        }

        if (Roles::isManager($user) || Roles::isReviewer($user)) {
            return false;
        }

        return $deliverable->blocks()->whereNull('resolved_at')->exists();
    }

    /**
     * Runs an action, shows its outcome as a notice and tells the rest of the page when something changed.
     */
    private function attempt(Closure $work, ?string $success, bool $announce = true): bool
    {
        try {
            $work();
        } catch (WorkflowException $exception) {
            $this->notify($exception->getMessage(), 'danger');

            return false;
        } catch (AuthorizationException) {
            $this->notify(__('You are not allowed to do that.'), 'danger');

            return false;
        }

        unset($this->deliverable, $this->tasks, $this->standingApprovals, $this->events, $this->comments, $this->files);

        if ($success !== null) {
            $this->notify($success, 'ok');
        }

        if ($announce) {
            $this->dispatch('deliverable-changed', deliverableId: $this->deliverableId);
        }

        return true;
    }

    private function finishSheet(bool $done): void
    {
        if ($done) {
            $this->sheet = null;
            $this->sheetReason = '';
            $this->sheetNote = '';
        }
    }

    private function notify(?string $text, string $tone): void
    {
        $this->noticeText = $text;
        $this->noticeTone = $tone;
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function resetPanelState(): void
    {
        $this->reset([
            'tab', 'sheet', 'sheetReason', 'sheetNote', 'planAssigneeId', 'planDay', 'planOverride', 'receiptMethod',
            'noticeText', 'noticeTone', 'editing', 'details', 'edit', 'newTaskTitle', 'newTaskIsGate', 'approvalSource',
            'measurementsConfirmed', 'estimateApproved', 'approvalNote', 'approvalEvidence', 'newComment',
        ]);
        $this->resetValidation();
        unset($this->deliverable, $this->tasks, $this->standingApprovals, $this->events, $this->comments, $this->files);
    }
}
