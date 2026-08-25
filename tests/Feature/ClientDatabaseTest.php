<?php

namespace Tests\Feature;

use App\Livewire\Backlog\Index;
use App\Livewire\Clients\ClientFlyoutPanel;
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

    public function test_client_relationships_only_include_workspace_orders(): void
    {
        $client = Client::create(['name' => 'CLIENTE SCOPE TEST']);

        $workspaceOrder = Order::create([
            'company_name' => 'CLIENTE SCOPE TEST',
            'task_name' => 'Workspace Task',
            'client_id' => $client->id,
            'in_workspace' => true,
        ]);

        $backlogOrder = Order::create([
            'company_name' => 'CLIENTE SCOPE TEST',
            'task_name' => 'Backlog Task',
            'client_id' => $client->id,
            'in_workspace' => false,
        ]);

        $this->assertCount(1, $client->orders);
        $this->assertEquals($workspaceOrder->id, $client->orders->first()->id);
        $this->assertCount(2, $client->allOrders);
    }

    public function test_match_or_create_respects_create_if_missing_flag(): void
    {
        $service = app(ClientMatchingService::class);

        $resultNoCreate = $service->matchOrCreate('EMPRESA INEXISTENTE', createIfMissing: false);
        $this->assertNull($resultNoCreate['client']);

        $resultCreate = $service->matchOrCreate('EMPRESA INEXISTENTE', createIfMissing: true);
        $this->assertInstanceOf(Client::class, $resultCreate['client']);
        $this->assertEquals('EMPRESA INEXISTENTE', $resultCreate['client']->name);
    }

    public function test_promoting_backlog_order_links_client(): void
    {
        $order = Order::create([
            'company_name' => 'EMPRESA PROMOVIDA',
            'task_name' => 'Tarea Backlog',
            'in_workspace' => false,
        ]);

        $this->assertNull($order->client_id);

        Livewire::test(Index::class)
            ->call('addToWorkspace', $order->id);

        $order->refresh();

        $this->assertTrue($order->in_workspace);
        $this->assertNotNull($order->client_id);
        $this->assertEquals('EMPRESA PROMOVIDA', $order->client->name);
    }

    public function test_can_save_client_detail_with_website_and_main_responsible(): void
    {
        $client = Client::create([
            'name' => 'EMPRESA CON WEBSITE',
        ]);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->set('website', 'https://www.empresaconwebsite.com')
            ->set('contacts.0.name', 'Carlos Gómez')
            ->set('contacts.0.email', 'carlos@empresa.com')
            ->set('contacts.0.phone', '+57 311 0000000')
            ->set('notes', 'Notas especiales')
            ->call('save')
            ->assertDispatched('client-updated');

        $client->refresh();
        $this->assertEquals('https://www.empresaconwebsite.com', $client->website);
        $this->assertEquals('Notas especiales', $client->notes);

        $this->assertDatabaseHas('client_contacts', [
            'client_id' => $client->id,
            'name' => 'Carlos Gómez',
            'email' => 'carlos@empresa.com',
            'phone' => '+57 311 0000000',
        ]);
    }
}
