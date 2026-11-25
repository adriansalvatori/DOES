<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Livewire\Settings\TrelloSync;
use App\Models\Client;
use App\Models\ClientLink;
use App\Models\ClientLocation;
use App\Models\Contact;
use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Characterizes destructive maintenance commands. Safe only because the test database is the
 * in-memory SQLite default connection (phpunit.xml); never run these commands against a real DB.
 */
class SupportCommandsTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrderGraph(): Order
    {
        $order = Order::create(['company_name' => 'CLEAR CO', 'task_name' => 'Task', 'core_status' => CoreStatus::TO_DO_TODAY, 'in_workspace' => true]);
        RelatedTask::create(['order_id' => $order->id, 'title' => 'Sub', 'status' => 'todo']);
        DueDateHistory::create(['order_id' => $order->id, 'previous_due_date' => now()->toDateString(), 'new_due_date' => now()->addDay()->toDateString(), 'reason' => 'x']);

        return $order;
    }

    public function test_the_test_database_is_the_in_memory_default_connection(): void
    {
        $this->assertSame('sqlite', DB::getDefaultConnection());
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_orders_clear_soft_deletes_orders_and_tasks_and_removes_events_and_history_on_the_default_connection(): void
    {
        $order = $this->seedOrderGraph();
        $client = Client::create(['name' => 'KEEP ME']);
        $this->assertGreaterThan(0, OrderEvent::count());

        $this->artisan('orders:clear')->assertExitCode(0);

        $this->assertSame(0, Order::count());
        $this->assertSame(1, Order::withTrashed()->count());
        $this->assertSame(0, RelatedTask::count());
        $this->assertSame(1, RelatedTask::withTrashed()->count());
        $this->assertSame(0, OrderEvent::count());
        $this->assertSame(0, DueDateHistory::count());
        $this->assertNotNull(Client::find($client->id));
        $this->assertSame(1, DB::connection(DB::getDefaultConnection())->table('orders')->whereNotNull('deleted_at')->count());
    }

    public function test_settings_clear_demo_data_does_the_same_as_the_command(): void
    {
        $this->seedOrderGraph();
        $this->actingAs(User::factory()->create());

        Livewire::test(TrelloSync::class)->call('clearDemoData');

        $this->assertSame(0, Order::count());
        $this->assertSame(1, Order::withTrashed()->count());
        $this->assertSame(0, RelatedTask::count());
        $this->assertSame(0, OrderEvent::count());
        $this->assertSame(0, DueDateHistory::count());
    }

    public function test_clients_consolidate_wipes_contacts_locations_and_links_and_rebuilds_from_workspace_orders_only(): void
    {
        $manualClient = Client::create(['name' => 'MANUAL CLIENT']);
        $manualLocation = ClientLocation::create(['client_id' => $manualClient->id, 'name' => 'MANUAL LOC', 'address' => 'x']);
        $manualContact = Contact::create(['name' => 'MANUAL PERSON', 'preferred_channel' => 'any', 'preferred_language' => 'es']);
        $manualClient->contacts()->attach($manualContact->id, ['is_primary' => true]);
        ClientLink::create(['client_id' => $manualClient->id, 'label' => 'Drive', 'url' => 'https://example.test']);

        $workspace = Order::create(['company_name' => 'Fuerza Latina REF El Sol', 'responsible_person' => 'Maria Lopez', 'task_name' => 'a', 'in_workspace' => true, 'core_status' => CoreStatus::TO_DO_TODAY, 'client_id' => $manualClient->id]);
        $backlog = Order::create(['company_name' => 'Backlog Only', 'task_name' => 'b', 'in_workspace' => false, 'core_status' => CoreStatus::ENTRANTE, 'client_id' => $manualClient->id]);

        $this->artisan('clients:consolidate')->assertExitCode(0);

        $this->assertNull(Client::withTrashed()->find($manualClient->id));
        $this->assertNull(ClientLocation::withTrashed()->find($manualLocation->id));
        $this->assertNull(Contact::withTrashed()->where('name', 'MANUAL PERSON')->first());
        $this->assertSame(0, ClientLink::count());

        $this->assertSame(['FUERZA LATINA'], Client::pluck('name')->all());
        $this->assertSame(['EL SOL'], ClientLocation::pluck('name')->all());
        $this->assertSame(['MARIA LOPEZ'], Contact::pluck('name')->all());

        $workspace->refresh();
        $this->assertNotNull($workspace->client_id);
        $this->assertNotNull($workspace->client_location_id);
        $this->assertSame('FUERZA LATINA', $workspace->company_name);

        $backlog->refresh();
        $this->assertNull($backlog->client_id);
        $this->assertNull($backlog->client_location_id);
        $this->assertSame('BACKLOG ONLY', $backlog->company_name);
    }
}
