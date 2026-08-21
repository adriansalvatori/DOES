<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Livewire\Backlog\Index as BacklogIndex;
use App\Livewire\Dashboard\Index;
use App\Livewire\Kanban\Board;
use App\Livewire\Orders\CreateOrderModal;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Designer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AutocompleteAndFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_modals_render_existing_companies_and_responsibles_for_autocomplete(): void
    {
        Order::create([
            'company_name' => 'TAQUERIA LA CHULA',
            'responsible_person' => 'EDER CIFUENTES',
            'task_name' => 'Menu Board',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
        ]);

        Order::create([
            'company_name' => 'GLOSSY SIGNS',
            'responsible_person' => 'MARCELA',
            'task_name' => 'Acrylic Sign',
            'core_status' => CoreStatus::ENTRANTE,
            'in_workspace' => true,
        ]);

        Livewire::test(CreateOrderModal::class)
            ->assertViewHas('existingCompanies', fn ($c) => $c->contains('TAQUERIA LA CHULA') && $c->contains('GLOSSY SIGNS'))
            ->assertViewHas('existingResponsibles', fn ($r) => $r->contains('EDER CIFUENTES') && $r->contains('MARCELA'));

        Livewire::test(OrderDetailModal::class)
            ->assertViewHas('existingCompanies', fn ($c) => $c->contains('TAQUERIA LA CHULA'))
            ->assertViewHas('existingResponsibles', fn ($r) => $r->contains('EDER CIFUENTES'));
    }

    public function test_kanban_board_filters_by_company_and_responsible(): void
    {
        $order1 = Order::create([
            'company_name' => 'TAQUERIA LA CHULA',
            'responsible_person' => 'EDER CIFUENTES',
            'task_name' => 'Menu Board',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $order2 = Order::create([
            'company_name' => 'GLOSSY SIGNS',
            'responsible_person' => 'MARCELA',
            'task_name' => 'Acrylic Sign',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Livewire::test(Board::class)
            ->set('companyFilter', 'TAQUERIA LA CHULA')
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1 && $orders->first()->id === $order1->id);

        Livewire::test(Board::class)
            ->set('responsibleFilter', 'MARCELA')
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1 && $orders->first()->id === $order2->id);
    }

    public function test_backlog_filters_by_company_and_responsible(): void
    {
        $order1 = Order::create([
            'company_name' => 'TAQUERIA LA CHULA',
            'responsible_person' => 'EDER CIFUENTES',
            'task_name' => 'Menu Board',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $order2 = Order::create([
            'company_name' => 'GLOSSY SIGNS',
            'responsible_person' => 'MARCELA',
            'task_name' => 'Acrylic Sign',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Livewire::test(BacklogIndex::class)
            ->set('companyFilter', 'TAQUERIA LA CHULA')
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1 && $orders->first()->id === $order1->id);

        Livewire::test(BacklogIndex::class)
            ->set('responsibleFilter', 'MARCELA')
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1 && $orders->first()->id === $order2->id);
    }

    public function test_can_duplicate_order_with_prefilled_modal_edits(): void
    {
        $order = Order::create([
            'company_name' => 'TAQUERIA LA CHULA',
            'responsible_person' => 'EDER CIFUENTES',
            'task_name' => 'Menu Board',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Livewire::test(CreateOrderModal::class)
            ->dispatch('open-duplicate-order', orderId: $order->id)
            ->assertSet('companyName', 'TAQUERIA LA CHULA')
            ->assertSet('taskName', 'Menu Board (Copia)')
            ->assertSet('isDuplicating', true)
            ->set('taskName', 'Menu Board (Copia Modificada)')
            ->call('save')
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('orders', [
            'company_name' => 'TAQUERIA LA CHULA',
            'task_name' => 'Menu Board (Copia Modificada)',
            'in_workspace' => true,
        ]);
    }

    public function test_assigning_external_designer_automatically_adds_euraliz(): void
    {
        $euraliz = Designer::create(['name' => 'Euralíz', 'active' => true]);
        $external = Designer::create(['name' => 'Diseñador Externo', 'active' => true]);
        $adrian = Designer::create(['name' => 'Adrián', 'active' => true]);

        $order = Order::create([
            'company_name' => 'TAQUERIA LA CHULA',
            'task_name' => 'Menu Board',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        // Assign only external designer
        $order->syncDesigners([$external->id]);

        // Both External and Euralíz should be assigned
        $designerNames = $order->fresh()->designers->pluck('name')->toArray();
        $this->assertContains('Diseñador Externo', $designerNames);
        $this->assertContains('Euralíz', $designerNames);

        // Assign External + Adrián
        $order->syncDesigners([$external->id, $adrian->id]);

        $updatedNames = $order->fresh()->designers->pluck('name')->toArray();
        $this->assertContains('Diseñador Externo', $updatedNames);
        $this->assertContains('Adrián', $updatedNames);
        $this->assertContains('Euralíz', $updatedNames);
    }

    public function test_urgente_orders_are_sorted_to_the_top(): void
    {
        $orderNormal = Order::create([
            'company_name' => 'NORMAL COMPANY',
            'task_name' => 'Normal Task',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => null,
        ]);

        $orderUrgente = Order::create([
            'company_name' => 'URGENT COMPANY',
            'task_name' => 'Urgent Task',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::URGENTE,
        ]);

        $orderedIds = Order::inWorkspace()->prioritizeUrgente()->pluck('id')->toArray();

        $this->assertEquals($orderUrgente->id, $orderedIds[0]);
    }

    public function test_dashboard_pronostico_alta_filters_orders_sent_to_client_this_week(): void
    {
        $sentThisWeek = Order::create([
            'company_name' => 'TAQUERIA EL GATO',
            'task_name' => 'Menu Board Design',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'updated_at' => now(),
        ]);

        $sentOld = Order::create([
            'company_name' => 'OLD CLIENT COMPANY',
            'task_name' => 'Old Banner',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
        ]);

        Order::where('id', $sentOld->id)->update([
            'created_at' => now()->subWeeks(3),
            'updated_at' => now()->subWeeks(3),
        ]);

        Livewire::test(Index::class)
            ->assertViewHas('pronosticoAltaOrders', fn ($orders) => $orders->pluck('id')->contains($sentThisWeek->id)
                && ! $orders->pluck('id')->contains($sentOld->id));
    }

    public function test_new_orders_from_trello_sync_are_flagged_and_displayed_in_backlog_and_dashboard(): void
    {
        $newOrder = Order::create([
            'company_name' => 'NEW TRELLO CLIENT',
            'task_name' => 'New Storefront Banner',
            'trello_card_id' => 'trello_card_abc123',
            'in_workspace' => false,
            'is_new_from_trello' => true,
        ]);

        Livewire::test(BacklogIndex::class)
            ->assertViewHas('newTrelloOrders', fn ($orders) => $orders->pluck('id')->contains($newOrder->id))
            ->call('addToWorkspace', $newOrder->id);

        $this->assertTrue($newOrder->fresh()->in_workspace);
        $this->assertFalse($newOrder->fresh()->is_new_from_trello);
    }
}
