<?php

namespace Tests\Feature\Characterization;

use App\Models\Order;
use App\Services\WorkOrder\SequentialWorkOrderGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterizes SequentialWorkOrderGenerator beyond the basics in Orders/WorkOrderNumberGeneratorTest.
 */
class SupportWorkOrderNumberTest extends TestCase
{
    use RefreshDatabase;

    private SequentialWorkOrderGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new SequentialWorkOrderGenerator;
    }

    private function orderWithWo(?string $wo, array $attributes = []): Order
    {
        return Order::create(array_merge([
            'company_name' => 'WO CO', 'task_name' => 'Task', 'wo_number' => $wo, 'in_workspace' => true,
        ], $attributes));
    }

    public function test_starts_at_configured_starting_number_when_nothing_valid_exists(): void
    {
        $this->assertSame('WO 10001', $this->generator->generateNext());

        config(['work_orders.starting_number' => 20000]);
        $this->assertSame('WO 20000', $this->generator->generateNext());
    }

    public function test_uses_the_max_valid_number_among_the_latest_orders_not_the_latest_one(): void
    {
        $this->orderWithWo('WO 16500');
        $this->orderWithWo('WO 16100');

        $this->assertSame('16500', $this->generator->getLastNumber());
        $this->assertSame('WO 16501', $this->generator->generateNext());
    }

    public function test_only_the_last_one_hundred_orders_by_id_are_considered(): void
    {
        $this->orderWithWo('WO 30000');
        for ($i = 1; $i <= 100; $i++) {
            $this->orderWithWo('WO '.(16000 + $i));
        }

        $this->assertSame('16100', $this->generator->getLastNumber());
        $this->assertSame('WO 16101', $this->generator->generateNext());
    }

    public function test_ignored_values_are_dummy_zero_outlier_short_and_non_numeric(): void
    {
        $this->orderWithWo('WO 55555');
        $this->orderWithWo('WO 0000');
        $this->orderWithWo('WO 123456');
        $this->orderWithWo('WO 50001');
        $this->orderWithWo('WO 99');
        $this->orderWithWo('NO WO');
        $this->orderWithWo('');
        $this->orderWithWo(null);

        $this->assertNull($this->generator->getLastNumber());
        $this->assertSame('WO 10001', $this->generator->generateNext());
    }

    public function test_boundary_of_fifty_thousand_is_accepted(): void
    {
        $this->orderWithWo('WO 50000');

        $this->assertSame('50000', $this->generator->getLastNumber());
        $this->assertSame('WO 50001', $this->generator->generateNext());
    }

    public function test_bare_digits_and_spaced_formats_are_parsed(): void
    {
        $this->orderWithWo('16200');
        $this->assertSame('16200', $this->generator->getLastNumber());

        $this->orderWithWo('WO16300');
        $this->assertSame('16300', $this->generator->getLastNumber());

        $this->orderWithWo('WO 16400');
        $this->assertSame('16400', $this->generator->getLastNumber());
    }

    public function test_soft_deleted_orders_still_count_towards_the_last_number(): void
    {
        $this->orderWithWo('WO 16700')->delete();

        $this->assertSame('16700', $this->generator->getLastNumber());
        $this->assertSame('WO 16701', $this->generator->generateNext());
    }

    public function test_collision_loop_skips_taken_numbers_including_trashed_and_bare_digit_values(): void
    {
        $this->orderWithWo('WO 16000');
        $this->orderWithWo('16001')->delete();
        $this->orderWithWo('WO 16002');
        $this->orderWithWo('WO 15000');

        // last number is 16002 so the candidate is 16003 and is free
        $this->assertSame('WO 16003', $this->generator->generateNext());

        // an order holding the candidate number forces a skip
        $this->orderWithWo('WO 16003', ['company_name' => 'TAKEN']);
        $this->assertSame('WO 16004', $this->generator->generateNext());
    }

    public function test_collision_with_an_order_outside_the_hundred_order_window_is_still_skipped(): void
    {
        $this->orderWithWo('WO 16000');
        for ($i = 0; $i < 100; $i++) {
            $this->orderWithWo('WO '.(15900 + $i));
        }

        $this->assertSame('15999', $this->generator->getLastNumber());
        $this->assertSame('WO 16001', $this->generator->generateNext());
    }

    public function test_generate_next_digits_strips_the_prefix(): void
    {
        $this->orderWithWo('WO 16800');

        $this->assertSame('16801', $this->generator->generateNextDigits());
    }
}
