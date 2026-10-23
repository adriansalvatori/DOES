<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Order;
use App\Models\RelatedTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderElementsAndStagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_stages_and_elements_associated_with_an_order(): void
    {
        $order = Order::create([
            'company_name' => 'RESTAURANTE EL SOL',
            'task_name' => 'ROTULACION COMPLETA',
            'wo_number' => 'WO 9901',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $stage1 = $order->stages()->create([
            'name' => 'Etapa 1 - Fachada',
            'due_date' => now()->addDays(2)->toDateString(),
            'sort_order' => 1,
            'status' => 'pending',
        ]);

        $stage2 = $order->stages()->create([
            'name' => 'Etapa 2 - Interior',
            'due_date' => now()->addDays(5)->toDateString(),
            'sort_order' => 2,
            'status' => 'pending',
        ]);

        $element1 = $order->elements()->create([
            'order_stage_id' => $stage1->id,
            'name' => 'Pared Principal',
            'dimensions' => '4.5m x 2.8m',
            'material' => 'Vinilo laminado mate',
            'quantity' => 1,
            'sort_order' => 1,
        ]);

        $element2 = $order->elements()->create([
            'order_stage_id' => $stage1->id,
            'name' => 'Aviso Luminoso 3D',
            'dimensions' => '2m x 1m',
            'quantity' => 1,
            'sort_order' => 2,
        ]);

        $this->assertTrue($order->hasElements());
        $this->assertCount(2, $order->stages);
        $this->assertCount(2, $order->elements);
        $this->assertCount(2, $stage1->elements);
        $this->assertCount(0, $stage2->elements);

        $this->assertEquals('Etapa 1 - Fachada', $order->getActiveStage()?->name);
    }

    public function test_order_element_can_be_marked_approved_independently(): void
    {
        $order = Order::create([
            'company_name' => 'HOTEL CENTRAL',
            'task_name' => 'SENALETICA',
            'wo_number' => 'WO 9902',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $element = $order->elements()->create([
            'name' => 'Cuadro Recepción',
            'quantity' => 1,
        ]);

        $this->assertFalse($element->is_approved);

        $element->markApproved('cliente', 'Aprobado por WhatsApp');

        $element->refresh();
        $this->assertTrue($element->is_approved);
        $this->assertEquals('cliente', $element->approval_type);
        $this->assertEquals('Aprobado por WhatsApp', $element->approval_note);
        $this->assertNotNull($element->approved_at);
        $this->assertEquals('approved', $element->status);
    }

    public function test_order_element_can_be_marked_ready_for_production(): void
    {
        $order = Order::create([
            'company_name' => 'CAFE ROMA',
            'task_name' => 'MENU BOARDS',
            'wo_number' => 'WO 9903',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $element = $order->elements()->create([
            'name' => 'Menu 1',
            'quantity' => 1,
            'status' => 'draft',
        ]);

        $element->markReadyForProduction('https://drive.google.com/test-file', 'Imprimir en acrílico 3mm');

        $element->refresh();
        $this->assertTrue($element->isReadyForProduction());
        $this->assertTrue($element->isInAlta());
        $this->assertEquals('https://drive.google.com/test-file', $element->file_url);
        $this->assertEquals('Imprimir en acrílico 3mm', $element->production_notes);
        $this->assertNotNull($element->completed_at);
    }

    public function test_order_stage_evaluates_all_approved_and_all_alta_ready(): void
    {
        $order = Order::create([
            'company_name' => 'PIZZERIA NAPOLI',
            'task_name' => 'ROTULACION',
            'wo_number' => 'WO 9904',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $stage = $order->stages()->create([
            'name' => 'Etapa 1',
            'due_date' => now()->toDateString(),
        ]);

        $el1 = $order->elements()->create([
            'order_stage_id' => $stage->id,
            'name' => 'Mesa 1',
        ]);
        $el2 = $order->elements()->create([
            'order_stage_id' => $stage->id,
            'name' => 'Mesa 2',
        ]);

        $stage->refresh();
        $this->assertFalse($stage->isAllApproved());
        $this->assertFalse($stage->isAllAltaReady());

        $el1->markApproved();
        $stage->refresh();
        $this->assertFalse($stage->isAllApproved());

        $el2->markApproved();
        $stage->refresh();
        $this->assertTrue($stage->isAllApproved());
        $this->assertFalse($stage->isAllAltaReady());

        $el1->markReadyForProduction();
        $el2->markReadyForProduction();
        $stage->refresh();
        $this->assertTrue($stage->isAllAltaReady());
    }

    public function test_alta_progress_attribute_calculates_correct_percentages(): void
    {
        $order = Order::create([
            'company_name' => 'GRILL MASTER',
            'task_name' => 'VINILOS',
            'wo_number' => 'WO 9905',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $this->assertEquals(0, $order->alta_progress['total']);
        $this->assertEquals(0, $order->alta_progress['percentage']);

        $el1 = $order->elements()->create(['name' => 'Pieza 1']);
        $el2 = $order->elements()->create(['name' => 'Pieza 2']);

        $order->refresh();
        $this->assertEquals(2, $order->alta_progress['total']);
        $this->assertEquals(0, $order->alta_progress['percentage']);

        $el1->markReadyForProduction();
        $order->refresh();
        $this->assertEquals(50, $order->alta_progress['percentage']);

        $el2->markReadyForProduction();
        $order->refresh();
        $this->assertEquals(100, $order->alta_progress['percentage']);
    }

    public function test_deleting_order_cascades_stages_and_elements(): void
    {
        $order = Order::create([
            'company_name' => 'TEMP STORE',
            'task_name' => 'BANNER',
            'wo_number' => 'WO 9906',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $stage = $order->stages()->create(['name' => 'Etapa 1']);
        $element = $order->elements()->create(['order_stage_id' => $stage->id, 'name' => 'Banner']);

        $this->assertDatabaseHas('order_stages', ['id' => $stage->id]);
        $this->assertDatabaseHas('order_elements', ['id' => $element->id]);

        $order->forceDelete();

        $this->assertDatabaseMissing('order_stages', ['id' => $stage->id]);
        $this->assertDatabaseMissing('order_elements', ['id' => $element->id]);
    }

    public function test_modal_livewire_can_add_stage_and_element(): void
    {
        $order = Order::create([
            'company_name' => 'MODAL TEST STORE',
            'task_name' => 'DECORACION',
            'wo_number' => 'WO 9907',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->set('newStageName', 'Etapa 1 - Fachada')
            ->set('newStageDueDate', now()->addDays(3)->toDateString())
            ->call('addStage')
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('order_stages', [
            'order_id' => $order->id,
            'name' => 'Etapa 1 - Fachada',
        ]);

        $stage = $order->stages()->first();

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->set('newElementName', 'Pared Principal')
            ->set('newElementDimensions', '4m x 2.5m')
            ->set('newElementMaterial', 'Vinilo mate')
            ->set('newElementQuantity', 2)
            ->set('newElementStageId', $stage->id)
            ->call('addElement')
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('order_elements', [
            'order_id' => $order->id,
            'order_stage_id' => $stage->id,
            'name' => 'Pared Principal',
            'dimensions' => '4m x 2.5m',
            'material' => 'Vinilo mate',
            'quantity' => 2,
        ]);
    }

    public function test_modal_livewire_can_perform_granular_approval_by_stage(): void
    {
        $order = Order::create([
            'company_name' => 'APPROVAL TEST STORE',
            'task_name' => 'DISENO',
            'wo_number' => 'WO 9908',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $stage1 = $order->stages()->create(['name' => 'Etapa 1']);
        $stage2 = $order->stages()->create(['name' => 'Etapa 2']);

        $el1 = $order->elements()->create(['order_stage_id' => $stage1->id, 'name' => 'Pieza Etapa 1']);
        $el2 = $order->elements()->create(['order_stage_id' => $stage2->id, 'name' => 'Pieza Etapa 2']);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openApprovalModal')
            ->set('approvalType', 'cliente')
            ->set('approvalComment', 'Aprobada la etapa 1')
            ->set('approvalScope', 'stage')
            ->set('selectedApprovalStageId', $stage1->id)
            ->call('submitApproval')
            ->assertDispatched('order-updated');

        $el1->refresh();
        $el2->refresh();

        $this->assertTrue($el1->is_approved);
        $this->assertEquals('cliente', $el1->approval_type);

        $this->assertFalse($el2->is_approved);
    }

    public function test_modal_livewire_unapproved_element_prompts_approval_modal_before_alta(): void
    {
        $order = Order::create([
            'company_name' => 'ALTA TOGGLE STORE',
            'task_name' => 'ALTA PIEZAS',
            'wo_number' => 'WO 9909',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $element = $order->elements()->create([
            'name' => 'Letras Corpóreas',
            'status' => 'draft',
            'is_approved' => false,
        ]);

        // Attempting to toggle Alta on an unapproved element opens the piece approval modal
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleElementAlta', $element->id)
            ->assertSet('showPieceApprovalModal', true)
            ->assertSet('pieceApprovalDirectToAlta', true)
            ->assertSet('pieceApprovalElementId', $element->id)
            // Now confirm approval with 'cliente' and 'today'
            ->set('pieceApprovalType', 'cliente')
            ->set('pieceApprovalDatePreset', 'today')
            ->set('pieceApprovalNote', 'Confirmado por WhatsApp')
            ->call('submitPieceApproval')
            ->assertSet('showPieceApprovalModal', false)
            ->assertDispatched('order-updated');

        $element->refresh();
        $this->assertTrue($element->is_approved);
        $this->assertTrue($element->isReadyForProduction());
        $this->assertEquals('cliente', $element->approval_type);
        $this->assertEquals('Confirmado por WhatsApp', $element->approval_note);
        $this->assertNotNull($element->approved_at);

        // Now that it's approved and in production, toggling it again reverts it
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleElementAlta', $element->id)
            ->assertDispatched('order-updated');

        $element->refresh();
        $this->assertFalse($element->isReadyForProduction());
        $this->assertEquals('approved', $element->status);
    }

    public function test_piece_approval_with_custom_date_and_no_date_presets(): void
    {
        $order = Order::create([
            'company_name' => 'DATE PRESET STORE',
            'task_name' => 'LETRERO',
            'wo_number' => 'WO 9910',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $element1 = $order->elements()->create(['name' => 'Pieza Fecha Ayer']);
        $element2 = $order->elements()->create(['name' => 'Pieza Sin Fecha']);

        // Test Yesterday
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openPieceApprovalModal', $element1->id, false)
            ->set('pieceApprovalType', 'camila')
            ->set('pieceApprovalDatePreset', 'yesterday')
            ->set('pieceApprovalNote', 'Validado por Camila')
            ->call('submitPieceApproval');

        $element1->refresh();
        $this->assertTrue($element1->is_approved);
        $this->assertEquals('camila', $element1->approval_type);
        $this->assertEquals(now()->subDay()->toDateString(), $element1->approved_at->toDateString());

        // Test None (Sin fecha)
        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openPieceApprovalModal', $element2->id, false)
            ->set('pieceApprovalType', 'manager')
            ->set('pieceApprovalDatePreset', 'none')
            ->set('pieceApprovalNote', 'Autorizado por Manager')
            ->call('submitPieceApproval');

        $element2->refresh();
        $this->assertTrue($element2->is_approved);
        $this->assertEquals('manager', $element2->approval_type);
        $this->assertNull($element2->approved_at);
    }

    public function test_modal_livewire_can_select_and_deselect_all_approval_elements(): void
    {
        $order = Order::create([
            'company_name' => 'SELECT ALL STORE',
            'task_name' => 'VARIAS PIEZAS',
            'wo_number' => 'WO 9911',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $el1 = $order->elements()->create(['name' => 'Pieza 1']);
        $el2 = $order->elements()->create(['name' => 'Pieza 2']);
        $el3 = $order->elements()->create(['name' => 'Pieza 3']);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('selectAllApprovalElements')
            ->assertSet('selectedApprovalElementIds', [$el1->id, $el2->id, $el3->id])
            ->call('deselectAllApprovalElements')
            ->assertSet('selectedApprovalElementIds', []);
    }

    public function test_modal_livewire_can_update_element_server_file_path(): void
    {
        $order = Order::create([
            'company_name' => 'NETWORK PATH STORE',
            'task_name' => 'AVISO',
            'wo_number' => 'WO 9912',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $element = $order->elements()->create(['name' => 'Aviso Luminoso']);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('updateElementFileUrl', $element->id, '\\\\servidor\\Kudos\\Clientes\\Aviso.pdf', 'Imprimir a 300dpi')
            ->assertDispatched('order-updated');

        $element->refresh();
        $this->assertEquals('\\\\servidor\\Kudos\\Clientes\\Aviso.pdf', $element->file_url);
        $this->assertEquals('Imprimir a 300dpi', $element->production_notes);
    }

    public function test_approving_piece_without_direct_alta_triggers_todo_subtask(): void
    {
        $order = Order::create([
            'company_name' => 'SUBTASK TRIGGER STORE',
            'task_name' => 'ROTULOS',
            'wo_number' => 'WO 9913',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $element = $order->elements()->create(['name' => 'Pared 1']);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openPieceApprovalModal', $element->id, false)
            ->set('pieceApprovalType', 'cliente')
            ->set('pieceApprovalDatePreset', 'today')
            ->set('pieceApprovalNote', 'Aprobado por cliente vía email')
            ->call('submitPieceApproval');

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'order_element_id' => $element->id,
            'title' => 'Poner en alta: Pared 1',
            'status' => 'todo',
        ]);
    }

    public function test_approving_piece_with_direct_alta_creates_auto_completed_subtask_for_audit(): void
    {
        $order = Order::create([
            'company_name' => 'DIRECT ALTA STORE',
            'task_name' => 'ROTULOS',
            'wo_number' => 'WO 9914',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $element = $order->elements()->create(['name' => 'Mesa 1']);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openPieceApprovalModal', $element->id, true)
            ->set('pieceApprovalType', 'cliente')
            ->set('pieceApprovalDatePreset', 'today')
            ->set('pieceApprovalNote', 'Aprobado en sitio')
            ->call('submitPieceApproval');

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'order_element_id' => $element->id,
            'title' => 'Poner en alta: Mesa 1',
            'status' => 'done',
        ]);

        $task = RelatedTask::where('order_element_id', $element->id)->first();
        $this->assertNotNull($task->completed_at);
    }

    public function test_marking_element_alta_completes_pending_piece_subtask(): void
    {
        $order = Order::create([
            'company_name' => 'PENDING TO DONE STORE',
            'task_name' => 'ROTULOS',
            'wo_number' => 'WO 9915',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $element = $order->elements()->create([
            'name' => 'Aviso Entrada',
            'is_approved' => true,
            'status' => 'approved',
        ]);

        $task = $order->relatedTasks()->create([
            'order_element_id' => $element->id,
            'title' => 'Poner en alta: Aviso Entrada',
            'type' => RelatedTaskType::PONER_ALTA,
            'status' => 'todo',
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleElementAlta', $element->id);

        $task->refresh();
        $element->refresh();

        $this->assertEquals('done', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertTrue($element->isReadyForProduction());
    }

    public function test_toggling_subtask_done_marks_piece_ready_for_production(): void
    {
        $order = Order::create([
            'company_name' => 'REVERSE SYNC STORE',
            'task_name' => 'ROTULOS',
            'wo_number' => 'WO 9916',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $element = $order->elements()->create([
            'name' => 'Placa Metal',
            'is_approved' => true,
            'status' => 'approved',
        ]);

        $task = $order->relatedTasks()->create([
            'order_element_id' => $element->id,
            'title' => 'Poner en alta: Placa Metal',
            'type' => RelatedTaskType::PONER_ALTA,
            'status' => 'todo',
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('toggleTaskStatus', $task->id);

        $task->refresh();
        $element->refresh();

        $this->assertEquals('done', $task->status);
        $this->assertTrue($element->isReadyForProduction());
    }

    public function test_approving_entire_stage_creates_grouped_stage_subtask(): void
    {
        $order = Order::create([
            'company_name' => 'STAGE GROUP STORE',
            'task_name' => 'ROTULOS',
            'wo_number' => 'WO 9917',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $stage = $order->stages()->create(['name' => 'Etapa 1 - Fachada']);
        $order->elements()->create(['order_stage_id' => $stage->id, 'name' => 'Pieza A']);
        $order->elements()->create(['order_stage_id' => $stage->id, 'name' => 'Pieza B']);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openApprovalModal')
            ->set('approvalType', 'cliente')
            ->set('approvalComment', 'Aprobada la fachada completa')
            ->set('approvalScope', 'stage')
            ->set('selectedApprovalStageId', $stage->id)
            ->call('submitApproval');

        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'order_stage_id' => $stage->id,
            'title' => 'Poner en alta: Etapa 1 - Fachada (2 piezas)',
            'status' => 'todo',
        ]);
    }

    public function test_piece_approval_requires_approval_support_notes(): void
    {
        $order = Order::create([
            'company_name' => 'NOTE REQUIREMENT STORE',
            'task_name' => 'VINILOS',
            'in_workspace' => true,
        ]);

        $element = $order->elements()->create(['name' => 'Vinilo Pared']);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $order->id)
            ->call('openPieceApprovalModal', $element->id, false)
            ->set('pieceApprovalType', 'cliente')
            ->set('pieceApprovalDatePreset', 'today')
            ->set('pieceApprovalNote', '')
            ->call('submitPieceApproval')
            ->assertHasErrors(['pieceApprovalNote']);

        $element->refresh();
        $this->assertFalse($element->is_approved);
    }
}
