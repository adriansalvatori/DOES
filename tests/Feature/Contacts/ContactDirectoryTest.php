<?php

namespace Tests\Feature\Contacts;

use App\Enums\CoreStatus;
use App\Livewire\Contacts\ContactFlyoutPanel;
use App\Livewire\Contacts\ContactIndex;
use App\Livewire\Orders\CreateOrderModal;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Order;
use App\Models\User;
use App\Services\ClientMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContactDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_contact_directory_page_is_accessible_to_authenticated_users(): void
    {
        $contact = Contact::create([
            'name' => 'VALENTINA MORALES',
            'phone' => '(770) 555-1234',
            'email' => 'valentina@empresa.com',
            'preferred_channel' => 'whatsapp',
            'preferred_language' => 'es',
        ]);

        $this->actingAs($this->user)
            ->get('/contacts')
            ->assertStatus(200)
            ->assertSee('Directorio de Clientes')
            ->assertSee('VALENTINA MORALES');
    }

    public function test_can_create_new_contact_with_preferences_and_linked_clients(): void
    {
        $client1 = Client::create(['name' => 'DON PAN BAKERY']);
        $client2 = Client::create(['name' => 'BURGER SPOT']);

        Livewire::actingAs($this->user)
            ->test(ContactFlyoutPanel::class)
            ->call('open')
            ->set('name', 'JULIANA GOMEZ')
            ->set('phone', '7708649359')
            ->set('email', 'juliana@empresa.com')
            ->set('role_title', 'Brand Manager')
            ->set('preferred_channel', 'whatsapp')
            ->set('preferred_language', 'both')
            ->set('notes', 'Prefiere mensajes breves')
            ->call('toggleClientLink', $client1->id)
            ->call('toggleClientLink', $client2->id)
            ->call('save')
            ->assertDispatched('contact-updated');

        $this->assertDatabaseHas('contacts', [
            'name' => 'JULIANA GOMEZ',
            'phone' => '(770) 864-9359',
            'email' => 'juliana@empresa.com',
            'role_title' => 'Brand Manager',
            'preferred_channel' => 'whatsapp',
            'preferred_language' => 'both',
            'notes' => 'Prefiere mensajes breves',
        ]);

        $contact = Contact::where('name', 'JULIANA GOMEZ')->first();
        $this->assertCount(2, $contact->clients);
        $this->assertTrue($contact->clients->pluck('id')->contains($client1->id));
        $this->assertTrue($contact->clients->pluck('id')->contains($client2->id));
    }

    public function test_can_filter_contacts_by_channel_and_language(): void
    {
        Contact::create([
            'name' => 'MARIA ESPAÑOL WHATSAPP',
            'preferred_channel' => 'whatsapp',
            'preferred_language' => 'es',
        ]);

        Contact::create([
            'name' => 'JOHN ENGLISH CALL',
            'preferred_channel' => 'call',
            'preferred_language' => 'en',
        ]);

        Livewire::actingAs($this->user)
            ->test(ContactIndex::class)
            ->set('channelFilter', 'whatsapp')
            ->assertSee('MARIA ESPAÑOL WHATSAPP')
            ->assertDontSee('JOHN ENGLISH CALL')
            ->set('channelFilter', '')
            ->set('languageFilter', 'en')
            ->assertSee('JOHN ENGLISH CALL')
            ->assertDontSee('MARIA ESPAÑOL WHATSAPP');
    }

    public function test_can_search_contacts_by_name_and_linked_company(): void
    {
        $client = Client::create(['name' => 'TACO LOCO']);
        $c1 = Contact::create(['name' => 'SERGIO RAMIREZ']);
        $client->contacts()->attach($c1->id);

        $c2 = Contact::create(['name' => 'ANDREA SUAREZ']);

        Livewire::actingAs($this->user)
            ->test(ContactIndex::class)
            ->set('search', 'TACO LOCO')
            ->assertSee('SERGIO RAMIREZ')
            ->assertDontSee('ANDREA SUAREZ')
            ->set('search', 'ANDREA')
            ->assertSee('ANDREA SUAREZ')
            ->assertDontSee('SERGIO RAMIREZ');
    }

    public function test_can_merge_duplicate_contacts_and_reassign_orders(): void
    {
        $clientA = Client::create(['name' => 'EMPRESA A']);
        $clientB = Client::create(['name' => 'EMPRESA B']);

        $contactTarget = Contact::create([
            'name' => 'PACO MARTINEZ',
            'phone' => '(770) 111-2222',
            'email' => 'paco@target.com',
            'preferred_channel' => 'whatsapp',
            'preferred_language' => 'es',
        ]);
        $clientA->contacts()->attach($contactTarget->id);

        $contactSource = Contact::create([
            'name' => 'PACO',
            'notes' => 'Nota desde contacto duplicado',
            'preferred_channel' => 'call',
            'preferred_language' => 'en',
        ]);
        $clientB->contacts()->attach($contactSource->id);

        $order1 = Order::create([
            'wo_number' => 'WO 1001',
            'task_name' => 'Banner',
            'company_name' => 'EMPRESA B',
            'client_id' => $clientB->id,
            'contact_id' => $contactSource->id,
            'responsible_person' => 'PACO',
            'in_workspace' => true,
        ]);

        app(ClientMatchingService::class)->mergeContacts($contactSource, $contactTarget);

        $order1->refresh();
        $this->assertEquals($contactTarget->id, $order1->contact_id);
        $this->assertEquals('PACO MARTINEZ', $order1->responsible_person);

        $contactTarget->refresh();
        $this->assertCount(2, $contactTarget->clients);
        $this->assertStringContainsString('Nota desde contacto duplicado', $contactTarget->notes);

        $this->assertSoftDeleted('contacts', ['id' => $contactSource->id]);
    }

    public function test_order_creation_modal_can_create_contact_on_the_fly_for_company(): void
    {
        $client = Client::create(['name' => 'POLLO CAMPERO']);

        Livewire::actingAs($this->user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->set('companyName', 'POLLO CAMPERO')
            ->call('createContactForCompany', 'DIEGO REYES')
            ->assertSet('responsiblePerson', 'DIEGO REYES');

        $contact = Contact::where('name', 'DIEGO REYES')->first();
        $this->assertNotNull($contact);
        $this->assertTrue($client->contacts()->where('contacts.id', $contact->id)->exists());
    }

    public function test_contact_flyout_shows_only_assigned_orders_to_that_contact(): void
    {
        $client = Client::create(['name' => 'KUDOS BRAND']);

        $contact1 = Contact::create(['name' => 'ALEXANDER']);
        $contact2 = Contact::create(['name' => 'BEATRIZ']);

        $client->contacts()->attach([$contact1->id, $contact2->id]);

        $order1 = Order::create([
            'wo_number' => 'WO 2001',
            'task_name' => 'Logo Alexander',
            'company_name' => 'KUDOS BRAND',
            'client_id' => $client->id,
            'contact_id' => $contact1->id,
            'responsible_person' => 'ALEXANDER',
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
        ]);

        $order2 = Order::create([
            'wo_number' => 'WO 2002',
            'task_name' => 'Flyer Beatriz',
            'company_name' => 'KUDOS BRAND',
            'client_id' => $client->id,
            'contact_id' => $contact2->id,
            'responsible_person' => 'BEATRIZ',
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(ContactFlyoutPanel::class)
            ->call('open', $contact1->id)
            ->set('activeTab', 'orders')
            ->assertSee('WO 2001')
            ->assertSee('LOGO ALEXANDER')
            ->assertDontSee('WO 2002')
            ->assertDontSee('FLYER BEATRIZ');
    }

    public function test_unassigned_orders_for_company_are_attributed_to_primary_main_contact(): void
    {
        $client = Client::create(['name' => 'SUPER STORE']);
        $primaryContact = Contact::create(['name' => 'MARCELA']);
        $client->contacts()->attach($primaryContact->id, ['is_primary' => true]);

        $unassignedOrder = Order::create([
            'wo_number' => 'WO 3001',
            'task_name' => 'Banners Gran Inauguracion',
            'company_name' => 'SUPER STORE',
            'client_id' => $client->id,
            'contact_id' => null,
            'responsible_person' => 'MAIN',
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(ContactFlyoutPanel::class)
            ->call('open', $primaryContact->id)
            ->set('activeTab', 'orders')
            ->assertSee('WO 3001')
            ->assertSee('BANNERS GRAN INAUGURACION')
            ->assertSee('Vía Main Contact');
    }

    public function test_create_order_auto_assigns_primary_contact_or_defaults_to_main(): void
    {
        $clientWithPrimary = Client::create(['name' => 'TAQUERIA EL REY']);
        $primary = Contact::create(['name' => 'JUAN CARLOS']);
        $clientWithPrimary->contacts()->attach($primary->id, ['is_primary' => true]);

        $clientWithoutContact = Client::create(['name' => 'NUEVO NEGOCIO SIN CONTACTO']);

        // Test 1: Order for company with primary contact auto-assigns primary contact
        Livewire::actingAs($this->user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->set('companyName', 'TAQUERIA EL REY')
            ->set('taskName', 'Letrero Acrilico')
            ->call('save');

        $order1 = Order::where('company_name', 'TAQUERIA EL REY')->first();
        $this->assertNotNull($order1);
        $this->assertEquals($primary->id, $order1->contact_id);
        $this->assertEquals('JUAN CARLOS', $order1->responsible_person);

        // Test 2: Order for company without contacts defaults to MAIN
        Livewire::actingAs($this->user)
            ->test(CreateOrderModal::class)
            ->call('openModal')
            ->set('companyName', 'NUEVO NEGOCIO SIN CONTACTO')
            ->set('taskName', 'Menu Digital')
            ->call('save');

        $order2 = Order::where('company_name', 'NUEVO NEGOCIO SIN CONTACTO')->first();
        $this->assertNotNull($order2);
        $this->assertNull($order2->contact_id);
        $this->assertEquals('MAIN', $order2->responsible_person);
        $this->assertEquals('MAIN', $order2->display_responsible_person);
    }

    public function test_can_set_contact_as_primary_and_auto_assigns_unassigned_orders(): void
    {
        $client = Client::create(['name' => 'AUTO SHOP']);
        $contact = Contact::create(['name' => 'CARLOS MOTOR']);
        $client->contacts()->attach($contact->id, ['is_primary' => false]);

        $unassigned = Order::create([
            'wo_number' => 'WO 4001',
            'task_name' => 'Wrap Vehicular',
            'company_name' => 'AUTO SHOP',
            'client_id' => $client->id,
            'contact_id' => null,
            'responsible_person' => 'MAIN',
            'in_workspace' => true,
        ]);

        Livewire::actingAs($this->user)
            ->test(ContactFlyoutPanel::class)
            ->call('open', $contact->id)
            ->call('setAsPrimaryClient', $client->id);

        $unassigned->refresh();
        $this->assertEquals($contact->id, $unassigned->contact_id);
        $this->assertEquals('CARLOS MOTOR', $unassigned->responsible_person);
    }
}
