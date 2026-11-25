<?php

namespace Tests\Feature\Characterization;

use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Contact;
use App\Models\Designer;
use App\Models\ProductPriceTier;
use App\Models\ProductVariant;
use App\Services\ClientMatchingService;
use Database\Seeders\ProductPricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Characterizes ClientMatchingService name parsing / contact rules and ProductPriceTier math.
 */
class SupportClientMatchingTest extends TestCase
{
    use RefreshDatabase;

    private ClientMatchingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ClientMatchingService::class);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: ?string}>
     */
    public static function companyNames(): array
    {
        return [
            'plain name is uppercased' => ['Fuerza Latina', 'FUERZA LATINA', null],
            'status prefix stripped' => ['(ALTA) Fuerza Latina', 'FUERZA LATINA', null],
            'designer prefix stripped' => ['(EURALIZ) Mi Tienda', 'MI TIENDA', null],
            'slash wo prefix stripped' => ['/ 15039 - Fuerza Latina', 'FUERZA LATINA', null],
            'wo prefix stripped' => ['WO 12345 - Fuerza Latina', 'FUERZA LATINA', null],
            'REF split' => ['Fuerza Latina REF El Sol', 'FUERZA LATINA', 'EL SOL'],
            'REF dot split' => ['Fuerza Latina REF. El Sol', 'FUERZA LATINA', 'EL SOL'],
            'SEDE split' => ['Fuerza Latina SEDE Centro', 'FUERZA LATINA', 'CENTRO'],
            'SUCURSAL split' => ['Fuerza Latina Sucursal Norte', 'FUERZA LATINA', 'NORTE'],
            'LOCAL split' => ['Fuerza Latina Local 4', 'FUERZA LATINA', '4'],
            'hyphen split' => ['Fuerza Latina - Talpa 8', 'FUERZA LATINA', 'TALPA 8'],
            'slash split' => ['Fuerza Latina / Talpa 8', 'FUERZA LATINA', 'TALPA 8'],
            'hyphenated brand is split' => ['COCA-COLA', 'COCA', 'COLA'],
            'hyphenated brand with spaces is split' => ['Coca - Cola', 'COCA', 'COLA'],
            'keyword split wins over hyphen' => ['Fuerza - Latina SEDE Centro', 'FUERZA - LATINA', 'CENTRO'],
            'empty becomes placeholder' => ['   ', 'CLIENTE SIN NOMBRE', null],
            'only prefix becomes placeholder' => ['(ALTA)', 'CLIENTE SIN NOMBRE', null],
            'accents are uppercased' => ['café añejo', 'CAFÉ AÑEJO', null],
        ];
    }

    #[DataProvider('companyNames')]
    public function test_clean_company_name(string $raw, string $expectedClient, ?string $expectedLocation): void
    {
        $parsed = $this->service->cleanCompanyName($raw);

        $this->assertSame($expectedClient, $parsed['client_name']);
        $this->assertSame($expectedLocation, $parsed['location_name']);
    }

    public function test_match_or_create_creates_client_location_and_reuses_them(): void
    {
        $first = $this->service->matchOrCreate('Fuerza Latina REF El Sol');
        $second = $this->service->matchOrCreate('(ALTA) fuerza latina REF El Sol');

        $this->assertSame('FUERZA LATINA', $first['client']->name);
        $this->assertSame('EL SOL', $first['location']->name);
        $this->assertSame($first['client']->id, $second['client']->id);
        $this->assertSame($first['location']->id, $second['location']->id);
        $this->assertSame(1, Client::count());
        $this->assertSame(1, ClientLocation::count());
    }

    public function test_match_or_create_without_location_returns_null_location(): void
    {
        $match = $this->service->matchOrCreate('Plain Client');

        $this->assertNull($match['location']);
        $this->assertSame(0, ClientLocation::count());
    }

    public function test_backlog_matching_never_creates_clients_locations_or_contacts(): void
    {
        $match = $this->service->matchOrCreate('Ghost Client REF Somewhere', 'Maria Lopez', createIfMissing: false);

        $this->assertNull($match['client']);
        $this->assertNull($match['location']);
        $this->assertSame(0, Client::count());
        $this->assertSame(0, ClientLocation::count());
        $this->assertSame(0, Contact::count());
    }

    public function test_backlog_matching_returns_existing_client_and_location_without_mutating(): void
    {
        $client = Client::create(['name' => 'KNOWN']);
        $location = ClientLocation::create(['client_id' => $client->id, 'name' => 'NORTH', 'address' => '']);

        $match = $this->service->matchOrCreate('Known SEDE North', 'Maria Lopez', createIfMissing: false);

        $this->assertSame($client->id, $match['client']->id);
        $this->assertSame($location->id, $match['location']->id);
        $this->assertSame(0, Contact::count());
    }

    public function test_responsible_person_becomes_an_uppercased_primary_contact_once(): void
    {
        $this->service->matchOrCreate('Contact Client', 'Maria Lopez');
        $this->service->matchOrCreate('Contact Client', 'maria lopez');
        $this->service->matchOrCreate('Contact Client', 'Pedro Ramos');

        $client = Client::where('name', 'CONTACT CLIENT')->firstOrFail();
        $this->assertSame(2, $client->contacts()->count());
        $this->assertSame(['MARIA LOPEZ'], $client->contacts()->wherePivot('is_primary', true)->pluck('name')->all());
        $contact = Contact::where('name', 'MARIA LOPEZ')->firstOrFail();
        $this->assertSame('any', $contact->preferred_channel);
        $this->assertSame('es', $contact->preferred_language);
    }

    public function test_invalid_contact_names_are_not_stored(): void
    {
        foreach (['ALTA', 'camila', 'Kike', '12345', 'ab', 'Logo nuevo', 'Business Cards', 'Taqueria Lupe'] as $bad) {
            $this->service->matchOrCreate('Blacklist Client', $bad);
        }

        $this->assertSame(0, Contact::count());
    }

    public function test_contact_name_validation_rules(): void
    {
        $this->assertTrue($this->service->isValidContactName('Maria Lopez'));
        $this->assertFalse($this->service->isValidContactName('ab'));
        $this->assertFalse($this->service->isValidContactName('123'));
        $this->assertFalse($this->service->isValidContactName('Marca Propia'));
        $this->assertFalse($this->service->isValidContactName('Pedro Bakery'));
    }

    public function test_active_designer_names_are_blacklisted_as_contacts(): void
    {
        $this->assertTrue($this->service->isValidContactName('Gabriela Soto'));

        Designer::create(['name' => 'Gabriela', 'active' => true]);

        $this->assertFalse($this->service->isValidContactName('Gabriela Soto'));
    }

    public function test_inactive_designer_names_are_not_blacklisted(): void
    {
        Designer::create(['name' => 'Gabriela', 'active' => false]);

        $this->assertTrue($this->service->isValidContactName('Gabriela Soto'));
    }

    public function test_contact_name_matching_a_client_location_is_rejected(): void
    {
        $client = Client::create(['name' => 'LOCATED']);
        ClientLocation::create(['client_id' => $client->id, 'name' => 'RIVERSIDE', 'address' => '']);

        $this->assertFalse($this->service->isValidContactName('Riverside', $client));
        $this->assertFalse($this->service->isValidContactName('Riverside Plaza', $client));
        $this->assertTrue($this->service->isValidContactName('Riverside', null));
    }

    public function test_purge_bogus_contacts_deletes_only_invalid_names(): void
    {
        $good = Contact::create(['name' => 'MARIA LOPEZ', 'preferred_channel' => 'any', 'preferred_language' => 'es']);
        $bad = Contact::create(['name' => 'ALTA', 'preferred_channel' => 'any', 'preferred_language' => 'es']);

        $this->assertSame(1, $this->service->purgeBogusContacts());
        $this->assertNotNull(Contact::find($good->id));
        $this->assertNull(Contact::find($bad->id));
    }

    public function test_product_price_tier_price_math(): void
    {
        $tier = new ProductPriceTier(['quantity' => 500, 'production_cost' => 100, 'markup_percent' => 50]);
        $tier->recalculate();

        $this->assertSame(150.0, (float) $tier->calculated_price);
        $this->assertSame(150.0, (float) $tier->final_price);
        $this->assertSame(0.3, (float) $tier->unit_price);
    }

    public function test_product_price_tier_defaults_markup_to_fifty_percent_and_rounds(): void
    {
        $tier = new ProductPriceTier(['quantity' => 3, 'production_cost' => 10.01]);
        $tier->recalculate();

        $this->assertSame(15.02, (float) $tier->calculated_price);
        $this->assertSame(5.0067, (float) $tier->unit_price);
    }

    public function test_product_price_tier_zero_markup_zero_cost_and_zero_quantity_edges(): void
    {
        $noMarkup = new ProductPriceTier(['quantity' => 10, 'production_cost' => 80, 'markup_percent' => 0]);
        $noMarkup->recalculate();
        $this->assertSame(80.0, (float) $noMarkup->final_price);
        $this->assertSame(8.0, (float) $noMarkup->unit_price);

        $zeroQuantity = new ProductPriceTier(['quantity' => 0, 'production_cost' => 80, 'markup_percent' => 100]);
        $zeroQuantity->recalculate();
        $this->assertSame(160.0, (float) $zeroQuantity->final_price);
        $this->assertSame(0.0, (float) $zeroQuantity->unit_price);

        $empty = new ProductPriceTier;
        $empty->recalculate();
        $this->assertSame(0.0, (float) $empty->final_price);
    }

    public function test_product_price_tier_recalculates_on_save_overwriting_manual_final_price(): void
    {
        $this->seed(ProductPricingSeeder::class);
        $variantId = ProductVariant::query()->value('id');

        $tier = ProductPriceTier::create([
            'product_variant_id' => $variantId, 'quantity' => 100, 'production_cost' => 200, 'markup_percent' => 25, 'final_price' => 9999,
        ]);

        $this->assertSame(250.0, (float) $tier->fresh()->final_price);
        $this->assertSame(2.5, (float) $tier->fresh()->unit_price);
    }
}
