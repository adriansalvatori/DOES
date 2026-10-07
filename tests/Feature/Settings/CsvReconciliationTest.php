<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\CsvReconciliation;
use App\Models\Client;
use App\Models\Order;
use App\Models\Substatus;
use App\Models\User;
use App\Services\OrderReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class CsvReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $testDir = storage_path('framework/testing/reconciliation');
        if (file_exists($testDir.'/last_analysis.json')) {
            @unlink($testDir.'/last_analysis.json');
        }
    }

    protected function tearDown(): void
    {
        $testDir = storage_path('framework/testing/reconciliation');
        if (file_exists($testDir.'/last_analysis.json')) {
            @unlink($testDir.'/last_analysis.json');
        }
        parent::tearDown();
    }

    public function test_admin_can_access_csv_reconciliation_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/settings/csv-reconciliation');

        $response->assertStatus(200);
        $response->assertSee('Conciliación & Migración CSV');
        $response->assertSee('Cargar archivo CSV de órdenes');
    }

    public function test_non_admin_cannot_access_csv_reconciliation_page(): void
    {
        $designer = User::factory()->create(['role' => 'designer']);

        $response = $this->actingAs($designer)->get('/settings/csv-reconciliation');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/settings/csv-reconciliation');

        $response->assertRedirect('/login');
    }

    public function test_can_upload_and_analyze_csv_with_3_tier_classification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Order that will be Full Match (WO 13919, similar company and task)
        Order::create([
            'wo_number' => 'WO 13919',
            'company_name' => 'CHAVEZ PAINTING',
            'task_name' => 'PARTIAL VAN',
        ]);

        // 2. Order that will be Partial Match (WO 13500, but company is totally different)
        Order::create([
            'wo_number' => 'WO 13500',
            'company_name' => 'RESTAURANTE EL SOL',
            'task_name' => 'MENU IMPRESO',
        ]);

        // Substatuses in DB
        Substatus::firstOrCreate(['name' => 'ORDEN LISTA - ENTREGADA']);

        // Build sample CSV content
        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow1 = "2024-05-31,,13919,CHAVEZ PAINTING (CESAR CHAVEZ),PARTIAL VAN,cesar,DONE / CESAR,REVISED - 10348,2024/05/29,INSTALACION,KUDOS,TRUE,,ORDEN LISTA - ENTREGADA\n";
        $csvRow2 = "2024-02-01,,13500,SUPERMERCADO DIAZ,LETRERO ACRILICO,euraliz,DONE EU,REVISED - CS,2024/02/01,PICASSO,KUDOS,TRUE,,ORDEN LISTA\n";
        $csvRow3 = "2024-03-15,,99999,CLIENTE INEXISTENTE,NUEVO BANNER,,PENDIENTE,REVISED - 9999,2024/03/10,4OVER,KUDOS,FALSE,,ENTRANTE\n";

        $csvContent = $csvHeader.$csvRow1.$csvRow2.$csvRow3;
        $uploadedFile = UploadedFile::fake()->createWithContent('orders_test.csv', $csvContent);

        Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile)
            ->assertSet('activeTab', 'full_match')
            ->assertSee('Full Match')
            ->assertSee('Partial Match')
            ->assertSee('13919')
            ->assertSee('CHAVEZ PAINTING');
    }

    public function test_batch_approval_updates_orders_silently_and_preserves_company_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = Order::create([
            'wo_number' => 'WO 13919',
            'company_name' => 'ORIGINAL COMPANY NAME',
            'task_name' => 'ORIGINAL TASK NAME',
            'production_note' => 'Old note',
        ]);

        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        // CSV company is "ORIGINAL COMPANY NAME (CESAR)" which has high match
        $csvRow = "2024-05-31,GARANTIA,13919,ORIGINAL COMPANY NAME (CESAR),ORIGINAL TASK NAME,cesar,Nueva nota de produccion,REVISED - 10348,2024/05/29,4OVER,KUDOS,TRUE,Nota entrega,ORDEN LISTA\n";

        $csvContent = $csvHeader.$csvRow;
        $uploadedFile = UploadedFile::fake()->createWithContent('orders_batch_test.csv', $csvContent);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        $component->call('openBatchConfirm', 'all_full')
            ->call('executeBatchApproval')
            ->assertSee('actualizaron exitosamente');

        $order->refresh();

        // 1. Company and Task names MUST remain untouched
        $this->assertEquals('ORIGINAL COMPANY NAME', $order->company_name);
        $this->assertEquals('ORIGINAL TASK NAME', $order->task_name);

        // 2. Production note should overwrite with CSV note
        $this->assertStringContainsString('Nueva nota de produccion', $order->production_note);

        // 3. Date anomaly "GARANTIA" was text -> delivery_due_date is null and text is appended in parentheses
        $this->assertNull($order->delivery_due_date);
        $this->assertStringContainsString('(Fecha entrega: GARANTIA)', $order->delivery_note);

        // 4. Estimate was cleaned from "REVISED - 10348"
        $this->assertEquals('10348', $order->estimate_invoice_number);

        // 5. Inactive designer created/assigned for César
        $this->assertNotNull($order->designer_id);
    }

    public function test_partial_match_approval_and_discard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = Order::create([
            'wo_number' => 'WO 13500',
            'company_name' => 'RESTAURANTE EL SOL',
            'task_name' => 'MENU IMPRESO',
        ]);

        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-02-01,,13500,SUPERMERCADO DIAZ,LETRERO ACRILICO,euraliz,Nota partial,REVISED - CS,2024/02/01,PICASSO,KUDOS,TRUE,,ORDEN LISTA\n";

        $csvContent = $csvHeader.$csvRow;
        $uploadedFile = UploadedFile::fake()->createWithContent('orders_partial_test.csv', $csvContent);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile)
            ->assertSet('activeTab', 'partial_match')
            ->assertSee('SUPERMERCADO DIAZ')
            ->assertSee('RESTAURANTE EL SOL');

        // Approve this partial match
        $component->call('approveSinglePartial', 'row_1')
            ->assertSee('aprobada y actualizada correctamente');

        $order->refresh();
        $this->assertEquals('RESTAURANTE EL SOL', $order->company_name); // unchanged
        $this->assertStringContainsString('Nota partial', $order->production_note);
        $this->assertEquals('CS', $order->review_status);
    }

    public function test_link_unmatched_order_to_trello_card(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-03-15,,88888,EMPRESA NUEVA DE PRUEBA,TAREA DESDE TRELLO,cesar,Nota nueva,REVISED - 10999,2024/03/10,4OVER,KUDOS,TRUE,Entrega ok,ORDEN LISTA\n";

        $csvContent = $csvHeader.$csvRow;
        $uploadedFile = UploadedFile::fake()->createWithContent('orders_unmatched_test.csv', $csvContent);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile)
            ->set('activeTab', 'unmatched');

        // Fake preview data manually simulating verification
        $component->set('trelloCardPreview.row_1', [
            'id' => '65abc1234567890123456789',
            'name' => 'Tarjeta Trello Nueva',
            'desc' => 'Descripción de prueba',
            'url' => 'https://trello.com/c/testCard123',
        ]);

        $component->call('linkAndApproveTrello', 'row_1')
            ->assertSee('creada y vinculada con Trello exitosamente');

        $this->assertDatabaseHas('orders', [
            'trello_card_id' => '65abc1234567890123456789',
            'in_workspace' => false, // strictly in backlog inbox
            'company_name' => 'EMPRESA NUEVA DE PRUEBA',
            'task_name' => 'TAREA DESDE TRELLO',
            'estimate_invoice_number' => '10999',
        ]);
    }

    public function test_continuous_list_load_more_and_set_per_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $rows = '';
        for ($i = 1; $i <= 60; $i++) {
            $rows .= "2024-03-15,,{$i},EMPRESA {$i},TAREA {$i},cesar,Nota {$i},1000{$i},2024/03/10,4OVER,KUDOS,TRUE,,ORDEN LISTA\n";
        }

        $uploadedFile = UploadedFile::fake()->createWithContent('orders_many.csv', $csvHeader.$rows);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        $this->assertEquals(50, $component->get('perPage'));

        // Load more adds +50
        $component->call('loadMore');
        $this->assertEquals(100, $component->get('perPage'));

        // Can switch to all
        $component->call('setPerPage', 'all');
        $this->assertEquals('all', $component->get('perPage'));
    }

    public function test_exact_wo_match_is_full_match_despite_different_task_wording(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = Order::create([
            'wo_number' => 'WO 20202',
            'company_name' => 'ALEVA GROCERIES',
            'task_name' => 'WINDOW PERF 50/50',
        ]);

        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-05-01,,20202,ALEVA GROCERIES,VINILO MICROPERFORADO VENTANAS,cesar,Nota,1001,2024/05/01,INSTALACION,KUDOS,TRUE,,ORDEN LISTA\n";

        $uploadedFile = UploadedFile::fake()->createWithContent('orders_wo_primacy.csv', $csvHeader.$csvRow);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile)
            ->assertSet('activeTab', 'full_match')
            ->assertSee('20202')
            ->assertSee('ALEVA GROCERIES');

        $rows = $component->viewData('paginatedRows');
        $this->assertCount(1, $rows);
        $this->assertEquals(100.0, $rows[0]['similarity']);
        $this->assertEquals($order->id, $rows[0]['order_id']);
    }

    public function test_client_entity_mapping_with_typo_tolerance_and_auto_learning(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'name' => 'ALEVA GROCERIES',
            'aliases' => [],
        ]);

        $order = Order::create([
            'wo_number' => 'WO 30303',
            'company_name' => 'ALEVA GROCERIES',
            'task_name' => 'BANNER PROMO',
            'client_id' => null,
        ]);

        // CSV has a typo: "Aleva Grocerys"
        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-05-10,,30303,Aleva Grocerys,BANNER PROMO,cesar,Nota,1002,2024/05/10,4OVER,KUDOS,TRUE,,ORDEN LISTA\n";

        $uploadedFile = UploadedFile::fake()->createWithContent('orders_typo.csv', $csvHeader.$csvRow);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        $rows = $component->viewData('paginatedRows');
        $this->assertCount(1, $rows);
        $this->assertEquals('ALEVA GROCERIES', $rows[0]['resolved_client_name']);
        $this->assertTrue($rows[0]['typo_detected']);

        // Execute batch approval
        $component->call('openBatchConfirm', 'all_full')
            ->call('executeBatchApproval');

        // Order has client_id set
        $order->refresh();
        $this->assertEquals($client->id, $order->client_id);

        // Client has auto-learned the typo alias
        $client->refresh();
        $this->assertContains('Aleva Grocerys', $client->aliases);
    }

    public function test_smart_pattern_extraction_for_contacts_locations_and_task_prefix(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = Order::create([
            'wo_number' => 'WO 40404',
            'company_name' => 'POLLO CAMPERO',
            'task_name' => 'BANNER EXTERIOR',
        ]);

        // Raw CSV contains location "- DULUTH", contact "(JUAN PEREZ)", alias '"EL SABROSO"', and redundant task prefix
        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-06-01,,40404,POLLO CAMPERO - DULUTH (JUAN PEREZ) \"EL SABROSO\",POLLO CAMPERO - BANNER EXTERIOR,cesar,Nota,1003,2024/06/01,4OVER,KUDOS,TRUE,,ORDEN LISTA\n";

        $uploadedFile = UploadedFile::fake()->createWithContent('orders_patterns.csv', $csvHeader.$csvRow);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        $rows = $component->viewData('paginatedRows');
        $this->assertCount(1, $rows);
        $this->assertEquals('DULUTH', $rows[0]['extracted_location']);
        $this->assertEquals('JUAN PEREZ', $rows[0]['extracted_contact']);
        $this->assertEquals('EL SABROSO', $rows[0]['extracted_alias']);
        $this->assertEquals('POLLO CAMPERO', $rows[0]['clean_company']);
        $this->assertEquals('BANNER EXTERIOR', $rows[0]['clean_task']);

        // Execute batch approval
        $component->call('openBatchConfirm', 'all_full')
            ->call('executeBatchApproval');

        $order->refresh();
        $this->assertEquals('JUAN PEREZ', $order->responsible_person);
        $this->assertEquals('DULUTH', $order->location_name);
        $this->assertEquals('POLLO CAMPERO', $order->company_name); // Unmodified
    }

    public function test_quick_create_client_action_updates_database_and_cache(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Unmatched order with new company
        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-07-01,,50505,TAQUERIA LOS AMIGOS (MARIA),LETRERO COROPLAST,cesar,Nota,1004,2024/07/01,4OVER,KUDOS,TRUE,,ORDEN LISTA\n";

        $uploadedFile = UploadedFile::fake()->createWithContent('orders_quick_client.csv', $csvHeader.$csvRow);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        $this->assertDatabaseMissing('clients', [
            'name' => 'TAQUERIA LOS AMIGOS',
        ]);

        // Call quickCreateClient
        $component->call('quickCreateClient', 'row_1')
            ->assertSee('registrado exitosamente');

        $this->assertDatabaseHas('clients', [
            'name' => 'TAQUERIA LOS AMIGOS',
        ]);

        $client = Client::where('name', 'TAQUERIA LOS AMIGOS')->first();
        $this->assertNotNull($client);

        // Rows in cache now reflect the new client
        $rows = $component->viewData('paginatedRows');
        $this->assertEquals('TAQUERIA LOS AMIGOS', $rows[0]['resolved_client_name']);
        $this->assertEquals($client->id, $rows[0]['resolved_client_id']);
    }

    public function test_secondary_matching_blocks_incompatible_task_categories_even_for_same_client(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $client = Client::create([
            'name' => 'ALEVA GROCERIES',
            'aliases' => [],
        ]);

        // Existing DB order: Embroidery job
        Order::create([
            'wo_number' => 'WO 15308',
            'client_id' => $client->id,
            'company_name' => 'ALEVA GROCERIES',
            'task_name' => 'EMBROIDERY SERVICE - ALTA',
            'production_processed_at' => '2025-10-04',
        ]);

        // CSV row without matching WO: Signage/Storefront job for the same client, 2 days apart
        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2025-10-02,, ,ALEVA GROCERIES (BALDEMAR),VARIOS DE LOCACION - BIENVENIDO CON PAJARO (SALA DE VENTAS),euraliz,DONE,CS - 11396,2025-08-30,INSTALACION,KUDOS,TRUE,,ORDEN LISTA\n";

        $uploadedFile = UploadedFile::fake()->createWithContent('orders_incompatible_category.csv', $csvHeader.$csvRow);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        $meta = $component->get('meta');

        // It must NOT match as partial match (0 partial matches)
        $this->assertEquals(0, $meta['partial_match_count']);
        // It must cleanly land in Unmatched (Tab 3)
        $this->assertEquals(1, $meta['unmatched_count']);

        // Switching to unmatched tab shows this row
        $component->set('activeTab', 'unmatched');
        $unmatchedRows = $component->viewData('paginatedRows');
        $this->assertCount(1, $unmatchedRows);
        $this->assertEquals('ALEVA GROCERIES', $unmatchedRows[0]['resolved_client_name']);
        $this->assertStringContainsString('VARIOS DE LOCACION', $unmatchedRows[0]['csv_task']);
    }

    public function test_auto_search_trello_finds_cards_and_detects_existing_order_deduplication(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Fake Trello search API (mock boards endpoint to 500 to exercise search fallback)
        Http::fake([
            'https://api.trello.com/1/boards/*' => Http::response([], 500),
            'https://api.trello.com/1/search*' => Http::response([
                'cards' => [
                    [
                        'id' => 'card_trello_777',
                        'name' => 'WO 77777 - TAQUERIA EL REY - MENU',
                        'desc' => 'Descripción de prueba',
                        'closed' => false,
                        'shortUrl' => 'https://trello.com/c/card777',
                    ],
                ],
            ], 200),
        ]);

        // Existing Order in database with that Trello Card ID
        $existingOrder = Order::create([
            'trello_card_id' => 'card_trello_777',
            'wo_number' => null, // missing WO in DB
            'company_name' => 'TAQUERIA EL REY',
            'task_name' => 'MENU ACRILICO',
            'in_workspace' => true,
        ]);

        // CSV row for this order that lands in unmatched
        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-08-01,,77777,TAQUERIA EL REY,MENU ACRILICO,cesar,Nota produccion,11777,2024/08/01,4OVER,KUDOS,TRUE,,ORDEN LISTA\n";

        $uploadedFile = UploadedFile::fake()->createWithContent('orders_trello_dedup.csv', $csvHeader.$csvRow);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        // Run auto-search across Trello
        $component->call('autoSearchAllTrello');

        $previews = $component->get('trelloCardPreview');
        $this->assertArrayHasKey('row_1', $previews);
        $preview = $previews['row_1'];

        // Fail-safe must detect existing order and mark as reuse_existing!
        $this->assertEquals('card_trello_777', $preview['id']);
        $this->assertEquals('reuse_existing', $preview['dedup_action']);
        $this->assertEquals($existingOrder->id, $preview['existing_order_id']);

        // Link and approve
        $component->call('linkAndApproveTrello', 'row_1');

        // Zero-Duplicate verification: Orders count MUST remain exactly 1!
        $this->assertEquals(1, Order::count());

        $existingOrder->refresh();
        $this->assertEquals('WO 77777', $existingOrder->wo_number); // Repaired WO
        $this->assertEquals('card_trello_777', $existingOrder->trello_card_id);
    }

    public function test_link_all_found_trello_cards_batch_execution(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Unmatched CSV with 2 rows
        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow1 = "2024-08-10,,88001,EMPRESA A,TAREA A,cesar,Nota A,11801,2024/08/10,4OVER,KUDOS,TRUE,,ORDEN LISTA\n";
        $csvRow2 = "2024-08-12,,88002,EMPRESA B,TAREA B,cesar,Nota B,11802,2024/08/12,4OVER,KUDOS,TRUE,,ORDEN LISTA\n";

        $uploadedFile = UploadedFile::fake()->createWithContent('orders_trello_batch.csv', $csvHeader.$csvRow1.$csvRow2);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        // Manually simulate preview found for both rows
        $service = app(OrderReconciliationService::class);
        $cached = $service->getCachedAnalysis();
        $cached['unmatched'][0]['suggested_trello_card'] = [
            'id' => 'card_batch_1',
            'name' => 'WO 88001 - EMPRESA A',
            'desc' => '',
            'url' => 'https://trello.com/c/batch1',
            'is_closed' => false,
            'dedup_action' => 'create_new',
            'dedup_reason' => 'Nueva orden',
            'existing_order_id' => null,
        ];
        $cached['unmatched'][1]['suggested_trello_card'] = [
            'id' => 'card_batch_2',
            'name' => 'WO 88002 - EMPRESA B',
            'desc' => '',
            'url' => 'https://trello.com/c/batch2',
            'is_closed' => true,
            'dedup_action' => 'create_new',
            'dedup_reason' => 'Nueva orden archivada',
            'existing_order_id' => null,
        ];

        // Store into cache file via service
        $service->storeAnalysisCache($cached);

        // Call linkAllFoundTrello
        $component->call('linkAllFoundTrello')
            ->assertSee('vincularon exitosamente');

        $this->assertDatabaseHas('orders', ['wo_number' => 'WO 88001', 'trello_card_id' => 'card_batch_1']);
        $this->assertDatabaseHas('orders', ['wo_number' => 'WO 88002', 'trello_card_id' => 'card_batch_2']);
    }

    public function test_auto_search_trello_uses_bulk_board_cards_indexer_without_timeout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Fake Trello board cards API returning all cards in 1 call
        Http::fake([
            'https://api.trello.com/1/boards/*/cards*' => Http::response([
                [
                    'id' => 'card_bulk_1',
                    'name' => 'WO 99001 - NIKE STORE - VINILO',
                    'desc' => 'Descripción 1',
                    'closed' => false,
                    'shortUrl' => 'https://trello.com/c/nike99001',
                    'dateLastActivity' => '2024-06-01T12:00:00.000Z',
                ],
                [
                    'id' => 'card_bulk_2',
                    'name' => 'WO# 99002 - ADIDAS OUTLET',
                    'desc' => 'Archivada anteriormente',
                    'closed' => true,
                    'shortUrl' => 'https://trello.com/c/adidas99002',
                    'dateLastActivity' => '2023-11-01T10:00:00.000Z',
                ],
            ], 200),
        ]);

        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow1 = "2024-08-20,,99001,NIKE STORE,VINILO VENTANA,cesar,Nota 1,19001,2024/08/20,4OVER,KUDOS,TRUE,,ORDEN LISTA\n";
        $csvRow2 = "2024-08-21,,99002,ADIDAS OUTLET,BANNER PROMO,cesar,Nota 2,19002,2024/08/21,4OVER,KUDOS,TRUE,,ORDEN LISTA\n";

        $uploadedFile = UploadedFile::fake()->createWithContent('orders_bulk_trello.csv', $csvHeader.$csvRow1.$csvRow2);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        // Run auto-search across Trello
        $component->call('autoSearchAllTrello');

        $previews = $component->get('trelloCardPreview');
        $this->assertArrayHasKey('row_1', $previews);
        $this->assertArrayHasKey('row_2', $previews);

        // Row 1 matches active card
        $this->assertEquals('card_bulk_1', $previews['row_1']['id']);
        $this->assertFalse($previews['row_1']['is_closed']);

        // Row 2 matches archived card
        $this->assertEquals('card_bulk_2', $previews['row_2']['id']);
        $this->assertTrue($previews['row_2']['is_closed']);

        // Verified: boards endpoint was called with filter=all
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/boards/')
                && str_contains($request->url(), '/cards')
                && ($request['filter'] ?? '') === 'all';
        });

        // Search API was NOT spammed with multiple individual requests
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), '/search');
        });
    }

    public function test_smart_merge_task_names_harmonizes_db_and_csv_without_losing_details(): void
    {
        $service = app(OrderReconciliationService::class);

        // Case 1: User's exact example (DB dimension + CSV quantity & descriptor)
        $res1 = $service->mergeTaskNames('sticker 2 x 3', 'sticker qty. 500 comida rapida');
        $this->assertEquals('Sticker Comida Rapida 2x3 Qty. 500', $res1);

        // Case 2: DB dimension + CSV descriptor & finish
        $res2 = $service->mergeTaskNames('BANNER 3X5', 'BANNER GRAND OPENING CON OJALILLOS');
        $this->assertEquals('Banner Grand Opening Con Ojalillos 3x5', $res2);

        // Case 3: DB material + CSV descriptor & dimension
        $res3 = $service->mergeTaskNames('MENU ACRILICO', 'MENU TACOS Y BEBIDAS 24X36');
        $this->assertEquals('Menu Tacos Y Bebidas Acrilico 24x36', $res3);

        // Case 4: Parenthetical notes preserved
        $res4 = $service->mergeTaskNames('COROPLAST', 'COROPLAST 18X24 QTY 25 HORARIOS (SALA DE VENTAS)');
        $this->assertEquals('Coroplast Horarios 18x24 Qty. 25 (Sala De Ventas)', $res4);

        // Case 5: Identical returns intact
        $res5 = $service->mergeTaskNames('LETRERO 18X24', 'LETRERO 18X24');
        $this->assertEquals('LETRERO 18X24', $res5);
    }

    public function test_batch_approval_applies_smart_merged_task_name_to_database(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $order = Order::create([
            'wo_number' => 'WO 11223',
            'company_name' => 'TAQUERIA EL REY',
            'task_name' => 'sticker 2 x 3',
        ]);

        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-09-01,,11223,TAQUERIA EL REY,sticker qty. 500 comida rapida,cesar,Nota,21223,2024/09/01,4OVER,KUDOS,TRUE,,ORDEN LISTA\n";

        $uploadedFile = UploadedFile::fake()->createWithContent('orders_smart_merge.csv', $csvHeader.$csvRow);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        $meta = $component->get('meta');
        $this->assertEquals(1, $meta['full_match_count']);

        // Assert diff proposed smart merged task name
        $fullMatches = $component->viewData('paginatedRows');
        $this->assertArrayHasKey('task_name', $fullMatches[0]['diffs']);
        $this->assertTrue($fullMatches[0]['diffs']['task_name']['is_smart_merge']);
        $this->assertEquals('Sticker Comida Rapida 2x3 Qty. 500', $fullMatches[0]['diffs']['task_name']['proposed']);

        // Execute batch approval
        $component->call('openBatchConfirm', 'all_full')
            ->call('executeBatchApproval');

        $order->refresh();
        // Task name must be enriched and matches Order uppercase convention!
        $this->assertEquals('STICKER COMIDA RAPIDA 2X3 QTY. 500', $order->task_name);
        // Company name must remain strictly intact!
        $this->assertEquals('TAQUERIA EL REY', $order->company_name);
    }

    public function test_active_tab_persists_via_url_and_smart_fallback_when_full_match_is_empty(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create a fake cached analysis where full_match_count is 0 but partial_match_count is 1
        $testDir = storage_path('framework/testing/reconciliation');
        File::ensureDirectoryExists($testDir);
        $mockAnalysis = [
            'meta' => [
                'file_name' => 'test.csv',
                'total_rows' => 10,
                'full_match_count' => 0,
                'partial_match_count' => 1,
                'unmatched_count' => 9,
                'anomalies_count' => 0,
                'analyzed_at' => now()->toIso8601String(),
            ],
            'full_matches' => [],
            'partial_matches' => [
                [
                    'row_id' => 'row_1',
                    'wo_number' => 'WO 9999',
                    'db_company' => 'COMPANY A',
                    'csv_company' => 'COMPANY B',
                    'similarity' => 60,
                    'reason' => 'Diferencia en empresa',
                    'parsed_data' => ['raw_wo' => '9999'],
                ],
            ],
            'unmatched' => [],
            'anomalies' => [],
        ];
        File::put($testDir.'/last_analysis.json', json_encode($mockAnalysis));

        // When mounting without query params, it should automatically route to partial_match instead of showing an empty full_match
        $component = Livewire::actingAs($admin)->test(CsvReconciliation::class);
        $this->assertEquals('partial_match', $component->get('activeTab'));
        $this->assertTrue($component->get('hasAnalysis'));

        // If the user explicitly sets tab to unmatched, activeTab should be unmatched
        $componentWithUrl = Livewire::actingAs($admin)
            ->withQueryParams(['tab' => 'unmatched'])
            ->test(CsvReconciliation::class);
        $this->assertEquals('unmatched', $componentWithUrl->get('activeTab'));
    }

    public function test_can_filter_unmatched_orders_by_trello_card_match(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $testDir = storage_path('framework/testing/reconciliation');
        File::ensureDirectoryExists($testDir);
        $mockAnalysis = [
            'meta' => [
                'file_name' => 'test.csv',
                'total_rows' => 2,
                'full_match_count' => 0,
                'partial_match_count' => 0,
                'unmatched_count' => 2,
                'anomalies_count' => 0,
                'analyzed_at' => now()->toIso8601String(),
            ],
            'full_matches' => [],
            'partial_matches' => [],
            'unmatched' => [
                [
                    'row_id' => 'row_1',
                    'raw_wo' => '1001',
                    'csv_company' => 'COMPANY MATCH',
                    'csv_task' => 'BANNER',
                    'parsed_data' => ['raw_wo' => '1001'],
                    'suggested_trello_card' => [
                        'id' => 'card_123',
                        'name' => 'WO 1001 - BANNER',
                        'url' => 'https://trello.com/c/card_123',
                    ],
                ],
                [
                    'row_id' => 'row_2',
                    'raw_wo' => '1002',
                    'csv_company' => 'COMPANY NO MATCH',
                    'csv_task' => 'STICKERS',
                    'parsed_data' => ['raw_wo' => '1002'],
                    'suggested_trello_card' => null,
                ],
            ],
            'anomalies' => [],
        ];
        File::put($testDir.'/last_analysis.json', json_encode($mockAnalysis));

        $component = Livewire::actingAs($admin)
            ->withQueryParams(['tab' => 'unmatched'])
            ->test(CsvReconciliation::class);

        // All filter: 2 rows
        $this->assertCount(2, $component->viewData('paginatedRows'));

        // Filter: with_match -> only row_1
        $component->call('setUnmatchedFilter', 'with_match');
        $rowsWithMatch = $component->viewData('paginatedRows');
        $this->assertCount(1, $rowsWithMatch);
        $this->assertEquals('row_1', $rowsWithMatch[0]['row_id']);

        // Filter: without_match -> only row_2
        $component->call('setUnmatchedFilter', 'without_match');
        $rowsWithoutMatch = $component->viewData('paginatedRows');
        $this->assertCount(1, $rowsWithoutMatch);
        $this->assertEquals('row_2', $rowsWithoutMatch[0]['row_id']);
    }

    public function test_admin_can_resolve_wo_conflict_by_reassigning_db_wo_and_creating_csv_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // DB order has WO 13500 for RESTAURANTE EL SOL
        $dbOrder = Order::create([
            'wo_number' => 'WO 13500',
            'company_name' => 'RESTAURANTE EL SOL',
            'task_name' => 'MENU IMPRESO',
        ]);

        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-02-01,,13500,SUPERMERCADO DIAZ,LETRERO ACRILICO,euraliz,Nota nueva,REVISED - CS,2024/02/01,PICASSO,KUDOS,TRUE,,ORDEN LISTA\n";
        $csvContent = $csvHeader.$csvRow;
        $uploadedFile = UploadedFile::fake()->createWithContent('orders_wo_conflict.csv', $csvContent);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile)
            ->assertSet('activeTab', 'partial_match');

        // Toggle reassign form for row_1
        $component->call('toggleReassignWo', 'row_1')
            ->assertSet('activeReassignRows.row_1', true);

        // Assign new WO 13599 to the existing DB order
        $component->set('reassignWoInputs.row_1', '13599')
            ->call('executeReassignWo', 'row_1')
            ->assertDispatched('toast');

        // Check DB order was reassigned to 13599
        $dbOrder->refresh();
        $this->assertEquals('WO 13599', $dbOrder->wo_number);
        $this->assertEquals('RESTAURANTE EL SOL', $dbOrder->company_name);

        // Check new order was created in DB with WO 13500 for SUPERMERCADO DIAZ
        $newOrder = Order::where('wo_number', 'WO 13500')->first();
        $this->assertNotNull($newOrder);
        $this->assertEquals('SUPERMERCADO DIAZ', $newOrder->company_name);
        $this->assertEquals('LETRERO ACRILICO', $newOrder->task_name);

        // Conflict is resolved from partial_match
        $meta = $component->get('meta');
        $this->assertEquals(0, $meta['partial_match_count']);
    }

    public function test_admin_can_resolve_wo_conflict_by_leaving_db_order_without_wo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $dbOrder = Order::create([
            'wo_number' => 'WO 13500',
            'company_name' => 'RESTAURANTE EL SOL',
            'task_name' => 'MENU IMPRESO',
        ]);

        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-02-01,,13500,SUPERMERCADO DIAZ,LETRERO ACRILICO,euraliz,Nota nueva,REVISED - CS,2024/02/01,PICASSO,KUDOS,TRUE,,ORDEN LISTA\n";
        $uploadedFile = UploadedFile::fake()->createWithContent('orders_wo_conflict_empty.csv', $csvHeader.$csvRow);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        // Submit empty input -> leaves DB order without WO
        $component->set('reassignWoInputs.row_1', '')
            ->call('executeReassignWo', 'row_1')
            ->assertDispatched('toast');

        $dbOrder->refresh();
        $this->assertNull($dbOrder->wo_number);

        // New order gets WO 13500
        $this->assertDatabaseHas('orders', [
            'wo_number' => 'WO 13500',
            'company_name' => 'SUPERMERCADO DIAZ',
        ]);
    }

    public function test_cannot_reassign_db_wo_to_an_already_taken_number(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Order 1 has WO 13500 (conflict)
        Order::create([
            'wo_number' => 'WO 13500',
            'company_name' => 'RESTAURANTE EL SOL',
            'task_name' => 'MENU IMPRESO',
        ]);

        // Order 2 already has WO 13599
        Order::create([
            'wo_number' => 'WO 13599',
            'company_name' => 'OTRA EMPRESA',
            'task_name' => 'OTRA TAREA',
        ]);

        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-02-01,,13500,SUPERMERCADO DIAZ,LETRERO ACRILICO,euraliz,Nota nueva,REVISED - CS,2024/02/01,PICASSO,KUDOS,TRUE,,ORDEN LISTA\n";
        $uploadedFile = UploadedFile::fake()->createWithContent('orders_conflict_taken.csv', $csvHeader.$csvRow);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        // Try to reassign to 13599 (which is already taken)
        $component->set('reassignWoInputs.row_1', '13599')
            ->call('executeReassignWo', 'row_1')
            ->assertSee('ya está asignado a otra orden');

        // Partial match count must remain 1 (not resolved)
        $meta = $component->get('meta');
        $this->assertEquals(1, $meta['partial_match_count']);
    }

    public function test_orders_sharing_same_estimate_do_not_falsely_match_without_wo(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Existing DB order with estimate 10999
        Order::create([
            'wo_number' => 'WO 15001',
            'company_name' => 'ACME CORP',
            'task_name' => 'LETRERO EXTERIOR',
            'estimate_invoice_number' => '10999',
        ]);

        // CSV row WITHOUT WO that shares the same estimate 10999, but is a different order
        $csvHeader = ".production_processed_at,delivery_due_date,wo_number,company_name: fuer,task_name,designer_id,production_note,estimate_invoice_number,email_date,installation_type\t,Installation,overview_checked,delivery_note,substatus\n";
        $csvRow = "2024-02-01,, ,ACME CORP,BANNER ROLLUP,euraliz,Nota,REVISED - 10999,2024/02/01,PICASSO,KUDOS,TRUE,,ORDEN LISTA\n";
        $uploadedFile = UploadedFile::fake()->createWithContent('orders_estimate_no_match.csv', $csvHeader.$csvRow);

        $component = Livewire::actingAs($admin)
            ->test(CsvReconciliation::class)
            ->set('csvFile', $uploadedFile);

        $meta = $component->get('meta');
        // Because estimate matching was disabled, it must NOT match into partial matches
        $this->assertEquals(0, $meta['partial_match_count']);
        // It lands cleanly in unmatched (for Trello linking)
        $this->assertEquals(1, $meta['unmatched_count']);
    }
}
