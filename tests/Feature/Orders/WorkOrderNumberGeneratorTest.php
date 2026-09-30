<?php

namespace Tests\Feature\Orders;

use App\Contracts\WorkOrderNumberGenerator;
use App\Livewire\Orders\CreateOrderModal;
use App\Livewire\Orders\OrderDetailModal;
use App\Models\Order;
use App\Services\WorkOrder\QuickBooksWorkOrderGenerator;
use App\Services\WorkOrder\SequentialWorkOrderGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class WorkOrderNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_sequential_generator_by_default(): void
    {
        $generator = app(WorkOrderNumberGenerator::class);

        $this->assertInstanceOf(SequentialWorkOrderGenerator::class, $generator);
    }

    public function test_generates_starting_number_when_no_orders_exist(): void
    {
        $generator = app(WorkOrderNumberGenerator::class);

        $this->assertNull($generator->getLastNumber());
        $this->assertEquals('WO 10001', $generator->generateNext());
        $this->assertEquals('10001', $generator->generateNextDigits());
    }

    public function test_generates_next_sequential_number_from_last_valid_order(): void
    {
        Order::create([
            'company_name' => 'ACME CORP',
            'task_name' => 'BANNER DISPLAY',
            'wo_number' => 'WO 16366',
            'in_workspace' => true,
        ]);

        $generator = app(WorkOrderNumberGenerator::class);

        $this->assertEquals('16366', $generator->getLastNumber());
        $this->assertEquals('WO 16367', $generator->generateNext());
        $this->assertEquals('16367', $generator->generateNextDigits());
    }

    public function test_skips_placeholder_and_zero_wo_numbers(): void
    {
        Order::create([
            'company_name' => 'LEGIT ORDER',
            'task_name' => 'VALID WORK',
            'wo_number' => 'WO 16350',
            'in_workspace' => true,
        ]);

        // Placeholders created after the valid one
        Order::create([
            'company_name' => 'NO WO ORDER',
            'task_name' => 'PENDING WO',
            'wo_number' => 'WO 00000',
            'in_workspace' => true,
        ]);

        Order::create([
            'company_name' => 'INTERNAL NOTE',
            'task_name' => 'INTERNAL TASK',
            'wo_number' => 'WO 55555',
            'in_workspace' => true,
        ]);

        Order::create([
            'company_name' => 'EMPTY WO',
            'task_name' => 'EMPTY WORK',
            'wo_number' => null,
            'in_workspace' => true,
        ]);

        $generator = app(WorkOrderNumberGenerator::class);

        $this->assertEquals('16350', $generator->getLastNumber());
        $this->assertEquals('WO 16351', $generator->generateNext());
    }

    public function test_avoids_collision_with_existing_orders(): void
    {
        Order::create([
            'company_name' => 'ORDER 1',
            'task_name' => 'TASK 1',
            'wo_number' => 'WO 16360',
            'in_workspace' => true,
        ]);

        // Existing manual order ahead of sequence
        Order::create([
            'company_name' => 'ORDER 2',
            'task_name' => 'TASK 2',
            'wo_number' => 'WO 16361',
            'in_workspace' => true,
        ]);

        $generator = app(WorkOrderNumberGenerator::class);

        $this->assertEquals('WO 16362', $generator->generateNext());
    }

    public function test_avoids_collision_with_soft_deleted_orders(): void
    {
        Order::create([
            'company_name' => 'BASE ORDER',
            'task_name' => 'BASE TASK',
            'wo_number' => 'WO 16360',
            'in_workspace' => true,
        ]);

        $deletedOrder = Order::create([
            'company_name' => 'DELETED ORDER',
            'task_name' => 'CANCELLED TASK',
            'wo_number' => 'WO 16361',
            'in_workspace' => true,
        ]);
        $deletedOrder->delete();

        $generator = app(WorkOrderNumberGenerator::class);

        // Should detect WO 16361 exists in trashed orders and increment to 16362
        $this->assertEquals('WO 16362', $generator->generateNext());
    }

    public function test_livewire_create_order_modal_generates_and_saves_sequential_wo(): void
    {
        Order::create([
            'company_name' => 'EXISTING CLIENT',
            'task_name' => 'EXISTING JOB',
            'wo_number' => 'WO 16366',
            'in_workspace' => true,
        ]);

        Livewire::test(CreateOrderModal::class)
            ->call('openModal')
            ->call('generateWoNumber')
            ->assertSet('woNumber', '16367')
            ->set('companyName', 'NOVA BRAND')
            ->set('taskName', 'VINYL WRAP')
            ->call('save')
            ->assertDispatched('order-updated');

        $this->assertDatabaseHas('orders', [
            'company_name' => 'NOVA BRAND',
            'task_name' => 'VINYL WRAP',
            'wo_number' => 'WO 16367',
        ]);
    }

    public function test_livewire_order_detail_modal_can_generate_wo_number(): void
    {
        Order::create([
            'company_name' => 'PREV ORDER',
            'task_name' => 'PREV TASK',
            'wo_number' => 'WO 16366',
            'in_workspace' => true,
        ]);

        $orderWithoutWo = Order::create([
            'company_name' => 'CLIENT WITHOUT WO',
            'task_name' => 'PENDING JOB',
            'wo_number' => null,
            'in_workspace' => true,
        ]);

        Livewire::test(OrderDetailModal::class)
            ->call('openModal', $orderWithoutWo->id)
            ->call('generateWoNumber')
            ->assertSet('editWoNumber', '16367');
    }

    public function test_quickbooks_driver_throws_informative_exception_when_unconfigured(): void
    {
        config(['work_orders.driver' => 'quickbooks']);

        $generator = app(WorkOrderNumberGenerator::class);
        $this->assertInstanceOf(QuickBooksWorkOrderGenerator::class, $generator);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('QuickBooks');
        $generator->generateNext();
    }
}
