<?php

namespace Tests\Feature\Screens\Panel;

use App\Contracts\WorkOrderNumberGenerator;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\CreateWorkOrder\CreateWorkOrderModal;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CreateWorkOrderModalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function modal(?User $user = null): Testable
    {
        return Livewire::actingAs($user ?? User::factory()->coordinator()->create())->test(CreateWorkOrderModal::class)->dispatch('open-create-work-order');
    }

    public function test_it_stays_closed_until_the_event_and_prefills_a_client_and_phase(): void
    {
        $client = Client::create(['name' => 'KUYEN']);

        Livewire::actingAs(User::factory()->coordinator()->create())->test(CreateWorkOrderModal::class)
            ->assertSet('isOpen', false)
            ->assertDontSee('Next WO')
            ->dispatch('open-create-work-order', clientId: $client->id, phaseName: 'Phase 1')
            ->assertSet('isOpen', true)
            ->assertSet('clientId', $client->id)
            ->assertSet('companyQuery', 'Kuyen')
            ->assertSet('phaseName', 'Phase 1')
            ->assertSet('promisedDate', '2026-11-27')
            ->assertSee('Create work order')
            ->assertSee('Next WO')
            ->assertSee('Phase (optional)')
            ->assertSee('Fulfillment method')
            ->assertSee('Trello card link (reference)');
    }

    public function test_every_field_of_the_legacy_form_is_present_and_create_on_trello_is_gone(): void
    {
        $html = $this->modal()->html();

        foreach (['WO number (optional)', 'Next WO', 'Contact', 'Company', 'Location', 'Description', 'Deliverables (size', 'Phase (optional)', 'Fulfillment method', 'Flags', 'Urgent', 'Ticket', 'Potential customer', 'External', 'Reprint', 'Make permit sign', 'Designer', 'Promised date', 'Today', 'Tomorrow', 'Trello card link (reference)'] as $label) {
            $this->assertStringContainsString($label, $html, $label);
        }
        $this->assertStringNotContainsString('Create on Trello', $html);
        $this->assertStringNotContainsString('Balance due', $html);
    }

    public function test_a_user_who_cannot_create_work_orders_does_not_get_the_modal(): void
    {
        Livewire::actingAs(User::factory()->designer()->create(['active' => false]))->test(CreateWorkOrderModal::class)
            ->dispatch('open-create-work-order')
            ->assertSet('isOpen', false);
    }

    public function test_next_wo_fills_the_number_from_the_generator(): void
    {
        $this->app->bind(WorkOrderNumberGenerator::class, fn () => new class implements WorkOrderNumberGenerator
        {
            public function generateNext(array $context = []): string
            {
                return 'WO 16368';
            }

            public function generateNextDigits(array $context = []): string
            {
                return '16368';
            }

            public function getLastNumber(): ?string
            {
                return '16367';
            }
        });

        $this->modal()->call('generateWoNumber')->assertSet('woNumber', '16368');
    }

    public function test_company_and_contact_search_offer_matches_and_selecting_fills_the_form(): void
    {
        $client = Client::create(['name' => 'SUNRISE DINER']);
        $contact = Contact::create(['name' => 'MARCELA RIOS', 'preferred_channel' => 'any', 'preferred_language' => 'es']);
        $client->contacts()->attach($contact->id, ['is_primary' => true]);
        Client::create(['name' => 'OTHER CO']);

        $modal = $this->modal()
            ->set('companyQuery', 'sunr')
            ->assertSeeHtml('data-company-results')
            ->assertSee('Sunrise Diner')
            ->assertDontSee('Other Co')
            ->call('selectCompany', $client->id)
            ->assertSet('clientId', $client->id)
            ->assertSet('contactId', $contact->id)
            ->assertSet('contactQuery', 'Marcela Rios');

        $modal->set('contactQuery', 'zzz')->assertSet('contactId', null)->assertSee('A new contact will be created.');
        $modal->set('companyQuery', 'Brand New Place')->assertSet('clientId', null)->assertSee('A new company will be created.');
    }

    public function test_creates_the_work_order_with_every_field_and_lands_in_intake(): void
    {
        $designer = User::factory()->designer()->create(['name' => 'Ana']);
        $client = Client::create(['name' => 'KUYEN']);
        $location = ClientLocation::create(['client_id' => $client->id, 'name' => 'TALPA 8', 'address' => '']);
        $contact = Contact::create(['name' => 'MARCELA', 'preferred_channel' => 'any', 'preferred_language' => 'es']);

        $this->modal()
            ->call('selectCompany', $client->id)
            ->call('selectContact', $contact->id)
            ->set('woNumber', '16518')
            ->set('locationName', 'Talpa 8')
            ->set('description', 'Acrylic outdoor menu')
            ->set('newName', 'Front sign')->set('newSize', '2x1 m')->set('newMaterial', 'acrylic')->set('newQuantity', 2)
            ->call('addDeliverableRow')
            ->set('newName', 'Menu board')
            ->set('phaseName', 'Phase 3')
            ->set('fulfillmentMethod', FulfillmentMethod::INSTALLATION->value)
            ->call('toggleFlag', 'urgent')
            ->call('toggleFlag', 'reprint')
            ->call('selectAssignee', (string) $designer->id)
            ->call('setPromisedPreset', 'tomorrow')
            ->set('trelloLink', 'https://trello.com/c/abc')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('work-order-created')
            ->assertSet('isOpen', false);

        $workOrder = WorkOrder::where('number', 'WO 16518')->firstOrFail();
        $this->assertSame($client->id, $workOrder->client_id);
        $this->assertSame($location->id, $workOrder->client_location_id);
        $this->assertSame($contact->id, $workOrder->contact_id);
        $this->assertSame('Acrylic outdoor menu', $workOrder->description);
        $this->assertSame('2026-11-26', $workOrder->promised_date->toDateString());
        $this->assertSame(FulfillmentMethod::INSTALLATION, $workOrder->fulfillment_method);

        $deliverables = $workOrder->deliverables()->orderBy('id')->get();
        $this->assertSame(['Front sign', 'Menu board'], $deliverables->pluck('task_name')->all());
        $this->assertSame('2x1 m', $deliverables[0]->size);
        $this->assertSame('acrylic', $deliverables[0]->material);
        $this->assertSame(2, $deliverables[0]->quantity);
        $this->assertTrue($deliverables->every(fn (Deliverable $each): bool => $each->status === DeliverableStatus::INTAKE));
        $this->assertTrue($deliverables->every(fn (Deliverable $each): bool => $each->hasFlag(DeliverableFlag::URGENT) && $each->hasFlag(DeliverableFlag::REPRINT)));
        $this->assertTrue($deliverables->every(fn (Deliverable $each): bool => $each->assignee_id === $designer->id && $each->trello_card_url === 'https://trello.com/c/abc'));
        $this->assertSame('Phase 3', Phase::where('work_order_id', $workOrder->id)->value('name'));
        $this->assertTrue($deliverables->every(fn (Deliverable $each): bool => $each->phase_id !== null));
    }

    public function test_without_rows_one_deliverable_is_created_from_the_description_and_new_company_location_and_contact_are_created(): void
    {
        $this->modal()
            ->set('companyQuery', 'Brand New Place')
            ->set('locationName', 'Downtown')
            ->set('contactQuery', 'Pedro Gomez')
            ->set('description', 'Door vinyl')
            ->call('save')
            ->assertHasNoErrors();

        $client = Client::where('name', 'BRAND NEW PLACE')->firstOrFail();
        $workOrder = WorkOrder::firstOrFail();
        $this->assertSame($client->id, $workOrder->client_id);
        $this->assertSame('DOWNTOWN', ClientLocation::find($workOrder->client_location_id)->name);
        $this->assertSame('PEDRO GOMEZ', Contact::find($workOrder->contact_id)->name);
        $this->assertSame(['Door vinyl'], $workOrder->deliverables->pluck('task_name')->all());
        $this->assertNull($workOrder->number);
    }

    public function test_validation_messages_are_translated_and_nothing_is_created(): void
    {
        $this->modal()
            ->set('newName', '')->set('newQuantity', 0)->call('addDeliverableRow')
            ->assertHasErrors(['newName'])
            ->call('save')
            ->assertHasErrors(['companyQuery', 'description'])
            ->assertSee('The company is required.')
            ->assertSee('The job description is required.');

        $this->assertSame(0, WorkOrder::count());
    }

    public function test_a_duplicate_number_is_reported_and_the_form_stays_open(): void
    {
        WorkOrder::factory()->create(['number' => 'WO 9']);

        $this->modal()
            ->set('companyQuery', 'Acme')->set('description', 'Sign')->set('woNumber', 'WO 9')
            ->call('save')
            ->assertSet('isOpen', true)
            ->assertSeeHtml('data-submit-error')
            ->assertSee('Work order WO 9 already exists.');

        $this->assertSame(1, WorkOrder::count());
        $this->assertSame(0, Client::where('name', 'ACME')->count(), 'Nothing else is created when the work order is refused.');
    }

    public function test_designers_show_the_most_available_hint_from_work_in_progress(): void
    {
        $busy = User::factory()->designer()->create(['name' => 'Busy']);
        $free = User::factory()->designer()->create(['name' => 'Free']);
        foreach ([1, 2] as $ignored) {
            Deliverable::factory()->for(WorkOrder::factory()->create(), 'workOrder')->inStatus(DeliverableStatus::IN_DESIGN)->create(['assignee_id' => $busy->id]);
        }
        $blocked = Deliverable::factory()->for(WorkOrder::factory()->create(), 'workOrder')->inStatus(DeliverableStatus::IN_DESIGN)->create(['assignee_id' => $free->id]);
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        $this->modal()->assertSee('Most available: Free (0 in progress)')->assertSee('Busy');
    }

    public function test_unknown_assignees_are_refused(): void
    {
        $manager = User::factory()->coordinator()->create();

        $this->modal()->set('companyQuery', 'Acme')->set('description', 'Sign')->set('assigneeId', (string) $manager->id)->call('save')->assertHasErrors('assigneeId');
    }

    public function test_it_renders_in_both_locales_without_missing_keys_and_stays_small(): void
    {
        Client::create(['name' => 'ACME']);
        User::factory()->designer()->count(3)->create();

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            $modal = $this->modal()->set('companyQuery', 'ac')->call('save');
            $modal->assertDontSee('enums.');
            $this->assertLessThan(300 * 1024, strlen($modal->html()));
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->modal()->set('companyQuery', 'ac');
        $this->assertLessThanOrEqual(25, count(DB::getQueryLog()));
    }
}
