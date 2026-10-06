<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\CsvReconciliation;
use App\Models\Order;
use App\Models\Substatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class CsvReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (file_exists(storage_path('app/reconciliation/last_analysis.json'))) {
            @unlink(storage_path('app/reconciliation/last_analysis.json'));
        }
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
}
