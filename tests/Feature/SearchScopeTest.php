<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Models\Client;
use App\Models\Order;
use App\Models\RelatedTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_search_scope_matches_non_contiguous_words(): void
    {
        $matchingOrder = Order::create([
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY->value,
            'company_name' => 'Talpa Retail',
            'task_name' => 'talpa 8 mableton renovation',
            'wo_number' => 'WO-1010',
        ]);

        $nonMatchingOrder = Order::create([
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY->value,
            'company_name' => 'Talpa Atlanta',
            'task_name' => 'talpa store banner',
            'wo_number' => 'WO-2020',
        ]);

        $results = Order::search('talpa mableton')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($matchingOrder->id, $results->first()->id);
    }

    public function test_order_search_scope_matches_words_across_different_fields(): void
    {
        $matchingOrder = Order::create([
            'in_workspace' => true,
            'core_status' => CoreStatus::TO_DO_TODAY->value,
            'company_name' => 'Talpa Food Mart',
            'location_name' => 'Mableton Highway',
            'task_name' => 'Window Decals',
        ]);

        $results = Order::search('talpa mableton')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($matchingOrder->id, $results->first()->id);
    }

    public function test_client_search_scope_matches_multi_token(): void
    {
        $client = Client::create([
            'name' => 'TALPA SUPERMARKET MABLETON',
            'website' => 'https://talpa.com',
        ]);

        $results = Client::search('talpa mableton')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($client->id, $results->first()->id);
    }

    public function test_related_task_search_scope_matches_multi_token(): void
    {
        $order = Order::create([
            'in_workspace' => true,
            'company_name' => 'Talpa Group',
            'task_name' => 'Main Task',
        ]);

        $task = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'Install mableton storefront letters',
        ]);

        $results = RelatedTask::search('talpa mableton')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($task->id, $results->first()->id);
    }
}
