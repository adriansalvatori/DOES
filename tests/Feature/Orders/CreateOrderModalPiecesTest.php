<?php

namespace Tests\Feature\Orders;

use App\Enums\CoreStatus;
use App\Livewire\Orders\CreateOrderModal;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CreateOrderModalPiecesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_edit_and_remove_pieces_in_modal(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->set('newPieceName', 'Aviso Luminoso 3D')
            ->set('newPieceDimensions', '2m x 1m')
            ->set('newPieceMaterial', 'Acrílico 3mm')
            ->set('newPieceQuantity', 2)
            ->call('addPiece')
            ->assertCount('pieces', 1)
            ->assertSet('newPieceName', '')
            ->assertSet('newPieceDimensions', '')
            ->assertSet('newPieceMaterial', '')
            ->assertSet('newPieceQuantity', 1)
            // Add a second piece
            ->set('newPieceName', 'Lona Front')
            ->call('addPiece')
            ->assertCount('pieces', 2)
            // Edit the first piece
            ->call('editPiece', 0)
            ->assertCount('pieces', 1)
            ->assertSet('newPieceName', 'Aviso Luminoso 3D')
            ->assertSet('newPieceDimensions', '2m x 1m')
            ->assertSet('newPieceMaterial', 'Acrílico 3mm')
            ->assertSet('newPieceQuantity', 2)
            ->assertSet('showPieceSpecs', true)
            // Remove the remaining piece
            ->call('removePiece', 0)
            ->assertCount('pieces', 0);
    }

    public function test_add_piece_requires_name(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->set('newPieceName', '   ')
            ->call('addPiece')
            ->assertHasErrors(['newPieceName'])
            ->assertCount('pieces', 0);
    }

    public function test_creating_order_persists_elements(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->set('companyName', 'RESTAURANTE EL TACO')
            ->set('taskName', 'ROTULACIÓN TOTAL')
            ->set('newPieceName', 'Pared Principal')
            ->set('newPieceDimensions', '4x2.5m')
            ->set('newPieceMaterial', 'Vinilo laminado')
            ->set('newPieceQuantity', 1)
            ->call('addPiece')
            ->set('newPieceName', 'Aviso Fachada')
            ->set('newPieceDimensions', '3x1m')
            ->set('newPieceMaterial', 'Alucobond')
            ->set('newPieceQuantity', 2)
            ->call('addPiece')
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::where('company_name', 'RESTAURANTE EL TACO')->first();
        $this->assertNotNull($order);
        $this->assertTrue($order->hasElements());
        $this->assertCount(2, $order->elements);

        $firstElement = $order->elements()->where('sort_order', 1)->first();
        $this->assertNotNull($firstElement);
        $this->assertEquals('Pared Principal', $firstElement->name);
        $this->assertEquals('4x2.5m', $firstElement->dimensions);
        $this->assertEquals('Vinilo laminado', $firstElement->material);
        $this->assertEquals(1, $firstElement->quantity);
        $this->assertEquals('draft', $firstElement->status);
        $this->assertFalse($firstElement->is_approved);

        $secondElement = $order->elements()->where('sort_order', 2)->first();
        $this->assertNotNull($secondElement);
        $this->assertEquals('Aviso Fachada', $secondElement->name);
        $this->assertEquals('3x1m', $secondElement->dimensions);
        $this->assertEquals('Alucobond', $secondElement->material);
        $this->assertEquals(2, $secondElement->quantity);
        $this->assertEquals('draft', $secondElement->status);
    }

    public function test_pending_piece_input_is_saved_even_if_add_button_not_clicked(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->set('companyName', 'CAFETERIA EXPRESS')
            ->set('taskName', 'MENÚ DIGITAL')
            ->set('newPieceName', 'Pantalla Menú')
            ->set('newPieceDimensions', '55 pulgadas')
            ->set('newPieceQuantity', 3)
            // Notice: user did not call addPiece, directly called save
            ->call('save')
            ->assertHasNoErrors();

        $order = Order::where('company_name', 'CAFETERIA EXPRESS')->first();
        $this->assertNotNull($order);
        $this->assertCount(1, $order->elements);

        $el = $order->elements->first();
        $this->assertEquals('Pantalla Menú', $el->name);
        $this->assertEquals('55 pulgadas', $el->dimensions);
        $this->assertEquals(3, $el->quantity);
    }

    public function test_duplicating_order_loads_original_pieces(): void
    {
        $user = User::factory()->create();

        $original = Order::create([
            'company_name' => 'BOUTIQUE CHIC',
            'task_name' => 'LETRERO',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $original->elements()->create([
            'name' => 'Letras Neon',
            'dimensions' => '1.5m',
            'material' => 'LED Neón Flex',
            'quantity' => 1,
            'sort_order' => 1,
        ]);

        $original->elements()->create([
            'name' => 'Placa Entrada',
            'dimensions' => '30x20cm',
            'material' => 'Latón',
            'quantity' => 1,
            'sort_order' => 2,
        ]);

        Livewire::actingAs($user)
            ->test(CreateOrderModal::class)
            ->call('openDuplicateModal', $original->id)
            ->assertCount('pieces', 2)
            ->call('save')
            ->assertHasNoErrors();

        $duplicatedOrder = Order::where('task_name', 'LETRERO (COPIA)')->first();
        $this->assertNotNull($duplicatedOrder);
        $this->assertCount(2, $duplicatedOrder->elements);
        $this->assertEquals(['Letras Neon', 'Placa Entrada'], $duplicatedOrder->elements->pluck('name')->toArray());
    }
}
