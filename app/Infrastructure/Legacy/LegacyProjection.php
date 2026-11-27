<?php

namespace App\Infrastructure\Legacy;

use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Services\TrelloSyncService;
use App\Support\TrelloCutover;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the legacy `orders` columns in step with the domain model so the old screens stay consistent.
 *
 * Writes go straight to the table, so the legacy observer never re-normalizes anything. The Trello push for a
 * legacy-linked card is queued once per card and runs after the surrounding transaction commits.
 */
class LegacyProjection
{
    /**
     * @var array<int, true>
     */
    private array $pendingTrelloPush = [];

    /**
     * @var array<string, DeliverableFlag>
     */
    private const FLAG_LABELS = [
        'URGENTE' => DeliverableFlag::URGENT,
        'TICKET' => DeliverableFlag::TICKET,
        'POTENTIAL CUSTOMER' => DeliverableFlag::PROSPECT,
        'EXTERNO' => DeliverableFlag::EXTERNAL,
        'REPRINT' => DeliverableFlag::REPRINT,
        'MAKE PERMIT SIGN' => DeliverableFlag::PERMIT_SIGN,
        'DEBE' => DeliverableFlag::BALANCE_DUE,
    ];

    /**
     * Writes the legacy columns of a deliverable.
     *
     * @param  array<string, mixed>  $context  Transition context; `client_changes` and `reviewer_changes` pick the work substatus.
     */
    public function project(Deliverable $deliverable, array $context = []): void
    {
        $fresh = Deliverable::withTrashed()
            ->with(['workOrder.clientLocation', 'workOrder.client'])
            ->find($deliverable->getKey());
        if ($fresh === null) {
            return;
        }

        $existing = DB::table('orders')->where('id', $fresh->id)->first(['core_status', 'substatus', 'flags', 'in_workspace', 'original_due_date']);
        $block = Block::open()->where('deliverable_id', $fresh->id)->orderByDesc('id')->first();
        $hold = Hold::open()->where('deliverable_id', $fresh->id)->orderByDesc('id')->first();
        $standing = Approval::standing()->where('deliverable_id', $fresh->id)->orderByDesc('id')->get();
        $designer = $this->legacyDesigner($fresh->assignee_id);

        [$core, $substatus] = $this->legacyStatus($fresh, $block, $hold, $designer->queue_status_value ?? null, $existing->core_status ?? null, $existing->substatus ?? null, $context);

        $workOrder = $fresh->workOrder;
        $latestApproval = $standing->first();

        $update = [
            'core_status' => $core->value,
            'substatus' => $substatus?->value,
            'designer_id' => $designer->id ?? null,
            'scheduled_date' => $fresh->planned_for?->toDateString(),
            'done_today' => false,
            'approved' => $standing->isNotEmpty(),
            'approval_type' => $latestApproval ? $this->legacyApprovalType($latestApproval) : null,
            'approval_note' => $latestApproval?->note,
            'approval_image_path' => $latestApproval?->evidence_path,
            'approved_at' => $latestApproval?->approved_at?->toDateTimeString(),
            'measures_confirmed' => $standing->contains(fn (Approval $approval): bool => $approval->measurements_confirmed),
            'estimate_approved' => $standing->contains(fn (Approval $approval): bool => $approval->estimate_approved),
            'blocking_reason' => $block ? $this->legacyBlockingReason($block)?->value : null,
            'blocking_reason_other' => $block && $block->reason === BlockReason::OTHER ? $block->note : null,
            'customer_service_required' => $block?->reason === BlockReason::CUSTOMER_SERVICE_REQUIRED,
            'pause_reason' => $hold?->note,
            'archived_at' => $fresh->status === DeliverableStatus::DONE ? ($fresh->closed_at ?? now())->toDateTimeString() : null,
            'current_due_date' => $fresh->due_target?->toDateString(),
            'original_due_date' => $existing->original_due_date ?? $fresh->due_target?->toDateString(),
            'flags' => json_encode($this->legacyFlags($fresh, $existing->flags ?? null)),
            'updated_at' => now()->toDateTimeString(),
        ];

        if ($fresh->status !== DeliverableStatus::DONE) {
            $update['in_workspace'] = ! $fresh->in_backlog;
        }

        if ($workOrder !== null) {
            $update += [
                'wo_number' => $workOrder->number,
                'client_id' => $workOrder->client_id,
                'client_location_id' => $workOrder->client_location_id,
                'location_name' => $workOrder->clientLocation?->name,
                'contact_id' => $workOrder->contact_id,
                'estimate_invoice_number' => $workOrder->estimate_invoice_number,
                'installation_types' => $workOrder->installation_types !== null ? json_encode($workOrder->installation_types) : null,
                'drive_folder_id' => $workOrder->drive_folder_id,
                'delivery_due_date' => $workOrder->promised_date?->toDateString(),
            ];
        }

        DB::table('orders')->where('id', $fresh->id)->update($update);

        if ($designer !== null) {
            DB::table('designer_order')->where('order_id', $fresh->id)->where('designer_id', '!=', $designer->id)->delete();
            DB::table('designer_order')->updateOrInsert(
                ['order_id' => $fresh->id, 'designer_id' => $designer->id],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }

        $this->queueTrelloPush($fresh->id);
    }

    /**
     * The legacy core status a domain status maps to, ignoring blocks and holds.
     *
     * @param  string|null  $queueStatus  The assignee's legacy queue (an ORDERS RECEIVED core status), when known.
     */
    public function coreStatusFor(DeliverableStatus $status, ?string $queueStatus = null): CoreStatus
    {
        $queue = $queueStatus !== null ? CoreStatus::tryFrom($queueStatus) : null;
        if ($queue !== null && ! CoreStatus::isPendingDesign($queue)) {
            $queue = null;
        }

        return match ($status) {
            DeliverableStatus::INTAKE => CoreStatus::ENTRANTE,
            DeliverableStatus::READY, DeliverableStatus::PRODUCTION_FILES => $queue ?? CoreStatus::ENTRANTE,
            DeliverableStatus::IN_DESIGN => CoreStatus::TO_DO_TODAY,
            DeliverableStatus::INTERNAL_REVIEW => CoreStatus::ENVIADO_A_CAMILA,
            DeliverableStatus::CLIENT_REVIEW => CoreStatus::ENVIADO_AL_CLIENTE,
            DeliverableStatus::READY_FOR_PRODUCTION,
            DeliverableStatus::PRODUCTION_QUEUE,
            DeliverableStatus::IN_PRODUCTION,
            DeliverableStatus::PRODUCED,
            DeliverableStatus::FULFILLMENT => CoreStatus::EN_PRODUCCION,
            DeliverableStatus::DONE => CoreStatus::ARCHIVED,
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: CoreStatus, 1: Substatus|null}
     */
    public function legacyStatus(
        Deliverable $deliverable,
        ?Block $block,
        ?Hold $hold,
        ?string $queueStatus,
        ?string $previousCore = null,
        ?string $previousSubstatus = null,
        array $context = [],
    ): array {
        if ($deliverable->status === DeliverableStatus::DONE) {
            return [CoreStatus::ARCHIVED, $this->legacyCloseSubstatus($deliverable->close_reason)];
        }

        if ($block !== null) {
            return [
                CoreStatus::ENTRANTE,
                $block->reason === BlockReason::AWAITING_ESTIMATE_APPROVAL ? Substatus::FALTA_APROBACION_ESTIMADO : Substatus::BLOQUEADA,
            ];
        }

        if ($hold !== null) {
            return [CoreStatus::ON_HOLD, $this->legacyHoldSubstatus($hold->reason)];
        }

        $core = $this->coreStatusFor($deliverable->status, $queueStatus);

        $substatus = match ($deliverable->status) {
            DeliverableStatus::READY_FOR_PRODUCTION,
            DeliverableStatus::PRODUCTION_QUEUE,
            DeliverableStatus::IN_PRODUCTION,
            DeliverableStatus::PRODUCED,
            DeliverableStatus::FULFILLMENT => Substatus::ENVIADO_EN_ALTA,
            DeliverableStatus::PRODUCTION_FILES => Substatus::PONER_EN_ALTA,
            DeliverableStatus::INTERNAL_REVIEW => Substatus::CAMBIOS_CAMILA,
            DeliverableStatus::CLIENT_REVIEW => Substatus::WAITING_FOR_CLIENT,
            DeliverableStatus::IN_DESIGN => $this->workSubstatus($context, $previousCore, $previousSubstatus),
            default => null,
        };

        return [$core, $substatus];
    }

    private function workSubstatus(array $context, ?string $previousCore, ?string $previousSubstatus): ?Substatus
    {
        if ($context['client_changes'] ?? false) {
            return Substatus::CAMBIOS_CLIENTE;
        }

        if ($context['reviewer_changes'] ?? false) {
            return Substatus::CAMBIOS_CAMILA;
        }

        $keepsRevision = in_array($previousSubstatus, [Substatus::CAMBIOS_CLIENTE->value, Substatus::CAMBIOS_CAMILA->value], true);

        return $previousCore === CoreStatus::TO_DO_TODAY->value && $keepsRevision ? Substatus::from($previousSubstatus) : null;
    }

    private function legacyHoldSubstatus(HoldReason $reason): Substatus
    {
        return match ($reason) {
            HoldReason::PAUSED => Substatus::PAUSADO,
            HoldReason::NO_RESPONSE => Substatus::NO_RESPUESTA,
            HoldReason::AWAITING_PERMIT => Substatus::ESPERANDO_PERMISO,
            HoldReason::PERMIT_IN_PROGRESS => Substatus::PROCESO_DE_PERMISO,
        };
    }

    private function legacyCloseSubstatus(?CloseReason $reason): Substatus
    {
        return match ($reason) {
            CloseReason::CANCELLED_BY_CLIENT => Substatus::CANCELADA_POR_CLIENTE,
            CloseReason::CANCELLED_INTERNAL => Substatus::CANCELADA,
            CloseReason::CANCELLED_BY_REVIEWER => Substatus::CANCELADA_POR_CAMILA,
            CloseReason::TRANSFERRED => Substatus::NO_REALIZADA_TRANSFERIDA,
            CloseReason::CLIENT_UNRESPONSIVE => Substatus::CLIENTE_NO_RESPONSIVE,
            CloseReason::INACTIVE_ON_HOLD => Substatus::CANCELADA,
            default => Substatus::FINALIZADA,
        };
    }

    private function legacyBlockingReason(Block $block): ?BlockingReason
    {
        return match ($block->reason) {
            BlockReason::MISSING_MEASUREMENTS => BlockingReason::FALTAN_MEDIDAS,
            BlockReason::MISSING_LOGO => BlockingReason::FALTA_LOGO,
            BlockReason::AWAITING_ESTIMATE_APPROVAL => null,
            default => BlockingReason::OTROS,
        };
    }

    private function legacyApprovalType(Approval $approval): string
    {
        return match ($approval->source->value) {
            'reviewer' => 'camila',
            'manager' => 'manager',
            default => 'cliente',
        };
    }

    /**
     * Managed flags follow the domain model; any other legacy flag (such as OVERDUE) is kept as it was.
     *
     * @return list<string>
     */
    private function legacyFlags(Deliverable $deliverable, ?string $existingJson): array
    {
        $kept = array_values(array_filter(
            (array) json_decode($existingJson ?? '[]', true),
            fn (mixed $flag): bool => is_string($flag) && ! array_key_exists($flag, self::FLAG_LABELS),
        ));

        $managed = [];
        foreach (self::FLAG_LABELS as $label => $flag) {
            if ($deliverable->hasFlag($flag)) {
                $managed[] = $label;
            }
        }

        return [...$kept, ...$managed];
    }

    private function legacyDesigner(?int $assigneeId): ?object
    {
        if ($assigneeId === null) {
            return null;
        }

        return DB::table('designers')->where('user_id', $assigneeId)->orderBy('id')->first()
            ?? DB::table('designers')
                ->whereIn('id', DB::table('users')->where('id', $assigneeId)->whereNotNull('designer_id')->select('designer_id'))
                ->first();
    }

    /**
     * Queues the Trello push for a legacy-linked card once, after the open transaction commits.
     */
    private function queueTrelloPush(int $orderId): void
    {
        if (TrelloCutover::isComplete() || isset($this->pendingTrelloPush[$orderId])) {
            return;
        }

        if (! DB::table('orders')->where('id', $orderId)->whereNotNull('trello_card_id')->exists()) {
            return;
        }

        $this->pendingTrelloPush[$orderId] = true;

        DB::afterCommit(function () use ($orderId): void {
            unset($this->pendingTrelloPush[$orderId]);
            app(TrelloSyncService::class)->updateCardOnTrelloAfterResponse($orderId);
        });
    }
}
