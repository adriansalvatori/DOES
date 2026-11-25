<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Livewire\Orders\CreateOrderModal;
use App\Models\Order;
use Livewire\Livewire;

class EntryCreateOrderTest extends EntryTestCase
{
    private function fill(array $overrides = [])
    {
        $component = Livewire::test(CreateOrderModal::class)
            ->call('openModal')
            ->set('companyName', 'taqueria la chula')
            ->set('taskName', 'menu board');

        foreach ($overrides as $property => $value) {
            $component->set($property, $value);
        }

        return $component;
    }

    public function test_default_order_lands_in_the_selected_designer_queue(): void
    {
        $this->fill(['designerIds' => [$this->cesar->id]])->call('save')->assertHasNoErrors();

        $order = Order::first();
        $this->assertSame(CoreStatus::CESAR_ORDERS_RECEIVED, $order->core_status);
        $this->assertSame($this->cesar->id, $order->designer_id);
        $this->assertSame('TAQUERIA LA CHULA', $order->company_name);
        $this->assertSame('MENU BOARD', $order->task_name);
        $this->assertTrue($order->in_workspace);
        $this->assertSame([$this->cesar->id], $order->designers()->pluck('designers.id')->all());
    }

    public function test_order_without_designer_goes_to_lead_designer_queue(): void
    {
        $this->fill()->call('save');

        $this->assertSame(CoreStatus::EURALIZ_ORDERS_RECEIVED, Order::first()->core_status);
    }

    public function test_falta_algo_substatus_enters_as_blocked(): void
    {
        $this->fill(['substatus' => 'FALTA ALGO', 'designerIds' => [$this->adrian->id]])->call('save');

        $this->assertSame(CoreStatus::ENTRANTE, Order::first()->core_status);
    }

    public function test_bloqueada_substatus_enters_as_blocked(): void
    {
        $this->fill(['substatus' => 'BLOQUEADA'])->call('save');

        $this->assertSame(CoreStatus::ENTRANTE, Order::first()->core_status);
    }

    public function test_urgente_flag_enters_working_today_and_keeps_the_flag(): void
    {
        $this->fill(['flags' => ['URGENTE'], 'designerIds' => [$this->adrian->id]])->call('save');

        $order = Order::first();
        $this->assertSame(CoreStatus::TO_DO_TODAY, $order->core_status);
        $this->assertTrue($order->hasFlag('URGENTE'));
    }

    public function test_blocked_rule_wins_over_urgent(): void
    {
        $this->fill(['flags' => ['FALTA ALGO', 'URGENTE']])->call('save');

        $this->assertSame(CoreStatus::ENTRANTE, Order::first()->core_status);
    }

    public function test_pieces_are_created_as_order_elements_including_pending_piece_input(): void
    {
        $this->fill()
            ->set('newPieceName', 'Pared Principal')
            ->set('newPieceDimensions', '4x2m')
            ->set('newPieceMaterial', 'Vinilo')
            ->set('newPieceQuantity', 2)
            ->call('addPiece')
            ->set('newPieceName', 'Aviso Fachada')
            ->call('save');

        $order = Order::first();
        $this->assertSame(['Pared Principal', 'Aviso Fachada'], $order->elements()->orderBy('sort_order')->pluck('name')->all());
        $first = $order->elements()->orderBy('sort_order')->first();
        $this->assertSame('4x2m', $first->dimensions);
        $this->assertSame(2, $first->quantity);
        $this->assertSame('draft', $first->status);
    }

    public function test_save_creates_welcome_task_and_created_event_and_initial_sla_dates(): void
    {
        $this->fill(['designerIds' => [$this->adrian->id]])->call('save');

        $order = Order::first();
        $this->assertDatabaseHas('related_tasks', [
            'order_id' => $order->id,
            'type' => RelatedTaskType::BIENVENIDA->value,
            'trigger_type' => 'NEW_ORDER_CREATED',
            'status' => 'todo',
            'assignee_id' => $this->adrian->id,
        ]);
        $created = $order->events()->where('event_type', 'ORDER_CREATED')->first();
        $this->assertSame('Entry Tester', $created->actor);
        $this->assertSame('app', $created->metadata['source']);
        $this->assertSame('2026-03-04', $order->start_date->toDateString());
        $this->assertSame($order->original_due_date->toDateString(), $order->current_due_date->toDateString());
    }

    public function test_save_validates_required_fields(): void
    {
        Livewire::test(CreateOrderModal::class)
            ->call('openModal')
            ->call('save')
            ->assertHasErrors(['companyName', 'taskName']);

        $this->assertSame(0, Order::count());
    }

    public function test_save_normalizes_wo_number_and_creates_client(): void
    {
        $this->fill(['woNumber' => 'WO 321'])->call('save');

        $order = Order::first();
        $this->assertSame('WO 321', $order->wo_number);
        $this->assertNotNull($order->client_id);
    }

    public function test_duplicate_modal_prefills_and_save_creates_a_copy_with_duplicated_event(): void
    {
        $original = $this->makeOrder(['company_name' => 'ACME', 'task_name' => 'LOGO', 'designer_id' => $this->cesar->id]);

        Livewire::test(CreateOrderModal::class)
            ->call('openDuplicateModal', $original->id)
            ->assertSet('taskName', 'LOGO (COPIA)')
            ->call('save');

        $copy = Order::where('id', '!=', $original->id)->first();
        $this->assertSame('LOGO (COPIA)', $copy->task_name);
        $this->assertDatabaseHas('order_events', [
            'order_id' => $copy->id,
            'event_type' => 'ORDER_DUPLICATED',
        ]);
        $this->assertContains('ORDER_CREATED', $this->eventTypes($copy));
    }
}
