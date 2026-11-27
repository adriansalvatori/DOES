<?php

namespace Tests\Feature\Backfill;

use App\Enums\UserRole;
use App\Infrastructure\Backfill\LegacyBackfill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegacyBackfillTest extends TestCase
{
    use BuildsLegacyRows, RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: ?string, 2: string, 3: array<string, mixed>}>
     */
    public static function statusMappings(): array
    {
        return [
            'entrante' => ['ENTRANTE', null, 'intake', []],
            'euraliz received' => ['EURALIZ ORDERS RECEIVED', null, 'ready', []],
            'adrian received' => ['ADRIAN ORDERS RECEIVED', null, 'ready', []],
            'cesar received' => ['CESAR ORDERS RECEIVED', null, 'ready', []],
            'received with poner en alta' => ['EURALIZ ORDERS RECEIVED', 'PONER EN ALTA', 'production_files', []],
            'received with estimate approval' => ['ADRIAN ORDERS RECEIVED', 'FALTA APROBACIÓN DE ESTIMADO', 'ready', []],
            'to do today' => ['TO DO TODAY', null, 'in_design', []],
            'to do today poner en alta' => ['TO DO TODAY', 'PONER EN ALTA', 'production_files', []],
            'to do today ajustes' => ['TO DO TODAY', 'AJUSTES DE PRODUCCIÓN', 'production_files', []],
            'enviado a camila' => ['ENVIADO A CAMILA', null, 'internal_review', []],
            'enviado al cliente' => ['ENVIADO AL CLIENTE', 'WAITING FOR CLIENT', 'client_review', []],
            'en produccion' => ['EN PRODUCCIÓN', 'ENVIADO EN ALTA', 'in_production', []],
            'archived' => ['ARCHIVED', 'FINALIZADA !', 'done', []],
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    #[DataProvider('statusMappings')]
    public function test_core_status_and_substatus_map_to_the_new_status(string $core, ?string $substatus, string $expected, array $extra): void
    {
        $id = $this->legacyOrder(array_merge(['core_status' => $core, 'substatus' => $substatus], $extra));

        $this->runBackfill();

        $this->assertSame($expected, $this->row('orders', $id)->status);
    }

    public function test_to_do_today_uses_the_scheduled_date_or_today_as_planned_for(): void
    {
        $this->travelTo('2026-11-25 09:00:00');
        $scheduled = $this->legacyOrder(['core_status' => 'TO DO TODAY', 'scheduled_date' => '2026-11-27']);
        $unscheduled = $this->legacyOrder(['core_status' => 'TO DO TODAY']);

        $this->runBackfill();

        $this->assertSame('2026-11-27', $this->row('orders', $scheduled)->planned_for);
        $this->assertSame('2026-11-25', $this->row('orders', $unscheduled)->planned_for);
    }

    public function test_queue_designer_user_becomes_the_assignee_and_reviewer_is_set(): void
    {
        $user = User::factory()->designer()->create(['name' => 'Euraliz']);
        $designerId = $this->legacyDesigner(['name' => 'Euraliz', 'user_id' => $user->id, 'queue_status_value' => 'EURALIZ ORDERS RECEIVED']);
        $reviewer = User::factory()->coordinator()->create(['name' => 'Camila (Coordinadora)']);
        $withDesigner = $this->legacyOrder(['core_status' => 'EURALIZ ORDERS RECEIVED', 'designer_id' => $designerId]);
        $queueOnly = $this->legacyOrder(['core_status' => 'EURALIZ ORDERS RECEIVED']);

        $this->runBackfill();

        $this->assertSame($user->id, $this->row('orders', $withDesigner)->assignee_id);
        $this->assertSame($user->id, $this->row('orders', $queueOnly)->assignee_id);
        $this->assertSame($reviewer->id, $this->row('orders', $withDesigner)->reviewer_id);
        $this->assertTrue((bool) $reviewer->fresh()->is_reviewer);
    }

    public function test_due_target_and_trello_card_url_are_copied(): void
    {
        $id = $this->legacyOrder(['current_due_date' => '2026-12-01', 'trello_card_id' => 'abc123']);

        $this->runBackfill();

        $row = $this->row('orders', $id);
        $this->assertSame('2026-12-01', $row->due_target);
        $this->assertSame('https://trello.com/c/abc123', $row->trello_card_url);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: list<string>}>
     */
    public static function blockMappings(): array
    {
        return [
            'missing measurements' => [['blocking_reason' => 'FALTAN MEDIDAS'], ['missing_measurements']],
            'missing logo' => [['blocking_reason' => 'FALTA LOGO'], ['missing_logo']],
            'other with text' => [['blocking_reason' => 'OTROS', 'blocking_reason_other' => 'Wrong file'], ['other']],
            'estimate approval' => [['substatus' => 'FALTA APROBACIÓN DE ESTIMADO'], ['awaiting_estimate_approval']],
            'customer service flag' => [['customer_service_required' => true], ['customer_service_required']],
            'blocked without reason' => [['substatus' => 'BLOQUEADA'], ['other']],
            'reason and customer service' => [['blocking_reason' => 'FALTA LOGO', 'customer_service_required' => true], ['missing_logo', 'customer_service_required']],
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $expectedReasons
     */
    #[DataProvider('blockMappings')]
    public function test_blocking_columns_open_blocks_owned_by_customer_service(array $attributes, array $expectedReasons): void
    {
        $customerService = User::factory()->customerService()->create();
        $id = $this->legacyOrder(array_merge(['core_status' => 'ENTRANTE'], $attributes));

        $this->runBackfill();

        $blocks = DB::table('blocks')->where('deliverable_id', $id)->orderBy('id')->get();
        $this->assertSame($expectedReasons, $blocks->pluck('reason')->all());
        $this->assertNull($blocks->first()->resolved_at);
        $this->assertSame($customerService->id, $blocks->first()->owner_id);
    }

    public function test_block_note_keeps_the_free_text_reason(): void
    {
        $id = $this->legacyOrder(['blocking_reason' => 'OTROS', 'blocking_reason_other' => 'Wrong file']);

        $this->runBackfill();

        $this->assertSame('Wrong file', DB::table('blocks')->where('deliverable_id', $id)->value('note'));
    }

    public function test_unblocked_cards_and_archived_cards_get_no_block(): void
    {
        $this->legacyOrder(['core_status' => 'ENTRANTE']);
        $this->legacyOrder(['core_status' => 'ARCHIVED', 'customer_service_required' => true]);

        $this->runBackfill();

        $this->assertSame(0, DB::table('blocks')->count());
    }

    /**
     * @return array<string, array{0: ?string, 1: string, 2: string, 3: int}>
     */
    public static function holdMappings(): array
    {
        return [
            'no response' => ['NO RESPUESTA', 'client_review', 'no_response', 0],
            'customer service required' => ['CUSTOMER SERVICE REQUIRED', 'client_review', 'no_response', 1],
            'paused' => ['PAUSADO', 'ready', 'paused', 0],
            'awaiting permit' => ['ESPERANDO PERMISO', 'ready', 'awaiting_permit', 0],
            'permit in progress' => ['PROCESO DE PERMISO', 'ready', 'permit_in_progress', 0],
            'no substatus' => [null, 'ready', 'paused', 0],
        ];
    }

    #[DataProvider('holdMappings')]
    public function test_on_hold_cards_open_a_hold_with_the_matching_status(?string $substatus, string $status, string $holdReason, int $blockCount): void
    {
        $id = $this->legacyOrder(['core_status' => 'ON HOLD', 'substatus' => $substatus, 'pause_reason' => 'Waiting']);

        $this->runBackfill();

        $this->assertSame($status, $this->row('orders', $id)->status);
        $hold = DB::table('holds')->where('deliverable_id', $id)->first();
        $this->assertSame($holdReason, $hold->reason);
        $this->assertNull($hold->ended_at);
        $this->assertSame('Waiting', $hold->note);
        $this->assertSame($blockCount, DB::table('blocks')->where('deliverable_id', $id)->where('reason', 'customer_service_required')->count());
    }

    public function test_on_hold_card_flagged_for_customer_service_also_gets_the_block(): void
    {
        $id = $this->legacyOrder(['core_status' => 'ON HOLD', 'substatus' => 'NO RESPUESTA', 'customer_service_required' => true]);

        $this->runBackfill();

        $this->assertSame(1, DB::table('blocks')->where('deliverable_id', $id)->where('reason', 'customer_service_required')->count());
        $this->assertSame(1, DB::table('holds')->where('deliverable_id', $id)->count());
    }

    /**
     * @return array<string, array{0: ?string, 1: string, 2: list<string>}>
     */
    public static function closeMappings(): array
    {
        return [
            'finalizada' => ['FINALIZADA !', 'completed', []],
            'cancelada' => ['CANCELADA', 'cancelled_internal', []],
            'cancelada por cliente' => ['CANCELADA POR CLIENTE', 'cancelled_by_client', []],
            'cancelada por camila' => ['CANCELADA POR CAMILA', 'cancelled_by_reviewer', []],
            'transferida' => ['NO REALIZADA / TRANSFERIDA', 'transferred', []],
            'cliente no responsive' => ['CLIENTE NO RESPONSIVE', 'client_unresponsive', []],
            'cliente no respondio' => ['CLIENTE NO RESPONDIO', 'client_unresponsive', []],
            'orden lista entregada' => ['ORDEN LISTA - ENTREGADA', 'completed', []],
            'orden lista' => ['ORDEN LISTA !', 'completed', []],
            'pendiente instalacion' => ['PENDIENTE INSTALACION', 'completed', []],
            'debe' => ['DEBE', 'completed', ['balance_due']],
            'no substatus' => [null, 'completed', []],
        ];
    }

    /**
     * @param  list<string>  $expectedFlags
     */
    #[DataProvider('closeMappings')]
    public function test_archived_cards_close_with_the_matching_reason(?string $substatus, string $closeReason, array $expectedFlags): void
    {
        $id = $this->legacyOrder(['core_status' => 'ARCHIVED', 'substatus' => $substatus, 'archived_at' => '2026-10-20 12:00:00']);

        $this->runBackfill();

        $row = $this->row('orders', $id);
        $this->assertSame('done', $row->status);
        $this->assertSame($closeReason, $row->close_reason);
        $this->assertSame('2026-10-20 12:00:00', $row->closed_at);
        $this->assertSame($expectedFlags, json_decode($row->flag_codes, true));
    }

    public function test_flags_come_from_the_substatus_and_the_flags_column_and_overdue_is_dropped(): void
    {
        $fromSubstatus = $this->legacyOrder(['core_status' => 'TO DO TODAY', 'substatus' => 'URGENTE']);
        $fromFlags = $this->legacyOrder(['flags' => json_encode(['POTENTIAL CUSTOMER', 'EXTERNO', 'REPRINT', 'TICKET', 'MAKE PERMIT SIGN', 'URGENTE', 'OVERDUE', 'ALMOST OVERDUE'])]);

        $this->runBackfill();

        $this->assertSame(['urgent'], json_decode($this->row('orders', $fromSubstatus)->flag_codes, true));
        $this->assertEqualsCanonicalizing(
            ['prospect', 'external', 'reprint', 'ticket', 'permit_sign', 'urgent'],
            json_decode($this->row('orders', $fromFlags)->flag_codes, true),
        );
    }

    public function test_unknown_core_status_is_reported_and_left_untouched(): void
    {
        $id = $this->legacyOrder(['core_status' => 'SOMETHING ELSE']);

        $report = $this->runBackfill();

        $this->assertNull($this->row('orders', $id)->status);
        $this->assertSame($id, $report['unmapped'][0]['order_id']);
    }

    /**
     * @return array<string, array{0: ?string, 1: string}>
     */
    public static function approvalSources(): array
    {
        return [
            'cliente' => ['cliente', 'client'],
            'camila' => ['camila', 'reviewer'],
            'manager' => ['manager', 'manager'],
            'gabriela' => ['gabriela', 'manager'],
            'missing type defaults to client' => [null, 'client'],
        ];
    }

    #[DataProvider('approvalSources')]
    public function test_approved_cards_get_an_approval_row_with_its_source(?string $type, string $source): void
    {
        $reviewer = User::factory()->create(['name' => 'Camila']);
        $manager = User::factory()->coordinator()->create(['name' => 'Gabriela Requena']);
        $id = $this->legacyOrder([
            'approved' => true,
            'approval_type' => $type,
            'measures_confirmed' => true,
            'estimate_approved' => false,
            'approval_note' => 'ok by phone',
            'approval_image_path' => 'approvals/x.png',
            'approved_at' => '2026-10-15 08:00:00',
        ]);
        $this->legacyOrder(['approved' => false]);

        $this->runBackfill();

        $approval = DB::table('approvals')->where('deliverable_id', $id)->first();
        $this->assertSame(1, DB::table('approvals')->count());
        $this->assertSame($source, $approval->source);
        $this->assertSame(1, (int) $approval->measurements_confirmed);
        $this->assertSame(0, (int) $approval->estimate_approved);
        $this->assertSame('ok by phone', $approval->note);
        $this->assertSame('approvals/x.png', $approval->evidence_path);
        $this->assertSame('2026-10-15 08:00:00', $approval->approved_at);
        $this->assertSame(match ($source) {
            'reviewer' => $reviewer->id,
            'manager' => $manager->id,
            default => null,
        }, $approval->approved_by);
    }

    public function test_tasks_get_their_kind_gate_and_system_title_key(): void
    {
        $order = $this->legacyOrder();
        $subtask = $this->legacyTask($order, ['type' => 'SUBTAREA', 'title' => 'Ajustar logo']);
        $resolver = $this->legacyTask($order, ['type' => 'RESOLVER', 'title' => 'Resolver medidas']);
        $alta = $this->legacyTask($order, ['type' => 'PONER EN ALTA', 'title' => 'Poner en alta']);
        $proof = $this->legacyTask($order, ['type' => 'SUBTAREA', 'title' => 'Enviar Proof cliente']);
        $proofLong = $this->legacyTask($order, ['type' => 'RESOLVER', 'title' => 'ENVIAR PROOF CLIENTE - URGENTE']);
        $notProof = $this->legacyTask($order, ['type' => 'SUBTAREA', 'title' => 'Enviarle a gaby proof existente']);
        $welcome = $this->legacyTask($order, ['type' => 'ENVIAR CORREO DE BIENVENIDA', 'title' => 'Bienvenida']);

        $this->runBackfill();

        $this->assertSame('work', $this->row('related_tasks', $subtask)->kind);
        $this->assertSame('work', $this->row('related_tasks', $resolver)->kind);
        $this->assertSame('work', $this->row('related_tasks', $notProof)->kind);
        $this->assertSame(0, (int) $this->row('related_tasks', $subtask)->required);

        $gate = $this->row('related_tasks', $alta);
        $this->assertSame('gate_prepare_production_files', $gate->kind);
        $this->assertSame('ready_for_production', $gate->advances_to);
        $this->assertSame(1, (int) $gate->required);

        foreach ([$proof, $proofLong] as $taskId) {
            $this->assertSame('gate_send_proof', $this->row('related_tasks', $taskId)->kind);
            $this->assertSame('client_review', $this->row('related_tasks', $taskId)->advances_to);
        }

        $this->assertSame('enums.alert_kind.welcome_email', $this->row('related_tasks', $welcome)->title_key);
        $this->assertSame('Send welcome email', __($this->row('related_tasks', $welcome)->title_key));
        $this->assertSame('Bienvenida', $this->row('related_tasks', $welcome)->title);
    }

    public function test_open_system_reminders_become_alerts_and_closed_ones_do_not(): void
    {
        $designerUser = User::factory()->designer()->create();
        $designerId = $this->legacyDesigner(['user_id' => $designerUser->id]);
        $order = $this->legacyOrder();
        $types = [
            'ENVIAR CORREO DE BIENVENIDA' => 'welcome_email',
            'FOLLOW UP CLIENTE' => 'client_follow_up_1',
            'FOLLOW UP CAMILA' => 'reviewer_follow_up',
            'ENVIAR CORREO DE ATRASO' => 'delay_notice',
            'SOLICITAR INFORMACIÓN' => 'request_info',
        ];
        foreach ($types as $type => $kind) {
            $this->legacyTask($order, ['type' => $type, 'assignee_id' => $designerId, 'due_date' => '2026-11-30']);
            $this->legacyTask($order, ['type' => $type, 'status' => 'done']);
        }
        $this->legacyTask($order, ['type' => 'FOLLOW UP CLIENTE', 'deleted_at' => now()]);
        $this->legacyTask($order, ['type' => 'SUBTAREA']);

        $this->runBackfill();

        $this->assertEqualsCanonicalizing(array_values($types), DB::table('alerts')->pluck('kind')->all());
        $alert = DB::table('alerts')->where('kind', 'welcome_email')->first();
        $this->assertSame($order, $alert->deliverable_id);
        $this->assertSame($designerUser->id, $alert->user_id);
        $this->assertSame('2026-11-30', $alert->due_on);
        $this->assertNull($alert->resolved_at);
        $this->assertSame(0, DB::table('related_tasks')->whereNull('kind')->count());
    }

    public function test_elements_become_deliverables_under_the_same_work_order(): void
    {
        $order = $this->legacyOrder(['wo_number' => 'WO 100', 'core_status' => 'TO DO TODAY']);
        $draft = DB::table('order_elements')->insertGetId(['order_id' => $order, 'name' => 'Wall', 'dimensions' => '3x2 m', 'material' => 'Vinyl', 'quantity' => 2, 'status' => 'draft', 'is_approved' => false, 'file_url' => 'https://files.test/a', 'created_at' => now(), 'updated_at' => now()]);
        $approved = DB::table('order_elements')->insertGetId(['order_id' => $order, 'name' => 'Door', 'status' => 'approved', 'is_approved' => true, 'approval_type' => 'cliente', 'approved_at' => '2026-10-10 10:00:00', 'created_at' => now(), 'updated_at' => now()]);
        $ready = DB::table('order_elements')->insertGetId(['order_id' => $order, 'name' => 'Sign', 'status' => 'ready_for_production', 'is_approved' => true, 'approval_type' => 'camila', 'created_at' => now(), 'updated_at' => now()]);

        $this->runBackfill();

        $parent = $this->row('orders', $order);
        $folded = fn (int $elementId) => DB::table('orders')->where('legacy_element_id', $elementId)->first();

        $this->assertSame($parent->work_order_id, $folded($draft)->work_order_id);
        $this->assertSame('in_design', $folded($draft)->status);
        $this->assertSame('3x2 m', $folded($draft)->size);
        $this->assertSame('Vinyl', $folded($draft)->material);
        $this->assertSame(2, $folded($draft)->quantity);
        $this->assertSame('https://files.test/a', $folded($draft)->files_url);
        $this->assertSame('production_files', $folded($approved)->status);
        $this->assertSame('ready_for_production', $folded($ready)->status);
        $this->assertSame('client', DB::table('approvals')->where('deliverable_id', $folded($approved)->id)->value('source'));
        $this->assertSame('reviewer', DB::table('approvals')->where('deliverable_id', $folded($ready)->id)->value('source'));
        $this->assertSame(0, DB::table('approvals')->where('deliverable_id', $folded($draft)->id)->count());
        $this->assertSame(3, DB::table('orders')->whereNotNull('legacy_element_id')->count());
        $this->assertSame(1, DB::table('work_orders')->count());
    }

    public function test_pieces_of_a_card_in_the_trash_go_to_the_trash_with_it(): void
    {
        $order = $this->legacyOrder(['wo_number' => 'WO 101', 'core_status' => 'EURALIZ ORDERS RECEIVED', 'deleted_at' => '2026-10-23 14:21:16']);
        $piece = DB::table('order_elements')->insertGetId(['order_id' => $order, 'name' => 'Tent', 'status' => 'draft', 'is_approved' => false, 'created_at' => now(), 'updated_at' => now()]);

        $this->runBackfill();

        $this->assertSame('2026-10-23 14:21:16', DB::table('orders')->where('legacy_element_id', $piece)->value('deleted_at'));
        $this->assertNotNull(DB::table('work_orders')->where('number', 'WO 101')->value('deleted_at'));
    }

    public function test_draft_elements_of_a_ready_card_are_ready_and_of_a_done_card_are_done(): void
    {
        $ready = $this->legacyOrder(['core_status' => 'EURALIZ ORDERS RECEIVED']);
        $done = $this->legacyOrder(['core_status' => 'ARCHIVED', 'substatus' => 'CANCELADA']);
        $readyElement = DB::table('order_elements')->insertGetId(['order_id' => $ready, 'name' => 'A', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        $doneElement = DB::table('order_elements')->insertGetId(['order_id' => $done, 'name' => 'B', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);

        $this->runBackfill();

        $this->assertSame('ready', DB::table('orders')->where('legacy_element_id', $readyElement)->value('status'));
        $this->assertSame('done', DB::table('orders')->where('legacy_element_id', $doneElement)->value('status'));
        $this->assertSame('cancelled_internal', DB::table('orders')->where('legacy_element_id', $doneElement)->value('close_reason'));
    }

    public function test_real_designers_link_to_users_and_the_external_designer_gets_a_user(): void
    {
        $lead = User::factory()->designer()->create(['name' => 'Euraliz']);
        $leadDesigner = $this->legacyDesigner(['name' => 'Euraliz', 'user_id' => $lead->id, 'is_lead' => true, 'queue_status_value' => 'EURALIZ ORDERS RECEIVED']);
        $external = $this->legacyDesigner(['name' => 'Diseñador Externo', 'is_external' => true]);

        $report = $this->runBackfill();

        $this->assertSame($leadDesigner, $lead->fresh()->designer_id);
        $this->assertTrue($lead->fresh()->is_lead);
        $this->assertSame(4, $lead->fresh()->wip_limit);
        $externalUser = User::where('designer_id', $external)->first();
        $this->assertNotNull($externalUser);
        $this->assertSame(UserRole::DESIGNER, $externalUser->role);
        $this->assertSame($externalUser->id, $this->row('designers', $external)->user_id);
        $this->assertSame($externalUser->id, $report['team']['users_created'][0]['user_id']);
    }

    public function test_other_designers_move_to_contacts_without_duplicates_and_stay_in_designers(): void
    {
        $existing = DB::table('contacts')->insertGetId(['name' => 'MARIA PEREZ', 'preferred_channel' => 'any', 'preferred_language' => 'es', 'created_at' => now(), 'updated_at' => now()]);
        $merged = $this->legacyDesigner(['name' => 'María  Pérez']);
        $fresh = $this->legacyDesigner(['name' => 'Juan Lopez', 'phone' => '5551234567']);
        $twin = $this->legacyDesigner(['name' => 'juan lopez']);

        $this->runBackfill();

        $this->assertSame($existing, $this->row('designers', $merged)->moved_to_contact_id);
        $juan = $this->row('designers', $fresh)->moved_to_contact_id;
        $this->assertNotNull($juan);
        $this->assertSame($juan, $this->row('designers', $twin)->moved_to_contact_id);
        $this->assertSame('JUAN LOPEZ', $this->row('contacts', $juan)->name);
        $this->assertSame(2, DB::table('contacts')->count());
        $this->assertSame(3, DB::table('designers')->count());
    }

    public function test_designers_with_a_user_are_linked_not_moved_to_contacts(): void
    {
        $user = User::factory()->coordinator()->create(['name' => 'Camila']);
        $designer = $this->legacyDesigner(['name' => 'Camila', 'user_id' => $user->id]);

        $this->runBackfill();

        $this->assertNull($this->row('designers', $designer)->moved_to_contact_id);
        $this->assertSame($designer, $user->fresh()->designer_id);
        $this->assertSame(0, DB::table('contacts')->count());
    }

    public function test_reviewer_name_can_be_configured_with_a_setting(): void
    {
        DB::table('settings')->insert(['key' => 'reviewer_name', 'value' => 'Lucia', 'created_at' => now(), 'updated_at' => now()]);
        $camila = User::factory()->create(['name' => 'Camila']);
        $lucia = User::factory()->create(['name' => 'Lucia Ramos']);

        $this->runBackfill();

        $this->assertFalse($camila->fresh()->is_reviewer);
        $this->assertTrue($lucia->fresh()->is_reviewer);
    }

    public function test_running_the_backfill_twice_does_not_duplicate_anything(): void
    {
        $designerUser = User::factory()->designer()->create();
        $designer = $this->legacyDesigner(['user_id' => $designerUser->id, 'queue_status_value' => 'ADRIAN ORDERS RECEIVED']);
        $this->legacyDesigner(['name' => 'Someone Else']);
        $order = $this->legacyOrder(['wo_number' => 'WO 5', 'core_status' => 'ON HOLD', 'substatus' => 'PAUSADO', 'designer_id' => $designer, 'approved' => true, 'blocking_reason' => 'FALTA LOGO']);
        $this->legacyOrder(['wo_number' => 'WO 5']);
        $this->legacyOrder();
        $this->legacyTask($order, ['type' => 'FOLLOW UP CLIENTE']);
        DB::table('order_elements')->insert(['order_id' => $order, 'name' => 'A', 'status' => 'draft', 'created_at' => now(), 'updated_at' => now()]);

        $this->runBackfill();
        $counts = $this->tableCounts();
        $second = $this->runBackfill();

        $this->assertSame($counts, $this->tableCounts());
        $this->assertSame(0, $second['work_orders']['created']);
        $this->assertSame(0, $second['deliverables']['mapped']);
        $this->assertSame(0, $second['elements']['deliverables_created']);
    }

    public function test_a_card_added_after_the_first_run_joins_the_existing_work_order(): void
    {
        $first = $this->legacyOrder(['wo_number' => 'WO 7']);
        $this->runBackfill();
        $late = $this->legacyOrder(['wo_number' => 'wo 7 ']);

        $this->runBackfill();

        $this->assertSame($this->row('orders', $first)->work_order_id, $this->row('orders', $late)->work_order_id);
        $this->assertSame(1, DB::table('work_orders')->count());
        $this->assertNotNull($this->row('orders', $late)->status);
    }

    public function test_dry_run_reports_without_saving(): void
    {
        $id = $this->legacyOrder(['core_status' => 'TO DO TODAY']);

        $report = app(LegacyBackfill::class)->run(dryRun: true);

        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['deliverables']['mapped']);
        $this->assertNull($this->row('orders', $id)->status);
        $this->assertSame(0, DB::table('work_orders')->count());
    }

    public function test_the_domain_backfill_command_runs_and_writes_a_report(): void
    {
        $this->legacyOrder(['core_status' => 'EN PRODUCCIÓN']);
        $path = tempnam(sys_get_temp_dir(), 'backfill');

        $this->artisan('domain:backfill', ['--report' => $path])->assertSuccessful();

        $report = json_decode(file_get_contents($path), true);
        $this->assertSame(1, $report['deliverables']['mapped']);
        $this->assertSame('in_production', DB::table('orders')->value('status'));
        unlink($path);
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        $counts = [];
        foreach (['work_orders', 'blocks', 'holds', 'approvals', 'alerts', 'contacts', 'users', 'orders', 'order_events'] as $table) {
            $counts[$table] = DB::table($table)->count();
        }

        return $counts;
    }
}
