<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Backlog\Index;
use App\Livewire\Clients\ClientFlyoutPanel;
use App\Livewire\Clients\ClientIndex;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Contact;
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
            ->assertSee(__('Catálogo de Compañías'));
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

        $this->assertDatabaseHas('contacts', [
            'name' => 'CARLOS GÓMEZ',
            'email' => 'carlos@empresa.com',
            'phone' => '+57 311 0000000',
        ]);

        $this->assertDatabaseHas('client_contact', [
            'client_id' => $client->id,
        ]);
    }

    public function test_deleting_location_nullifies_order_location_id(): void
    {
        $client = Client::create(['name' => 'CLIENTE LOCACION DELETION']);
        $location = ClientLocation::create([
            'client_id' => $client->id,
            'name' => 'SEDE EL SOL',
            'address' => 'Calle 100',
        ]);

        $order = Order::create([
            'company_name' => 'CLIENTE LOCACION DELETION',
            'task_name' => 'Order with location',
            'in_workspace' => true,
            'client_id' => $client->id,
            'client_location_id' => $location->id,
        ]);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->call('removeLocation', 0)
            ->call('save');

        $order->refresh();
        $this->assertNull($order->client_location_id);
    }

    public function test_location_phone_formatting_and_validation(): void
    {
        $client = Client::create(['name' => 'CLIENTE PHONE FORMATTING']);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->set('locations.0.name', 'SUWANEE')
            ->set('locations.0.address', '3630 Peachtree Industrial Blvd')
            ->set('locations.0.phone', '7708649359')
            ->set('locations.0.email', 'suwanee@suvidha.com')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('client_locations', [
            'client_id' => $client->id,
            'name' => 'SUWANEE',
            'phone' => '(770) 864-9359',
            'email' => 'suwanee@suvidha.com',
        ]);
    }

    public function test_confirming_duplicate_contact_merges_contacts_into_one_entry(): void
    {
        $client = Client::create(['name' => 'CLIENTE DUP CONTACTS']);

        $c1 = Contact::create([
            'name' => 'GIO',
            'phone' => '(770) 111-2222',
        ]);
        $client->contacts()->attach($c1->id, ['is_primary' => true]);

        $c2 = Contact::create([
            'name' => 'GIO GARCIA',
            'email' => 'gio@example.com',
        ]);
        $client->contacts()->attach($c2->id, ['is_primary' => false]);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->assertSee('¡Posible contacto duplicado con')
            ->call('confirmMergeContact', 1, 'GIO')
            ->call('save');

        $this->assertDatabaseHas('contacts', [
            'name' => 'GIO GARCIA',
            'phone' => '(770) 111-2222',
            'email' => 'gio@example.com',
        ]);
    }

    public function test_exact_duplicate_contact_names_trigger_warning_and_merge(): void
    {
        $client = Client::create(['name' => 'CLIENTE EXACT DUP CONTACTS']);

        $c1 = Contact::create([
            'name' => 'GIO GARCIA',
        ]);
        $client->contacts()->attach($c1->id, ['is_primary' => true]);

        $c2 = Contact::create([
            'name' => 'GIO GARCIA',
        ]);
        $client->contacts()->attach($c2->id, ['is_primary' => false]);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->assertSee('¡Posible contacto duplicado con')
            ->call('confirmMergeContact', 1, 'GIO GARCIA')
            ->call('save');

        $this->assertDatabaseHas('contacts', [
            'name' => 'GIO GARCIA',
        ]);
    }

    public function test_can_delete_client_from_client_index(): void
    {
        $client = Client::create(['name' => 'CLIENTE TO DELETE LIST']);

        $order = Order::create([
            'company_name' => 'CLIENTE TO DELETE LIST',
            'task_name' => 'Task',
            'client_id' => $client->id,
            'in_workspace' => true,
        ]);

        Livewire::test(ClientIndex::class)
            ->call('deleteClient', $client->id)
            ->assertDispatched('client-updated');

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'client_id' => null,
        ]);
    }

    public function test_can_delete_client_from_client_flyout_panel(): void
    {
        $client = Client::create(['name' => 'CLIENTE TO DELETE FLYOUT']);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->assertSee('Eliminar Cliente')
            ->call('deleteClient')
            ->assertSet('isOpen', false)
            ->assertDispatched('client-updated');

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
    }

    public function test_client_flyout_displays_active_and_archived_orders_tabs(): void
    {
        $client = Client::create(['name' => 'CLIENTE WITH ARCHIVED']);

        Order::create([
            'company_name' => 'CLIENTE WITH ARCHIVED',
            'task_name' => 'Active Project Task',
            'client_id' => $client->id,
            'in_workspace' => true,
            'core_status' => CoreStatus::EN_PRODUCCION,
        ]);

        Order::create([
            'company_name' => 'CLIENTE WITH ARCHIVED',
            'task_name' => 'Archived Project Task',
            'client_id' => $client->id,
            'in_workspace' => true,
            'core_status' => CoreStatus::ARCHIVED,
        ]);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->assertSee('Proyectos (1)')
            ->assertSee('Archivados (1)')
            ->set('activeTab', 'archived')
            ->assertSee('ARCHIVED PROJECT TASK');
    }

    public function test_can_save_multiple_labeled_phone_numbers_in_client_flyout_panel(): void
    {
        $client = Client::create(['name' => 'FUERZA LATINA MULTIPHONE']);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->set('phones', [
                ['label' => 'GA', 'phone' => '(770) 864-9359'],
                ['label' => 'SC', 'phone' => '(843) 123-4567'],
                ['label' => 'ALABAMA', 'phone' => '(205) 555-0144'],
                ['label' => 'TENNESSEE', 'phone' => '(615) 555-0188'],
            ])
            ->call('save')
            ->assertSet('isOpen', false);

        $client->refresh();
        $this->assertCount(4, $client->phones);
        $this->assertEquals('GA', $client->phones[0]['label']);
        $this->assertEquals('(770) 864-9359', $client->phones[0]['phone']);
        $this->assertEquals('SC', $client->phones[1]['label']);
        $this->assertEquals('(843) 123-4567', $client->phones[1]['phone']);
    }

    public function test_client_database_displays_active_archived_and_backlog_order_counts(): void
    {
        $client = Client::create(['name' => 'CLIENTE CON CONTEOS MIXTOS']);

        // 1 active order
        Order::create([
            'company_name' => 'CLIENTE CON CONTEOS MIXTOS',
            'task_name' => 'Active Task',
            'client_id' => $client->id,
            'in_workspace' => true,
            'core_status' => CoreStatus::EN_PRODUCCION,
        ]);

        // 2 archived orders
        Order::create([
            'company_name' => 'CLIENTE CON CONTEOS MIXTOS',
            'task_name' => 'Archived 1',
            'client_id' => $client->id,
            'in_workspace' => true,
            'core_status' => CoreStatus::ARCHIVED,
        ]);
        Order::create([
            'company_name' => 'CLIENTE CON CONTEOS MIXTOS',
            'task_name' => 'Archived 2',
            'client_id' => $client->id,
            'in_workspace' => true,
            'core_status' => CoreStatus::ARCHIVED,
        ]);

        // 1 backlog order
        Order::create([
            'company_name' => 'CLIENTE CON CONTEOS MIXTOS',
            'task_name' => 'Backlog Task',
            'client_id' => $client->id,
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Livewire::test(ClientIndex::class)
            ->assertSee('CLIENTE CON CONTEOS MIXTOS')
            ->assertSee('1 activas')
            ->assertSee('2 arch.')
            ->assertSee('1 backlog');
    }

    public function test_can_sort_active_orders_by_fields_in_client_flyout(): void
    {
        $client = Client::create(['name' => 'CLIENTE SORT FLYOUT']);

        Order::create([
            'company_name' => 'CLIENTE SORT FLYOUT',
            'client_id' => $client->id,
            'task_name' => 'Project 1',
            'wo_number' => 'WO 99999',
            'in_workspace' => true,
            'core_status' => CoreStatus::EN_PRODUCCION,
        ]);

        Order::create([
            'company_name' => 'CLIENTE SORT FLYOUT',
            'client_id' => $client->id,
            'task_name' => 'Project 2',
            'wo_number' => 'WO 11111',
            'in_workspace' => true,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->set('activeTab', 'projects')
            ->assertSet('activeOrdersSortField', 'wo')
            ->assertSet('activeOrdersSortDirection', 'asc')
            ->call('sortByActiveOrders', 'wo')
            ->assertSet('activeOrdersSortDirection', 'desc')
            ->call('sortByActiveOrders', 'location')
            ->assertSet('activeOrdersSortField', 'location')
            ->assertSet('activeOrdersSortDirection', 'asc')
            ->call('sortByActiveOrders', 'core_status')
            ->assertSet('activeOrdersSortField', 'core_status')
            ->assertSet('activeOrdersSortDirection', 'asc')
            ->assertSee('WO 99999')
            ->assertSee('WO 11111');
    }

    public function test_can_view_backlog_orders_tab_in_client_flyout(): void
    {
        $client = Client::create(['name' => 'CLIENTE BACKLOG FLYOUT']);

        Order::create([
            'company_name' => 'CLIENTE BACKLOG FLYOUT',
            'client_id' => $client->id,
            'task_name' => 'Backlog Project Item',
            'wo_number' => 'WO 77777',
            'in_workspace' => false,
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->assertSeeHtml('wire:click="$set(\'activeTab\', \'backlog\')"')
            ->set('activeTab', 'backlog')
            ->assertSee('WO 77777')
            ->assertSee('BACKLOG PROJECT ITEM')
            ->assertSee('Backlog');
    }

    public function test_client_brand_kit_defaults_and_helpers(): void
    {
        $client = Client::create(['name' => 'BRAND KIT TEST CLIENT']);

        $this->assertFalse($client->hasBrandKit());

        $kit = $client->getBrandKit();
        $this->assertIsArray($kit);
        $this->assertArrayHasKey('drive_url', $kit);
        $this->assertArrayHasKey('brandbook_url', $kit);
        $this->assertArrayHasKey('logo_url', $kit);
        $this->assertArrayHasKey('catalog_url', $kit);
        $this->assertArrayHasKey('colors', $kit);
        $this->assertArrayHasKey('fonts', $kit);
        $this->assertArrayHasKey('guidelines', $kit);
        $this->assertArrayHasKey('client_notes', $kit);
        $this->assertArrayHasKey('custom_links', $kit);

        $client->update([
            'brand_kit' => [
                'drive_url' => 'https://drive.google.com/test',
                'colors' => [['name' => 'Azul', 'hex' => '#0F172A', 'notes' => 'Principal']],
            ],
        ]);

        $client->refresh();
        $this->assertTrue($client->hasBrandKit());
        $this->assertEquals('https://drive.google.com/test', $client->getBrandKit()['drive_url']);
        $this->assertCount(1, $client->getBrandKit()['colors']);
    }

    public function test_can_manage_and_save_brand_kit_in_client_flyout(): void
    {
        $client = Client::create(['name' => 'FLYOUT BRAND KIT TEST']);

        Livewire::test(ClientFlyoutPanel::class)
            ->call('open', $client->id)
            ->set('activeTab', 'brand_kit')
            ->assertSee('Brand Kit')
            ->assertSee('Activos & Enlaces Clave')
            ->call('addBrandColor')
            ->set('brandKit.colors.0.name', 'Azul Corporativo')
            ->set('brandKit.colors.0.hex', '#0055FF')
            ->set('brandKit.colors.0.notes', 'Pantone 286 C')
            ->call('addBrandFont')
            ->set('brandKit.fonts.0.role', 'Titulares')
            ->set('brandKit.fonts.0.name', 'Montserrat Black')
            ->set('brandKit.fonts.0.notes', 'Siempre mayúsculas')
            ->set('brandKit.drive_url', 'https://drive.google.com/folder-123')
            ->set('brandKit.brandbook_url', 'https://figma.com/file-brandbook')
            ->set('brandKit.guidelines', 'Usar logo horizontal en uniformes')
            ->set('brandKit.client_notes', 'Don Carlos prefiere textos grandes')
            ->call('addCustomBrandLink')
            ->set('brandKit.custom_links.0.label', 'Fotos de Platos')
            ->set('brandKit.custom_links.0.url', 'G:\SALVA-MEX\FOTOS')
            ->set('brandKit.custom_links.0.notes', 'Servidor Kudos')
            ->call('save');

        $client->refresh();
        $kit = $client->getBrandKit();

        $this->assertEquals('https://drive.google.com/folder-123', $kit['drive_url']);
        $this->assertEquals('https://figma.com/file-brandbook', $kit['brandbook_url']);
        $this->assertEquals('Usar logo horizontal en uniformes', $kit['guidelines']);
        $this->assertEquals('Don Carlos prefiere textos grandes', $kit['client_notes']);
        $this->assertCount(1, $kit['colors']);
        $this->assertEquals('Azul Corporativo', $kit['colors'][0]['name']);
        $this->assertEquals('#0055FF', $kit['colors'][0]['hex']);
        $this->assertCount(1, $kit['fonts']);
        $this->assertEquals('Montserrat Black', $kit['fonts'][0]['name']);
        $this->assertCount(1, $kit['custom_links']);
        $this->assertEquals('G:\SALVA-MEX\FOTOS', $kit['custom_links'][0]['url']);
    }
}
