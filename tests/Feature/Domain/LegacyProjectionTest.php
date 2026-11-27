<?php

namespace Tests\Feature\Domain;

use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\WorkOrder;
use App\Enums\CoreStatus;
use App\Infrastructure\Legacy\LegacyProjection;
use App\Models\Client;
use App\Services\TrelloSyncService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class LegacyProjectionTest extends DeliveryTestCase
{
    private LegacyProjection $projection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projection = app(LegacyProjection::class);
    }

    /**
     * @return array<string, array{0: DeliverableStatus, 1: string, 2: string|null}>
     */
    public static function unassignedMap(): array
    {
        return [
            'intake' => [DeliverableStatus::INTAKE, 'ENTRANTE', null],
            'ready without a queue' => [DeliverableStatus::READY, 'ENTRANTE', null],
            'in design' => [DeliverableStatus::IN_DESIGN, 'TO DO TODAY', null],
            'internal review' => [DeliverableStatus::INTERNAL_REVIEW, 'ENVIADO A CAMILA', 'ENVIAR A CAMILA'],
            'client review' => [DeliverableStatus::CLIENT_REVIEW, 'ENVIADO AL CLIENTE', 'WAITING FOR CLIENT'],
            'production files' => [DeliverableStatus::PRODUCTION_FILES, 'ENTRANTE', 'PONER EN ALTA'],
            'ready for production' => [DeliverableStatus::READY_FOR_PRODUCTION, 'EN PRODUCCIÓN', 'ENVIADO EN ALTA'],
            'production queue' => [DeliverableStatus::PRODUCTION_QUEUE, 'EN PRODUCCIÓN', 'ENVIADO EN ALTA'],
            'in production' => [DeliverableStatus::IN_PRODUCTION, 'EN PRODUCCIÓN', 'ENVIADO EN ALTA'],
            'produced' => [DeliverableStatus::PRODUCED, 'EN PRODUCCIÓN', 'ENVIADO EN ALTA'],
            'fulfillment' => [DeliverableStatus::FULFILLMENT, 'EN PRODUCCIÓN', 'ENVIADO EN ALTA'],
        ];
    }

    #[DataProvider('unassignedMap')]
    public function test_every_status_projects_to_its_legacy_core_status(DeliverableStatus $status, string $core, ?string $substatus): void
    {
        $deliverable = $this->deliverable($status);

        $this->projection->project($deliverable);

        $row = $this->legacyRow($deliverable);
        $this->assertSame($core, $row->core_status);
        $this->assertSame($substatus, $row->substatus);
        $this->assertSame(0, (int) $row->done_today);
    }

    public function test_ready_and_production_files_use_the_queue_of_the_assignee(): void
    {
        $adrian = $this->designer('Adrián', CoreStatus::ADRIAN_ORDERS_RECEIVED);
        $euraliz = $this->designer('Euralíz', CoreStatus::EURALIZ_ORDERS_RECEIVED);
        $ready = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $adrian->id, 'planned_for' => '2026-10-02']);
        $files = $this->deliverable(DeliverableStatus::PRODUCTION_FILES, ['assignee_id' => $euraliz->id]);

        $this->projection->project($ready);
        $this->projection->project($files);

        $readyRow = $this->legacyRow($ready);
        $this->assertSame('ADRIAN ORDERS RECEIVED', $readyRow->core_status);
        $this->assertSame('2026-10-02', $readyRow->scheduled_date);
        $this->assertSame($adrian->designer_id, (int) $readyRow->designer_id);
        $this->assertTrue(DB::table('designer_order')->where(['order_id' => $ready->id, 'designer_id' => $adrian->designer_id])->exists());

        $filesRow = $this->legacyRow($files);
        $this->assertSame('EURALIZ ORDERS RECEIVED', $filesRow->core_status);
        $this->assertSame('PONER EN ALTA', $filesRow->substatus);
    }

    public function test_reassigning_replaces_the_legacy_designer_link(): void
    {
        $adrian = $this->designer('Adrián', CoreStatus::ADRIAN_ORDERS_RECEIVED);
        $cesar = $this->designer('César', CoreStatus::CESAR_ORDERS_RECEIVED);
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $adrian->id]);
        $this->projection->project($deliverable);

        $deliverable->update(['assignee_id' => $cesar->id]);
        $this->projection->project($deliverable);

        $this->assertSame([$cesar->designer_id], DB::table('designer_order')->where('order_id', $deliverable->id)->pluck('designer_id')->map(fn ($id) => (int) $id)->all());
        $this->assertSame('CESAR ORDERS RECEIVED', $this->legacyRow($deliverable)->core_status);
    }

    public function test_an_open_block_shows_in_the_legacy_blocked_column(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_MEASUREMENTS]);

        $this->projection->project($deliverable);

        $row = $this->legacyRow($deliverable);
        $this->assertSame('ENTRANTE', $row->core_status);
        $this->assertSame('BLOQUEADA', $row->substatus);
        $this->assertSame('FALTAN MEDIDAS', $row->blocking_reason);
    }

    public function test_block_reasons_map_to_the_legacy_blocking_reason_and_flags(): void
    {
        $logo = $this->deliverable(DeliverableStatus::READY);
        Block::factory()->create(['deliverable_id' => $logo->id, 'reason' => BlockReason::MISSING_LOGO]);
        $other = $this->deliverable(DeliverableStatus::READY);
        Block::factory()->create(['deliverable_id' => $other->id, 'reason' => BlockReason::OTHER, 'note' => 'Wrong file']);
        $service = $this->deliverable(DeliverableStatus::READY);
        Block::factory()->create(['deliverable_id' => $service->id, 'reason' => BlockReason::CUSTOMER_SERVICE_REQUIRED]);
        $estimate = $this->deliverable(DeliverableStatus::READY);
        Block::factory()->create(['deliverable_id' => $estimate->id, 'reason' => BlockReason::AWAITING_ESTIMATE_APPROVAL]);

        foreach ([$logo, $other, $service, $estimate] as $deliverable) {
            $this->projection->project($deliverable);
        }

        $this->assertSame('FALTA LOGO', $this->legacyRow($logo)->blocking_reason);
        $this->assertSame('OTROS', $this->legacyRow($other)->blocking_reason);
        $this->assertSame('Wrong file', $this->legacyRow($other)->blocking_reason_other);
        $this->assertSame(1, (int) $this->legacyRow($service)->customer_service_required);
        $this->assertSame('FALTA APROBACIÓN DE ESTIMADO', $this->legacyRow($estimate)->substatus);
        $this->assertNull($this->legacyRow($estimate)->blocking_reason);
    }

    public function test_resolving_the_block_clears_the_legacy_blocked_state(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $block = Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_LOGO]);
        $this->projection->project($deliverable);

        $block->update(['resolved_at' => now()]);
        $this->projection->project($deliverable);

        $row = $this->legacyRow($deliverable);
        $this->assertSame('ENTRANTE', $row->core_status);
        $this->assertNull($row->substatus);
        $this->assertNull($row->blocking_reason);
    }

    public function test_a_hold_projects_to_on_hold_and_keeps_the_no_response_substatus(): void
    {
        $cases = [
            [HoldReason::PAUSED, 'PAUSADO'],
            [HoldReason::NO_RESPONSE, 'NO RESPUESTA'],
            [HoldReason::AWAITING_PERMIT, 'ESPERANDO PERMISO'],
            [HoldReason::PERMIT_IN_PROGRESS, 'PROCESO DE PERMISO'],
        ];

        foreach ($cases as [$reason, $substatus]) {
            $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
            Hold::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => $reason, 'note' => 'Because']);

            $this->projection->project($deliverable);

            $row = $this->legacyRow($deliverable);
            $this->assertSame('ON HOLD', $row->core_status);
            $this->assertSame($substatus, $row->substatus);
            $this->assertSame('Because', $row->pause_reason);
        }
    }

    public function test_a_block_wins_over_a_hold(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        Hold::factory()->create(['deliverable_id' => $deliverable->id]);
        Block::factory()->create(['deliverable_id' => $deliverable->id]);

        $this->projection->project($deliverable);

        $this->assertSame('BLOQUEADA', $this->legacyRow($deliverable)->substatus);
    }

    /**
     * @return array<string, array{0: CloseReason, 1: string}>
     */
    public static function closeMap(): array
    {
        return [
            'completed' => [CloseReason::COMPLETED, 'FINALIZADA !'],
            'cancelled by client' => [CloseReason::CANCELLED_BY_CLIENT, 'CANCELADA POR CLIENTE'],
            'cancelled' => [CloseReason::CANCELLED_INTERNAL, 'CANCELADA'],
            'cancelled by reviewer' => [CloseReason::CANCELLED_BY_REVIEWER, 'CANCELADA POR CAMILA'],
            'transferred' => [CloseReason::TRANSFERRED, 'NO REALIZADA / TRANSFERIDA'],
            'unresponsive' => [CloseReason::CLIENT_UNRESPONSIVE, 'CLIENTE NO RESPONSIVE'],
        ];
    }

    #[DataProvider('closeMap')]
    public function test_done_projects_to_archived_with_the_close_reason(CloseReason $reason, string $substatus): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::DONE, ['close_reason' => $reason, 'closed_at' => '2026-09-29 12:00:00']);

        $this->projection->project($deliverable);

        $row = $this->legacyRow($deliverable);
        $this->assertSame('ARCHIVED', $row->core_status);
        $this->assertSame($substatus, $row->substatus);
        $this->assertSame('2026-09-29 12:00:00', $row->archived_at);
    }

    public function test_approvals_project_to_the_legacy_approval_columns(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        Approval::factory()->fromClient(true, false)->create(['deliverable_id' => $deliverable->id, 'note' => 'ok', 'evidence_path' => 'proofs/a.png']);
        Approval::factory()->fromClient()->reset()->create(['deliverable_id' => $deliverable->id]);

        $this->projection->project($deliverable);

        $row = $this->legacyRow($deliverable);
        $this->assertSame(1, (int) $row->approved);
        $this->assertSame('cliente', $row->approval_type);
        $this->assertSame(1, (int) $row->measures_confirmed);
        $this->assertSame(0, (int) $row->estimate_approved);
        $this->assertSame('proofs/a.png', $row->approval_image_path);
    }

    public function test_a_reviewer_approval_projects_as_camila_and_resetting_clears_it(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        $approval = $this->reviewerApproval($deliverable, ApprovalSource::REVIEWER);
        $this->projection->project($deliverable);
        $this->assertSame('camila', $this->legacyRow($deliverable)->approval_type);

        $approval->update(['reset_at' => now()]);
        $this->projection->project($deliverable);

        $this->assertSame(0, (int) $this->legacyRow($deliverable)->approved);
    }

    public function test_work_order_fields_flags_dates_and_backlog_follow_the_domain_model(): void
    {
        $client = Client::create(['name' => 'Acme']);
        $workOrder = WorkOrder::factory()->create(['number' => 'WO 777', 'client_id' => $client->id, 'estimate_invoice_number' => 'E-9']);
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['flag_codes' => ['urgent', 'permit_sign'], 'due_target' => '2026-10-05', 'in_backlog' => true], $workOrder);

        $this->projection->project($deliverable);

        $row = $this->legacyRow($deliverable);
        $this->assertSame('WO 777', $row->wo_number);
        $this->assertSame($client->id, (int) $row->client_id);
        $this->assertSame('E-9', $row->estimate_invoice_number);
        $this->assertSame(0, (int) $row->in_workspace);
        $this->assertSame('2026-10-05', $row->current_due_date);
        $this->assertSame(['URGENTE', 'MAKE PERMIT SIGN'], json_decode($row->flags, true));

        $deliverable->update(['in_backlog' => false]);
        $this->projection->project($deliverable);
        $this->assertSame(1, (int) $this->legacyRow($deliverable)->in_workspace);
    }

    public function test_unmanaged_legacy_flags_are_kept_and_managed_ones_follow_the_domain(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['flag_codes' => ['ticket']]);
        DB::table('orders')->where('id', $deliverable->id)->update(['flags' => json_encode(['OVERDUE', 'URGENTE'])]);

        $this->projection->project($deliverable);

        $this->assertSame(['OVERDUE', 'TICKET'], json_decode($this->legacyRow($deliverable)->flags, true));
    }

    public function test_work_substatus_comes_from_the_move_that_sent_the_work_back(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);

        $this->projection->project($deliverable, ['client_changes' => true]);
        $this->assertSame('CAMBIOS CLIENTE', $this->legacyRow($deliverable)->substatus);

        $this->projection->project($deliverable);
        $this->assertSame('CAMBIOS CLIENTE', $this->legacyRow($deliverable)->substatus);

        $this->projection->project($deliverable, ['reviewer_changes' => true]);
        $this->assertSame('ENVIAR A CAMILA', $this->legacyRow($deliverable)->substatus);
    }

    public function test_the_projection_does_not_fire_the_legacy_observer(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $before = DB::table('notifications')->count();

        $this->projection->project($deliverable);

        $this->assertSame($before, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('order_events')->where('order_id', $deliverable->id)->count());
    }

    public function test_a_legacy_linked_card_is_pushed_to_trello_once_after_commit(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        DB::table('orders')->where('id', $deliverable->id)->update(['trello_card_id' => 'abc123']);

        $trello = $this->partialMock(TrelloSyncService::class);
        $trello->shouldReceive('updateCardOnTrelloAfterResponse')->once()->with($deliverable->id);

        DB::transaction(function () use ($deliverable): void {
            $this->projection->project($deliverable);
            $this->projection->project($deliverable);
        });
    }

    public function test_cards_without_a_trello_link_are_not_pushed(): void
    {
        $trello = $this->partialMock(TrelloSyncService::class);
        $trello->shouldNotReceive('updateCardOnTrelloAfterResponse');

        $this->projection->project($this->deliverable(DeliverableStatus::READY));
    }
}
