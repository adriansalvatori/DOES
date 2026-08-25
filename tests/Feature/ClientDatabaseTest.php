<?php

namespace Tests\Feature;

use App\Livewire\Clients\ClientIndex;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Order;
use App\Services\ClientMatchingService;
use App\Services\OrderTitleParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_name_is_always_converted_to_uppercase(): void
    {
        $client = Client::create([
            'name' => 'fuerza latina',
        ]);

        $this->assertEquals('FUERZA LATINA', $client->name);
    }

    public function test_client_matching_service_parses_company_and_location(): void
    {
        $service = app(ClientMatchingService::class);
        $result = $service->matchOrCreate('FUERZA LATINA REF EL SOL', 'Juan Perez');

        $this->assertInstanceOf(Client::class, $result['client']);
        $this->assertEquals('FUERZA LATINA', $result['client']->name);
        $this->assertInstanceOf(ClientLocation::class, $result['location']);
        $this->assertEquals('EL SOL', $result['location']->name);
    }

    public function test_consolidate_clients_artisan_command_only_processes_workspace_orders(): void
    {
        // Workspace Order -> Should create Client
        Order::create([
            'company_name' => 'FUERZA LATINA REF EL SOL',
            'task_name' => 'Pendón 2x1',
            'responsible_person' => 'Pedro',
            'in_workspace' => true,
        ]);

        // Backlog Order -> Should NOT create Client
        Order::create([
            'company_name' => '(ALTA) WO 9999 BOGUS BACKLOG',
            'task_name' => 'Backlog Task',
            'in_workspace' => false,
        ]);

        $this->artisan('clients:consolidate')
            ->assertSuccessful();

        $this->assertDatabaseHas('clients', [
            'name' => 'FUERZA LATINA',
        ]);

        $this->assertDatabaseMissing('clients', [
            'name' => '(ALTA) WO 9999 BOGUS BACKLOG',
        ]);
    }

    public function test_clients_index_livewire_component_renders_ultra_compact_list(): void
    {
        $client = Client::create([
            'name' => 'CLIENTE TEST',
        ]);

        Livewire::test(ClientIndex::class)
            ->assertStatus(200)
            ->assertSee('CLIENTE TEST')
            ->assertSee('Base de Datos de Clientes');
    }

    public function test_merging_clients_extracts_location_and_updates_orders(): void
    {
        $targetClient = Client::create(['name' => 'FUERZA LATINA']);
        $sourceClient = Client::create(['name' => 'FUERZA LATINA TALPA 8']);

        $order = Order::create([
            'wo_number' => 'WO 15940',
            'company_name' => 'FUERZA LATINA TALPA 8',
            'task_name' => 'PROPUESTA DE SIGN',
            'client_id' => $sourceClient->id,
            'in_workspace' => true,
        ]);

        $service = app(ClientMatchingService::class);
        $service->mergeClients($targetClient, $sourceClient);

        $order->refresh();

        $this->assertEquals($targetClient->id, $order->client_id);
        $this->assertEquals('FUERZA LATINA', $order->company_name);
        $this->assertEquals('TALPA 8', $order->location_name);
        $this->assertNotNull($order->client_location_id);
    }

    public function test_order_title_parser_service_reconstructs_trello_title_with_location(): void
    {
        $title = OrderTitleParserService::buildTitle([
            'wo_number' => 'WO 15940',
            'company_name' => 'FUERZA LATINA',
            'location_name' => 'TALPA 8',
            'task_name' => 'PROPUESTA DE SIGN',
        ]);

        $this->assertEquals('WO 15940 FUERZA LATINA REF TALPA 8 - PROPUESTA DE SIGN', $title);
    }
}
