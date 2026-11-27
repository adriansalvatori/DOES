<?php

namespace App\Infrastructure\Backfill;

use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Support\LastActivity;
use App\Enums\UserRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Copies the legacy order model (orders, related_tasks, designers, order_elements) into the new domain tables.
 *
 * Every step only fills new columns and tables, is idempotent (rows that already carry the new value are skipped)
 * and runs in its own transaction. Legacy columns are never modified except the link columns documented below
 * (designers.user_id and designers.moved_to_contact_id).
 */
class LegacyBackfill
{
    private const PLACEHOLDER_WO_NUMBERS = ['', 'WO 00000'];

    /**
     * Open work is due, and its work order promised, at least this many days after its last activity.
     */
    public const DAYS_AFTER_LAST_ACTIVITY = 3;

    /** @var array<string, mixed> */
    private array $report = [];

    /** @var array<int, int> Legacy designer id => user id. */
    private array $userByDesigner = [];

    /** @var array<string, int> Normalized legacy queue status => user id of the designer who owns that queue. */
    private array $userByQueueStatus = [];

    private ?int $reviewerUserId = null;

    private ?int $managerUserId = null;

    private ?int $customerServiceUserId = null;

    /**
     * @return array<string, mixed>
     */
    public function run(bool $dryRun = false): array
    {
        $this->report = [
            'dry_run' => $dryRun,
            'team' => [],
            'work_orders' => [],
            'deliverables' => [],
            'approvals' => [],
            'tasks' => [],
            'elements' => [],
            'due_dates_rebased' => 0,
            'promised_dates_rebased' => 0,
            'unmapped' => [],
        ];

        DB::beginTransaction();

        try {
            $this->syncTeam();
            $this->buildWorkOrders();
            $this->mapDeliverables();
            $this->backfillApprovals();
            $this->mapTasks();
            $this->foldElements();
            $this->correctClosedAt();
            $this->fillWorkOrderCompanyNames();
            $this->rebaseDueDates();
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return $this->report;
    }

    /**
     * Step a: link real designers to users, flag lead and reviewer, move the other designers to contacts.
     */
    public function syncTeam(): void
    {
        DB::transaction(function (): void {
            $created = [];
            $linked = 0;
            $contactsCreated = 0;
            $contactsMerged = 0;
            $contactMap = [];

            $existingContacts = DB::table('contacts')->whereNull('deleted_at')->get(['id', 'name']);
            $contactIdsByName = [];
            foreach ($existingContacts as $contact) {
                $contactIdsByName[$this->normalizeName($contact->name)] ??= $contact->id;
            }

            foreach (DB::table('designers')->orderBy('id')->get() as $designer) {
                $isReal = $designer->queue_status_value !== null || $designer->is_lead || $designer->is_external;
                $userId = $designer->user_id && DB::table('users')->where('id', $designer->user_id)->exists()
                    ? (int) $designer->user_id
                    : null;

                if ($userId === null && $isReal) {
                    $userId = $this->createUserForDesigner($designer);
                    $created[] = ['designer_id' => $designer->id, 'name' => $designer->name, 'user_id' => $userId];
                }

                if ($userId !== null) {
                    DB::table('users')->where('id', $userId)->update([
                        'designer_id' => $designer->id,
                        'is_lead' => (bool) $designer->is_lead,
                    ]);
                    $this->userByDesigner[(int) $designer->id] = $userId;
                    $linked++;

                    if ($designer->queue_status_value !== null) {
                        $this->userByQueueStatus[$this->normalizeKey($designer->queue_status_value)] = $userId;
                    }

                    continue;
                }

                $key = $this->normalizeName($designer->name);
                if ($key === '') {
                    continue;
                }

                if ($designer->moved_to_contact_id !== null) {
                    $contactMap[$designer->id] = (int) $designer->moved_to_contact_id;

                    continue;
                }

                if (isset($contactIdsByName[$key])) {
                    $contactId = $contactIdsByName[$key];
                    $contactsMerged++;
                } else {
                    $contactId = DB::table('contacts')->insertGetId([
                        'name' => mb_strtoupper(trim($designer->name), 'UTF-8'),
                        'phone' => $designer->phone,
                        'preferred_channel' => 'any',
                        'preferred_language' => 'es',
                        'role_title' => 'Designer',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $contactIdsByName[$key] = $contactId;
                    $contactsCreated++;
                }

                DB::table('designers')->where('id', $designer->id)->update(['moved_to_contact_id' => $contactId]);
                $contactMap[$designer->id] = $contactId;
            }

            $this->flagReviewers();
            $this->resolveSupportUsers();

            $this->report['team'] = [
                'designers_linked_to_users' => $linked,
                'users_created' => $created,
                'designers_moved_to_contacts' => count($contactMap),
                'contacts_created' => $contactsCreated,
                'contacts_merged_by_name' => $contactsMerged,
                'designer_to_contact' => $contactMap,
                'reviewer_user_id' => $this->reviewerUserId,
                'customer_service_user_id' => $this->customerServiceUserId,
            ];
        });
    }

    /**
     * Step b: one work order per normalized WO number, one per card for cards without a number.
     */
    public function buildWorkOrders(): void
    {
        DB::transaction(function (): void {
            $cards = DB::table('orders')->whereNull('work_order_id')->orderBy('id')->get();
            $groups = [];
            $singles = [];

            foreach ($cards as $card) {
                $number = $this->normalizeWoNumber($card->wo_number);
                if ($number === null) {
                    $singles[] = [$card];
                } else {
                    $groups[$number][] = $card;
                }
            }

            $existingNumbers = DB::table('work_orders')->whereNotNull('number')->pluck('id', 'number')->all();
            $created = 0;
            $multiCard = 0;
            $disagreements = [];

            foreach ($groups as $number => $groupCards) {
                if (isset($existingNumbers[$number])) {
                    $this->assignCards($groupCards, (int) $existingNumbers[$number]);

                    continue;
                }

                $workOrderId = $this->createWorkOrder($groupCards, $number);
                $created++;

                if (count($groupCards) > 1) {
                    $multiCard++;
                    $disagreement = $this->recordInvoiceDisagreement($groupCards, $number);
                    if ($disagreement !== null) {
                        $disagreements[] = $disagreement;
                    }
                }

                $this->assignCards($groupCards, $workOrderId);
            }

            foreach ($singles as $groupCards) {
                $this->assignCards($groupCards, $this->createWorkOrder($groupCards, null));
                $created++;
            }

            $this->report['work_orders'] = [
                'created' => $created,
                'cards_assigned' => $cards->count(),
                'cards_without_number' => count($singles),
                'multi_card_groups' => $multiCard,
                'estimate_invoice_disagreements' => $disagreements,
            ];
        });
    }

    /**
     * Step c: status, flags, blocks, holds, close reason, assignee, reviewer and dates of every legacy card.
     */
    public function mapDeliverables(): void
    {
        DB::transaction(function (): void {
            $this->resolveSupportUsers();
            $this->loadQueueUsers();

            $matrix = [];
            $blocks = [];
            $holds = [];
            $mapped = 0;
            $today = Carbon::today()->toDateString();

            $query = DB::table('orders')->whereNull('status')->whereNull('legacy_element_id')->orderBy('id');

            foreach ($query->get() as $order) {
                $core = $this->normalizeKey($order->core_status);
                $sub = $this->normalizeKey($order->substatus);
                $status = $this->statusFor($core, $sub);

                if ($status === null) {
                    $this->report['unmapped'][] = ['order_id' => $order->id, 'core_status' => $order->core_status, 'substatus' => $order->substatus];

                    continue;
                }

                $isDone = $core === 'ARCHIVED';
                $hold = null;

                if ($core === 'ON HOLD') {
                    [$status, $hold] = $this->holdFor($sub);
                }

                $closeReason = $isDone ? $this->closeReasonFor($sub) : null;
                $flags = $this->flagsFor($order->flags, $sub);

                $assigneeId = ($order->designer_id !== null ? ($this->userByDesigner[(int) $order->designer_id] ?? null) : null)
                    ?? ($this->userByQueueStatus[$core] ?? null);

                $raisedAt = $order->last_meaningful_update ?? $order->updated_at ?? now()->toDateTimeString();

                if (! $isDone) {
                    foreach ($this->blockSpecsFor($order, $sub) as [$reason, $note]) {
                        $blocks[] = [
                            'deliverable_id' => $order->id,
                            'reason' => $reason->value,
                            'note' => $note,
                            'owner_id' => $this->customerServiceUserId,
                            'raised_at' => $raisedAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if ($hold !== null) {
                        $holds[] = [
                            'deliverable_id' => $order->id,
                            'reason' => $hold->value,
                            'note' => $order->pause_reason,
                            'started_at' => $raisedAt,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                $plannedFor = $status === DeliverableStatus::IN_DESIGN && $core === 'TO DO TODAY'
                    ? ($order->scheduled_date ? substr($order->scheduled_date, 0, 10) : $today)
                    : null;

                DB::table('orders')->where('id', $order->id)->update([
                    'status' => $status->value,
                    'assignee_id' => $assigneeId,
                    'reviewer_id' => $this->reviewerUserId,
                    'planned_for' => $plannedFor,
                    'due_target' => $hold === null ? $order->current_due_date : null,
                    'in_backlog' => ! $isDone && ! $order->in_workspace,
                    'trello_card_url' => $order->trello_card_id ? 'https://trello.com/c/'.$order->trello_card_id : null,
                    'flag_codes' => json_encode(array_map(fn (DeliverableFlag $flag): string => $flag->value, $flags)),
                    'close_reason' => $closeReason?->value,
                    'closed_at' => $isDone ? $this->legacyClosedAt($order) : null,
                ]);

                $key = trim(($order->core_status ?? '').' | '.($order->substatus ?? ''), ' |');
                $matrix[$key][$status->value] = ($matrix[$key][$status->value] ?? 0) + 1;
                $mapped++;
            }

            foreach (array_chunk($blocks, 200) as $chunk) {
                DB::table('blocks')->insert($chunk);
            }
            foreach (array_chunk($holds, 200) as $chunk) {
                DB::table('holds')->insert($chunk);
            }

            $this->report['deliverables'] = [
                'mapped' => $mapped,
                'blocks_created' => count($blocks),
                'blocks_by_reason' => $this->tally($blocks, 'reason'),
                'holds_created' => count($holds),
                'holds_by_reason' => $this->tally($holds, 'reason'),
                'legacy_to_new_status' => $matrix,
            ];
        });
    }

    /**
     * Step d: one approval row per legacy approved card.
     */
    public function backfillApprovals(): void
    {
        DB::transaction(function (): void {
            $this->resolveSupportUsers();

            $rows = [];
            $unknownSource = 0;

            $orders = DB::table('orders')
                ->where('approved', true)
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('approvals')->whereColumn('approvals.deliverable_id', 'orders.id'))
                ->orderBy('id')
                ->get();

            foreach ($orders as $order) {
                $source = $this->approvalSourceFor($order->approval_type);
                if ($source === null) {
                    $source = ApprovalSource::CLIENT;
                    $unknownSource++;
                }

                $rows[] = [
                    'deliverable_id' => $order->id,
                    'source' => $source->value,
                    'measurements_confirmed' => (bool) $order->measures_confirmed,
                    'estimate_approved' => (bool) $order->estimate_approved,
                    'note' => $order->approval_note,
                    'evidence_path' => $order->approval_image_path,
                    'approved_by' => $this->approverFor($source),
                    'approved_at' => $order->approved_at ?? $order->updated_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table('approvals')->insert($chunk);
            }

            $this->report['approvals'] = [
                'created' => count($rows),
                'by_source' => $this->tally($rows, 'source'),
                'defaulted_to_client_without_type' => $unknownSource,
            ];
        });
    }

    /**
     * Step e: kind, gate, system title key on every legacy task, plus an alert per open system reminder.
     */
    public function mapTasks(): void
    {
        DB::transaction(function (): void {
            $this->loadQueueUsers();

            $kinds = [];
            $alerts = 0;

            foreach (DB::table('related_tasks')->whereNull('kind')->orderBy('id')->get() as $task) {
                $type = $this->normalizeKey($task->type);
                $title = Str::lower(Str::ascii((string) $task->title));
                $kind = TaskKind::WORK;
                $advancesTo = null;
                $required = false;
                $titleKey = null;
                $alertKind = $this->systemAlertKindFor($type);

                if ($type === 'PONER EN ALTA') {
                    $kind = TaskKind::GATE_PREPARE_PRODUCTION_FILES;
                    $advancesTo = DeliverableStatus::READY_FOR_PRODUCTION;
                    $required = true;
                } elseif ($alertKind === null && preg_match('/^\s*enviar\s+proof\b.*\bclient/', $title) === 1) {
                    $kind = TaskKind::GATE_SEND_PROOF;
                    $advancesTo = DeliverableStatus::CLIENT_REVIEW;
                    $required = true;
                } elseif ($alertKind !== null && $alertKind !== AlertKind::REQUEST_INFO) {
                    // A system reminder gets its label in the reader's language; a request for information keeps its own text.
                    $titleKey = 'enums.alert_kind.'.$alertKind->value;
                }

                DB::table('related_tasks')->where('id', $task->id)->update([
                    'kind' => $kind->value,
                    'advances_to' => $advancesTo?->value,
                    'required' => $required,
                    'title_key' => $titleKey,
                ]);
                $kinds[$kind->value] = ($kinds[$kind->value] ?? 0) + 1;

                $isOpen = $task->status !== 'done' && $task->deleted_at === null && $task->order_id !== null;
                if ($alertKind !== null && $isOpen) {
                    $inserted = DB::table('alerts')->insertOrIgnore([
                        'deliverable_id' => $task->order_id,
                        'kind' => $alertKind->value,
                        'due_on' => $task->due_date ?? $task->scheduled_date,
                        'user_id' => $task->assignee_id !== null ? ($this->userByDesigner[(int) $task->assignee_id] ?? null) : null,
                        'legacy_task_id' => $task->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $alerts += $inserted;
                }
            }

            $this->report['tasks'] = ['by_kind' => $kinds, 'alerts_created' => $alerts];
        });
    }

    /**
     * Gives every backfilled done deliverable the time it really closed. Legacy archived_at and updated_at are often
     * the moment a data migration touched the card, so they only count when nothing better exists. Idempotent, and it
     * leaves alone deliverables closed through the new workflow (they have a deliverable_moved event into done).
     *
     * @return int Deliverables whose closed_at changed.
     */
    public function correctClosedAt(): int
    {
        return DB::transaction(function (): int {
            $changed = 0;

            $nativelyClosed = DB::table('order_events')
                ->where('event_type', 'deliverable_moved')
                ->where('new_value', DeliverableStatus::DONE->value)
                ->pluck('order_id')
                ->flip();

            DB::table('orders')
                ->where('status', DeliverableStatus::DONE->value)
                ->whereNull('legacy_element_id')
                ->orderBy('id')
                ->each(function (object $order) use (&$changed, $nativelyClosed): void {
                    if ($nativelyClosed->has($order->id)) {
                        return;
                    }

                    $closedAt = $this->legacyClosedAt($order);
                    if ($closedAt !== null && $closedAt !== $order->closed_at) {
                        DB::table('orders')->where('id', $order->id)->update(['closed_at' => $closedAt]);
                        $changed++;
                    }
                });

            $elements = DB::table('orders as element')
                ->join('order_elements', 'order_elements.id', '=', 'element.legacy_element_id')
                ->join('orders as parent', 'parent.id', '=', 'order_elements.order_id')
                ->where('element.status', DeliverableStatus::DONE->value)
                ->whereNotNull('parent.closed_at')
                ->whereColumn('element.closed_at', '!=', 'parent.closed_at')
                ->select('element.id', 'parent.closed_at')
                ->get();

            foreach ($elements as $element) {
                DB::table('orders')->where('id', $element->id)->update(['closed_at' => $element->closed_at]);
                $changed++;
            }

            $this->report['closed_at_corrected'] = $changed;

            return $changed;
        });
    }

    /**
     * Gives every work order the company it shows when no company record is linked: the linked company's name, else
     * the most recent company name typed on one of its deliverables. Idempotent.
     *
     * @return int Work orders whose company_name changed.
     */
    public function fillWorkOrderCompanyNames(): int
    {
        if (! Schema::hasColumn('work_orders', 'company_name')) {
            return 0;
        }

        return DB::transaction(function (): int {
            $changed = 0;

            DB::table('work_orders')
                ->join('clients', 'clients.id', '=', 'work_orders.client_id')
                ->whereRaw('(work_orders.company_name IS NULL OR work_orders.company_name != clients.name)')
                ->select('work_orders.id', 'clients.name')
                ->orderBy('work_orders.id')
                ->get()
                ->each(function (object $row) use (&$changed): void {
                    DB::table('work_orders')->where('id', $row->id)->update(['company_name' => $row->name]);
                    $changed++;
                });

            $unlinked = DB::table('work_orders')
                ->whereNull('client_id')
                ->where(fn ($query) => $query->whereNull('company_name')->orWhere('company_name', ''))
                ->pluck('id')
                ->flip();

            if ($unlinked->isNotEmpty()) {
                $seen = [];
                DB::table('orders')
                    ->whereNotNull('work_order_id')
                    ->whereNotNull('company_name')
                    ->where('company_name', '!=', '')
                    ->where('company_name', '!=', 'No client')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->select('work_order_id', 'company_name')
                    ->each(function (object $order) use ($unlinked, &$seen, &$changed): void {
                        if (! $unlinked->has($order->work_order_id) || isset($seen[$order->work_order_id])) {
                            return;
                        }

                        $seen[$order->work_order_id] = true;
                        DB::table('work_orders')->where('id', $order->work_order_id)->update(['company_name' => trim($order->company_name)]);
                        $changed++;
                    });
            }

            $this->report['work_order_company_names'] = $changed;

            return $changed;
        });
    }

    /**
     * When a legacy card closed: its last move to ARCHIVED, else the latest sign of activity (production processed,
     * sent to client, any legacy event), else archived_at, else updated_at.
     */
    private function legacyClosedAt(object $order): ?string
    {
        $archivedEvent = DB::table('order_events')
            ->where('order_id', $order->id)
            ->where('event_type', 'CORE_STATUS_CHANGED')
            ->where('new_value', 'ARCHIVED')
            ->max('created_at');

        if ($archivedEvent !== null) {
            return $archivedEvent;
        }

        $activity = array_filter([
            $order->production_processed_at ?? null,
            $order->last_sent_to_client_at ?? null,
            $order->production_sent_at ?? null,
            DB::table('order_events')->where('order_id', $order->id)->where('event_type', 'not like', 'deliverable\_%')->max('created_at'),
        ]);

        if ($activity !== []) {
            return Carbon::parse(max(array_map(fn ($value): string => Carbon::parse($value)->toDateTimeString(), $activity)))->toDateTimeString();
        }

        return $order->archived_at ?? $order->updated_at;
    }

    /**
     * Step f: every order element becomes an additional deliverable under the work order of its card. Pieces of a
     * card in the trash go to the trash with it.
     */
    public function foldElements(): void
    {
        DB::transaction(function (): void {
            $this->resolveSupportUsers();

            $created = 0;
            $approvals = 0;
            $byStatus = [];

            $elements = DB::table('order_elements')
                ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('orders')->whereColumn('orders.legacy_element_id', 'order_elements.id'))
                ->orderBy('id')
                ->get();

            foreach ($elements as $element) {
                $parent = DB::table('orders')->where('id', $element->order_id)->first();
                if ($parent === null || $parent->work_order_id === null || $parent->status === null) {
                    $this->report['unmapped'][] = ['element_id' => $element->id, 'reason' => 'parent card has no work order or status'];

                    continue;
                }

                $parentStatus = DeliverableStatus::from($parent->status);
                $status = $this->elementStatusFor($element->status, $parentStatus);
                if ($status === null) {
                    $this->report['unmapped'][] = ['element_id' => $element->id, 'status' => $element->status];
                    $status = DeliverableStatus::READY;
                }

                $isDone = $status === DeliverableStatus::DONE;
                $deliverableId = DB::table('orders')->insertGetId([
                    'company_name' => $parent->company_name,
                    'task_name' => $element->name,
                    'wo_number' => $parent->wo_number,
                    'designer_id' => $parent->designer_id,
                    'client_id' => $parent->client_id,
                    'client_location_id' => $parent->client_location_id,
                    'contact_id' => $parent->contact_id,
                    'core_status' => $parent->core_status,
                    'in_workspace' => $parent->in_workspace,
                    'current_due_date' => $parent->current_due_date,
                    'created_at' => $element->created_at,
                    'updated_at' => $element->updated_at,
                    'work_order_id' => $parent->work_order_id,
                    'status' => $status->value,
                    'assignee_id' => $parent->assignee_id,
                    'reviewer_id' => $parent->reviewer_id,
                    'planned_for' => $status === DeliverableStatus::IN_DESIGN ? $parent->planned_for : null,
                    'due_target' => $parent->due_target,
                    'in_backlog' => ! $isDone && $parent->in_backlog,
                    'size' => $element->dimensions,
                    'material' => $element->material,
                    'quantity' => $element->quantity,
                    'files_url' => $element->file_url,
                    'flag_codes' => '[]',
                    'close_reason' => $isDone ? $parent->close_reason : null,
                    'closed_at' => $isDone ? $parent->closed_at : null,
                    'carry_over_count' => 0,
                    'legacy_element_id' => $element->id,
                    'deleted_at' => $parent->deleted_at,
                ]);

                if ($element->is_approved) {
                    $source = $this->approvalSourceFor($element->approval_type) ?? ApprovalSource::CLIENT;
                    DB::table('approvals')->insert([
                        'deliverable_id' => $deliverableId,
                        'source' => $source->value,
                        'measurements_confirmed' => false,
                        'estimate_approved' => false,
                        'note' => $element->approval_note,
                        'approved_by' => $this->approverFor($source),
                        'approved_at' => $element->approved_at ?? $element->updated_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $approvals++;
                }

                $created++;
                $byStatus[$status->value] = ($byStatus[$status->value] ?? 0) + 1;
            }

            $this->report['elements'] = [
                'deliverables_created' => $created,
                'approvals_created' => $approvals,
                'by_status' => $byStatus,
            ];
        });
    }

    /**
     * Step h: open work in the workspace is due at least 3 days after its last activity, and its work order is promised
     * at least 3 days after the last activity of its open work. Dates that are already later are kept, and work on
     * hold keeps having no due date.
     *
     * @return array{due: int, promised: int} How many due dates and promised dates moved.
     */
    public function rebaseDueDates(): array
    {
        return DB::transaction(function (): array {
            $open = DB::table('orders')
                ->whereNull('deleted_at')
                ->whereNotNull('status')
                ->where('status', '!=', DeliverableStatus::DONE->value)
                ->where('in_backlog', false)
                ->selectRaw('id, work_order_id, due_target, EXISTS (SELECT 1 FROM holds WHERE holds.deliverable_id = orders.id AND holds.ended_at IS NULL) as held')
                ->orderBy('id')
                ->get();

            $activity = app(LastActivity::class)->forDeliverables($open->pluck('id')->map(fn ($id): int => (int) $id)->all());
            $floorOf = fn (int $id): ?Carbon => isset($activity[$id]) ? Carbon::instance($activity[$id])->startOfDay()->addDays(self::DAYS_AFTER_LAST_ACTIVITY) : null;
            $due = 0;
            $promised = 0;

            foreach ($open as $row) {
                $floor = $floorOf((int) $row->id);
                if ($row->held || $row->due_target === null || $floor === null || Carbon::parse($row->due_target)->startOfDay()->gte($floor)) {
                    continue;
                }

                DB::table('orders')->where('id', $row->id)->update(['due_target' => $floor->toDateString()]);
                $due++;
            }

            foreach ($open->whereNotNull('work_order_id')->groupBy('work_order_id') as $workOrderId => $rows) {
                $floor = $rows->map(fn (object $row): ?Carbon => $floorOf((int) $row->id))->filter()->max();
                $promisedDate = DB::table('work_orders')->where('id', $workOrderId)->value('promised_date');

                if ($floor === null || $promisedDate === null || Carbon::parse($promisedDate)->startOfDay()->gte($floor)) {
                    continue;
                }

                DB::table('work_orders')->where('id', $workOrderId)->update(['promised_date' => $floor->toDateString()]);
                $promised++;
            }

            $this->report['due_dates_rebased'] = $due;
            $this->report['promised_dates_rebased'] = $promised;

            return ['due' => $due, 'promised' => $promised];
        });
    }

    private function statusFor(string $core, string $sub): ?DeliverableStatus
    {
        $productionFilesSubstatuses = match ($core) {
            'TO DO TODAY' => ['PONER EN ALTA', 'AJUSTES DE PRODUCCION'],
            'EURALIZ ORDERS RECEIVED', 'ADRIAN ORDERS RECEIVED', 'CESAR ORDERS RECEIVED' => ['PONER EN ALTA'],
            default => [],
        };

        if (in_array($sub, $productionFilesSubstatuses, true)) {
            return DeliverableStatus::PRODUCTION_FILES;
        }

        return match ($core) {
            'ENTRANTE' => DeliverableStatus::INTAKE,
            'EURALIZ ORDERS RECEIVED', 'ADRIAN ORDERS RECEIVED', 'CESAR ORDERS RECEIVED' => DeliverableStatus::READY,
            'TO DO TODAY' => DeliverableStatus::IN_DESIGN,
            'ENVIADO A CAMILA' => DeliverableStatus::INTERNAL_REVIEW,
            'ENVIADO AL CLIENTE' => DeliverableStatus::CLIENT_REVIEW,
            'ON HOLD' => DeliverableStatus::READY,
            'EN PRODUCCION' => DeliverableStatus::IN_PRODUCTION,
            'ARCHIVED' => DeliverableStatus::DONE,
            default => null,
        };
    }

    /**
     * @return array{0: DeliverableStatus, 1: HoldReason}
     */
    private function holdFor(string $sub): array
    {
        if (in_array($sub, ['NO RESPUESTA', 'CUSTOMER SERVICE REQUIRED', 'ESPERANDO RESPUESTA', 'WAITING FOR CLIENT'], true)) {
            return [DeliverableStatus::CLIENT_REVIEW, HoldReason::NO_RESPONSE];
        }

        return [DeliverableStatus::READY, match ($sub) {
            'ESPERANDO PERMISO' => HoldReason::AWAITING_PERMIT,
            'PROCESO DE PERMISO' => HoldReason::PERMIT_IN_PROGRESS,
            default => HoldReason::PAUSED,
        }];
    }

    private function closeReasonFor(string $sub): CloseReason
    {
        return match ($sub) {
            'CANCELADA' => CloseReason::CANCELLED_INTERNAL,
            'CANCELADA POR CLIENTE' => CloseReason::CANCELLED_BY_CLIENT,
            'CANCELADA POR CAMILA' => CloseReason::CANCELLED_BY_REVIEWER,
            'NO REALIZADA / TRANSFERIDA' => CloseReason::TRANSFERRED,
            'CLIENTE NO RESPONSIVE', 'CLIENTE NO RESPONDIO' => CloseReason::CLIENT_UNRESPONSIVE,
            default => CloseReason::COMPLETED,
        };
    }

    /**
     * @return list<DeliverableFlag>
     */
    private function flagsFor(?string $legacyFlags, string $sub): array
    {
        $labels = [$sub];
        $decoded = json_decode((string) $legacyFlags, true);
        if (is_array($decoded)) {
            foreach ($decoded as $label) {
                if (is_string($label)) {
                    $labels[] = $this->normalizeKey($label);
                }
            }
        }

        $flags = [];
        foreach ($labels as $label) {
            $flag = match ($label) {
                'URGENTE' => DeliverableFlag::URGENT,
                'TICKET' => DeliverableFlag::TICKET,
                'POTENTIAL CUSTOMER' => DeliverableFlag::PROSPECT,
                'EXTERNO' => DeliverableFlag::EXTERNAL,
                'REPRINT' => DeliverableFlag::REPRINT,
                'MAKE PERMIT SIGN' => DeliverableFlag::PERMIT_SIGN,
                'DEBE' => DeliverableFlag::BALANCE_DUE,
                default => null,
            };

            if ($flag !== null && ! in_array($flag, $flags, true)) {
                $flags[] = $flag;
            }
        }

        return $flags;
    }

    /**
     * @return list<array{0: BlockReason, 1: ?string}>
     */
    private function blockSpecsFor(object $order, string $sub): array
    {
        $specs = [];
        $reasonLabel = $this->normalizeKey($order->blocking_reason);

        if ($reasonLabel !== '') {
            $reason = match ($reasonLabel) {
                'FALTAN MEDIDAS' => BlockReason::MISSING_MEASUREMENTS,
                'FALTA LOGO' => BlockReason::MISSING_LOGO,
                default => BlockReason::OTHER,
            };
            $specs[] = [$reason, $order->blocking_reason_other];
        }

        if ($sub === 'FALTA APROBACION DE ESTIMADO') {
            $specs[] = [BlockReason::AWAITING_ESTIMATE_APPROVAL, null];
        }

        if ($order->customer_service_required || $sub === 'CUSTOMER SERVICE REQUIRED') {
            $specs[] = [BlockReason::CUSTOMER_SERVICE_REQUIRED, null];
        }

        if ($sub === 'FALTA INFORMACION') {
            $specs[] = [BlockReason::MISSING_INFO, null];
        }

        if ($specs === [] && $sub === 'BLOQUEADA') {
            $specs[] = [BlockReason::OTHER, null];
        }

        return $specs;
    }

    private function approvalSourceFor(?string $type): ?ApprovalSource
    {
        return match ($this->normalizeKey($type)) {
            'CLIENTE', 'CLIENT' => ApprovalSource::CLIENT,
            'CAMILA', 'REVIEWER' => ApprovalSource::REVIEWER,
            'MANAGER', 'GABRIELA' => ApprovalSource::MANAGER,
            default => null,
        };
    }

    private function approverFor(ApprovalSource $source): ?int
    {
        return match ($source) {
            ApprovalSource::REVIEWER => $this->reviewerUserId,
            ApprovalSource::MANAGER => $this->managerUserId,
            ApprovalSource::CLIENT => null,
        };
    }

    private function systemAlertKindFor(string $type): ?AlertKind
    {
        return match ($type) {
            'ENVIAR CORREO DE BIENVENIDA' => AlertKind::WELCOME_EMAIL,
            'FOLLOW UP CLIENTE' => AlertKind::CLIENT_FOLLOW_UP_1,
            'FOLLOW UP CAMILA' => AlertKind::REVIEWER_FOLLOW_UP,
            'ENVIAR CORREO DE ATRASO' => AlertKind::DELAY_NOTICE,
            'SOLICITAR INFORMACION' => AlertKind::REQUEST_INFO,
            default => null,
        };
    }

    private function elementStatusFor(?string $status, DeliverableStatus $parentStatus): ?DeliverableStatus
    {
        if ($parentStatus === DeliverableStatus::DONE) {
            return DeliverableStatus::DONE;
        }

        return match ($status) {
            'draft' => $parentStatus === DeliverableStatus::IN_DESIGN ? DeliverableStatus::IN_DESIGN : DeliverableStatus::READY,
            'approved' => DeliverableStatus::PRODUCTION_FILES,
            'ready_for_production', 'in_alta' => DeliverableStatus::READY_FOR_PRODUCTION,
            default => null,
        };
    }

    /**
     * @param  list<object>  $cards
     */
    private function createWorkOrder(array $cards, ?string $number): int
    {
        $latestFirst = $cards;
        usort($latestFirst, fn (object $a, object $b): int => [$b->updated_at, $b->id] <=> [$a->updated_at, $a->id]);

        $pick = function (string $column) use ($latestFirst): mixed {
            foreach ($latestFirst as $card) {
                $value = $card->{$column} ?? null;
                if ($value !== null && trim((string) $value) !== '') {
                    return $value;
                }
            }

            return null;
        };

        $installationTypes = $this->installationTypesFor($pick('installation_types'), $pick('installation_type'));
        $allTrashed = collect($cards)->every(fn (object $card): bool => $card->deleted_at !== null);

        return DB::table('work_orders')->insertGetId([
            'number' => $number,
            'client_id' => $pick('client_id'),
            'client_location_id' => $pick('client_location_id'),
            'contact_id' => $pick('contact_id'),
            'description' => $pick('description'),
            'estimate_invoice_number' => ($estimate = $pick('estimate_invoice_number')) !== null ? trim((string) $estimate) : null,
            'promised_date' => $pick('delivery_due_date') ?? $pick('current_due_date'),
            'fulfillment_method' => $this->fulfillmentMethodFor($installationTypes)?->value,
            'installation_types' => $installationTypes === [] ? null : json_encode($installationTypes),
            'notes' => $pick('delivery_note'),
            'drive_folder_id' => $pick('drive_folder_id'),
            'created_at' => collect($cards)->min('created_at') ?? now(),
            'updated_at' => collect($cards)->max('updated_at') ?? now(),
            'deleted_at' => $allTrashed ? collect($cards)->max('deleted_at') : null,
        ]);
    }

    /**
     * @param  list<object>  $cards
     */
    private function assignCards(array $cards, int $workOrderId): void
    {
        DB::table('orders')->whereIn('id', array_map(fn (object $card): int => $card->id, $cards))
            ->update(['work_order_id' => $workOrderId]);
    }

    /**
     * Stores an import note on the most recent card when the cards of a work order disagree on the estimate invoice.
     *
     * @param  list<object>  $cards
     * @return array<string, mixed>|null
     */
    private function recordInvoiceDisagreement(array $cards, string $number): ?array
    {
        $values = [];
        foreach ($cards as $card) {
            $value = trim((string) $card->estimate_invoice_number);
            if ($value !== '') {
                $values[$value][] = $card->id;
            }
        }

        if (count($values) < 2) {
            return null;
        }

        $latest = collect($cards)->sortByDesc(fn (object $card): string => $card->updated_at.str_pad((string) $card->id, 12, '0', STR_PAD_LEFT))->first();
        $detail = ['work_order_number' => $number, 'estimate_invoice_numbers' => $values];

        DB::table('order_events')->insert([
            'order_id' => $latest->id,
            'event_type' => 'IMPORT_NOTE',
            'actor' => 'import',
            'new_value' => 'estimate_invoice_number_disagreement',
            'metadata' => json_encode($detail),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $detail;
    }

    /**
     * @return list<string>
     */
    private function installationTypesFor(?string $types, ?string $single): array
    {
        $decoded = json_decode((string) $types, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(fn ($type) => is_string($type) ? trim($type) : null, $decoded)));
        }

        return $single !== null && trim($single) !== '' ? [trim($single)] : [];
    }

    /**
     * @param  list<string>  $installationTypes
     */
    private function fulfillmentMethodFor(array $installationTypes): ?FulfillmentMethod
    {
        foreach ($installationTypes as $type) {
            $label = $this->normalizeKey($type);

            if (str_contains($label, 'INSTALACION')) {
                return FulfillmentMethod::INSTALLATION;
            }
            if (str_contains($label, 'CURRIER') || str_contains($label, 'COURIER')) {
                return FulfillmentMethod::SHIPPING;
            }
        }

        return null;
    }

    private function createUserForDesigner(object $designer): int
    {
        $base = Str::slug($designer->name) ?: 'designer-'.$designer->id;
        $email = $base.'@kudos.invalid';
        for ($suffix = 2; DB::table('users')->where('email', $email)->exists(); $suffix++) {
            $email = $base.'-'.$suffix.'@kudos.invalid';
        }

        $userId = DB::table('users')->insertGetId([
            'name' => $designer->name,
            'email' => $email,
            'password' => Hash::make(Str::random(40)),
            'role' => UserRole::DESIGNER->value,
            'active' => true,
            'phone' => $designer->phone,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('designers')->where('id', $designer->id)->update(['user_id' => $userId]);

        return $userId;
    }

    private function flagReviewers(): void
    {
        $names = array_filter(array_map(
            fn (string $name): string => $this->normalizeName($name),
            explode(',', (string) $this->setting('reviewer_name', 'Camila')),
        ));

        foreach (DB::table('users')->get(['id', 'name']) as $user) {
            $userName = $this->normalizeName($user->name);
            foreach ($names as $name) {
                if (str_contains($userName, $name)) {
                    DB::table('users')->where('id', $user->id)->update(['is_reviewer' => true]);

                    break;
                }
            }
        }
    }

    private function resolveSupportUsers(): void
    {
        $this->reviewerUserId = DB::table('users')->where('is_reviewer', true)->orderBy('id')->value('id');
        $this->customerServiceUserId = DB::table('users')
            ->where('active', true)
            ->where(fn ($holders) => $holders->where('role', UserRole::CUSTOMER_SERVICE->value)
                // The roles column arrives in a later migration than the one that runs this backfill.
                ->when(Schema::hasColumn('users', 'roles'), fn ($multiRole) => $multiRole->orWhereJsonContains('roles', UserRole::CUSTOMER_SERVICE->value)))
            ->orderBy('id')->value('id');

        $managerId = $this->setting('manager_id');
        $this->managerUserId = $managerId !== null && DB::table('users')->where('id', $managerId)->exists()
            ? (int) $managerId
            : DB::table('users')->where('name', 'like', '%Gabriela%')->orderBy('id')->value('id');
    }

    private function loadQueueUsers(): void
    {
        if ($this->userByDesigner !== []) {
            return;
        }

        foreach (DB::table('designers')->whereNotNull('user_id')->get(['id', 'user_id', 'queue_status_value']) as $designer) {
            $this->userByDesigner[(int) $designer->id] = (int) $designer->user_id;
            if ($designer->queue_status_value !== null) {
                $this->userByQueueStatus[$this->normalizeKey($designer->queue_status_value)] = (int) $designer->user_id;
            }
        }
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return $value === null || $value === '' ? $default : $value;
    }

    private function normalizeWoNumber(?string $number): ?string
    {
        $normalized = $this->normalizeKey($number);

        return in_array($normalized, self::PLACEHOLDER_WO_NUMBERS, true) ? null : $normalized;
    }

    private function normalizeKey(?string $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', Str::upper(Str::ascii((string) $value))));
    }

    private function normalizeName(?string $value): string
    {
        return Str::lower($this->normalizeKey($value));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function tally(array $rows, string $column): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row[$column]] = ($counts[$row[$column]] ?? 0) + 1;
        }

        return $counts;
    }
}
