<?php

namespace Tests\Feature\Domain\Queries;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class QueryTestCase extends TestCase
{
    use RefreshDatabase;

    protected function makeClient(string $name = 'Acme'): Client
    {
        return Client::create(['name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $deliverable
     */
    protected function makeDeliverable(DeliverableStatus $status, ?WorkOrder $workOrder = null, array $deliverable = []): Deliverable
    {
        return Deliverable::factory()
            ->for($workOrder ?? WorkOrder::factory()->create(['client_id' => $this->makeClient(fake()->unique()->company())->id]), 'workOrder')
            ->inStatus($status)
            ->create($deliverable);
    }

    /**
     * Number of SQL queries the callback runs.
     */
    protected function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    /**
     * Runs the callback on a small and on a larger data set and asserts the query count did not grow.
     */
    protected function assertConstantQueries(callable $seedMore, callable $callback): void
    {
        $callback();
        $small = $this->countQueries($callback);
        $seedMore();
        $large = $this->countQueries($callback);

        $this->assertSame($small, $large, "Query count grew from {$small} to {$large} with more data (N+1).");
    }
}
