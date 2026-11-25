<?php

namespace Tests\Feature\Portal;

use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\Receipt;
use App\Domain\Delivery\Models\WorkOrder;
use App\Enums\CoreStatus;
use App\Enums\UserRole;
use App\Livewire\Clients\ClientFlyoutPanel;
use App\Livewire\Portal\ClientPortal;
use App\Livewire\Settings\UserManagement;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClientTimelineService;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_has_auto_generated_portal_token_and_portal_url(): void
    {
        $client = Client::create([
            'name' => 'Fuerza Latina',
        ]);

        $this->assertNotEmpty($client->portal_token);
        $this->assertEquals(32, strlen($client->portal_token));
        $this->assertStringContainsString('/c/'.$client->portal_token, $client->portal_url);
    }

    public function test_client_portal_is_accessible_without_authentication(): void
    {
        $client = Client::create([
            'name' => 'Fuerza Latina',
        ]);

        $response = $this->get('/c/'.$client->portal_token);

        $response->assertStatus(200);
        $response->assertSee('Fuerza Latina');
        $response->assertSee('Live tracking');
    }

    public function test_client_portal_shows_not_found_on_invalid_token(): void
    {
        $response = $this->get('/c/invalid-random-token-xyz');

        $response->assertStatus(200);
        $response->assertSee('Invalid link or QR code');
    }

    public function test_client_portal_is_organized_by_work_order_phase_and_deliverable(): void
    {
        $client = Client::create(['name' => 'Fuerza Latina']);
        $workOrder = WorkOrder::factory()->create(['client_id' => $client->id, 'number' => 'WO 16362', 'description' => 'Gainesville storefront', 'promised_date' => '2026-12-15']);
        $phase = Phase::factory()->create(['work_order_id' => $workOrder->id, 'name' => 'Phase one', 'position' => 1]);
        Deliverable::factory()->inStatus(DeliverableStatus::CLIENT_REVIEW)->create([
            'work_order_id' => $workOrder->id, 'phase_id' => $phase->id, 'task_name' => 'ROLLUP BANNER GAINESVILLE', 'due_target' => '2026-12-10',
        ]);
        $produced = Deliverable::factory()->inStatus(DeliverableStatus::IN_PRODUCTION)->create([
            'work_order_id' => $workOrder->id, 'phase_id' => $phase->id, 'task_name' => 'WINDOW GRAPHICS',
        ]);
        Deliverable::factory()->inStatus(DeliverableStatus::FULFILLMENT)->create(['work_order_id' => $workOrder->id, 'task_name' => 'DOOR VINYL']);
        Receipt::factory()->create(['deliverable_id' => $produced->id, 'received_at' => '2026-11-20 10:00:00']);

        $oldWorkOrder = WorkOrder::factory()->create(['client_id' => $client->id, 'number' => 'WO 15000']);
        Deliverable::factory()->done()->create(['work_order_id' => $oldWorkOrder->id, 'task_name' => 'OLD ARCHIVED PROJECT', 'closed_at' => now()->subDays(90)]);

        $this->get('/c/'.$client->portal_token)
            ->assertOk()
            ->assertSee('WO 16362')
            ->assertSee('Gainesville storefront')
            ->assertSee('Phase one')
            ->assertSee('ROLLUP BANNER GAINESVILLE')
            ->assertSee('Waiting for your review')
            ->assertSee('Dec 15, 2026')
            ->assertSee('Dec 10, 2026')
            ->assertSee('In production')
            ->assertSee('Received Nov 20, 2026')
            ->assertSee('Ready for delivery or pickup')
            ->assertSee('Not received yet')
            ->assertDontSee('OLD ARCHIVED PROJECT')
            ->assertDontSee('WO 15000');
    }

    public function test_client_portal_never_shows_another_clients_work_orders_or_internal_blocks(): void
    {
        $client = Client::create(['name' => 'Fuerza Latina']);
        $other = Client::create(['name' => 'Other Client']);
        $mine = WorkOrder::factory()->create(['client_id' => $client->id, 'number' => 'WO 111']);
        $theirs = WorkOrder::factory()->create(['client_id' => $other->id, 'number' => 'WO 222']);
        $blocked = Deliverable::factory()->inStatus(DeliverableStatus::IN_DESIGN)->create(['work_order_id' => $mine->id, 'task_name' => 'BLOCKED ITEM']);
        Block::factory()->create(['deliverable_id' => $blocked->id, 'reason' => BlockReason::MISSING_LOGO]);
        Deliverable::factory()->create(['work_order_id' => $theirs->id, 'task_name' => 'THEIR SECRET']);

        $this->get('/c/'.$client->portal_token)
            ->assertSee('WO 111')
            ->assertSee('In design')
            ->assertDontSee('Missing logo')
            ->assertDontSee('WO 222')
            ->assertDontSee('THEIR SECRET');
    }

    public function test_client_portal_renders_in_the_requested_language(): void
    {
        $client = Client::create(['name' => 'Fuerza Latina']);
        $workOrder = WorkOrder::factory()->create(['client_id' => $client->id, 'promised_date' => '2026-12-15']);
        Deliverable::factory()->inStatus(DeliverableStatus::CLIENT_REVIEW)->create(['work_order_id' => $workOrder->id]);

        $this->get('/c/'.$client->portal_token.'?lang=es')
            ->assertOk()
            ->assertSee(trans('Waiting for your review', [], 'es'))
            ->assertSee(trans('Promised for', [], 'es'))
            ->assertSee('15 de dic. de 2026');

        $this->get('/c/'.$client->portal_token.'?lang=en')->assertSee('Waiting for your review');
    }

    public function test_client_portal_keeps_the_token_mechanism_and_query_count_is_small(): void
    {
        $client = Client::create(['name' => 'Fuerza Latina']);
        foreach (range(1, 5) as $index) {
            $workOrder = WorkOrder::factory()->create(['client_id' => $client->id]);
            Deliverable::factory()->count(4)->create(['work_order_id' => $workOrder->id]);
        }

        DB::enableQueryLog();
        $this->get('/c/'.$client->portal_token)->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(15, $queries);
        $this->get('/c/not-a-token')->assertOk()->assertSee('Invalid link or QR code');
    }

    public function test_portal_links_to_customer_service_whatsapp(): void
    {
        Setting::set('cs_whatsapp_phone', '+16783580594');
        $client = Client::create(['name' => 'Fuerza Latina']);

        Livewire::test(ClientPortal::class, ['token' => $client->portal_token])
            ->assertSee('+1 (678) 358-0594')
            ->assertSeeHtml('https://wa.me/16783580594?text=')
            ->assertSee('cs@kudosprintmedia.com');
    }

    public function test_client_flyout_panel_displays_qr_and_portal_link(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'name' => 'Fuerza Latina',
        ]);

        $this->actingAs($user);

        Livewire::test(ClientFlyoutPanel::class)
            ->dispatch('open-client-flyout', clientId: $client->id)
            ->assertSet('isOpen', true)
            ->assertSeeHtml(e(__('Tracking portal (QR)')))
            ->assertSee($client->portal_url)
            ->assertSee(__('Download JPG card'))
            ->assertDontSeeHtml('<span>Portal QR</span>');
    }

    public function test_qr_code_service_generates_valid_svg_string(): void
    {
        $service = app(QrCodeService::class);
        $svg = $service->generateSvg('https://example.com/c/test-token', 200);

        $this->assertStringStartsWith('<?xml', $svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);

        $dataUri = $service->generateDataUri('https://example.com/c/test-token', 200);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
    }

    public function test_client_timeline_service_handles_on_hold_with_reason(): void
    {
        $client = Client::create(['name' => 'Test Client']);
        $order = Order::create([
            'client_id' => $client->id,
            'company_name' => 'Test Client',
            'task_name' => 'Test Order',
            'core_status' => CoreStatus::ON_HOLD,
            'pause_reason' => 'Esperando confirmación de medidas finales por el cliente',
            'in_workspace' => true,
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'MOVED_TO_ON_HOLD',
            'new_value' => 'ON HOLD',
            'metadata' => ['reason' => 'Esperando confirmación de medidas finales por el cliente'],
            'created_at' => now()->subDay(),
        ]);

        $service = app(ClientTimelineService::class);
        $timeline = $service->getClientTimeline($order);
        $status = $service->getCustomerStatus($order);

        $this->assertEquals('On hold', $status['label']);
        $this->assertStringContainsString('Esperando confirmación de medidas', $status['description']);

        $holdMilestone = $timeline->firstWhere('type', 'on_hold');
        $this->assertNotNull($holdMilestone);
        $this->assertStringContainsString('Paused', $holdMilestone['title']);
        $this->assertStringContainsString('Esperando confirmación de medidas', $holdMilestone['subtitle']);
    }

    public function test_admin_can_update_cs_whatsapp_phone_in_user_management(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $this->actingAs($admin);

        Livewire::test(UserManagement::class)
            ->assertSet('cs_whatsapp_phone', '+16783580594')
            ->set('cs_whatsapp_phone', '+1 (678) 999-8877')
            ->call('saveCsWhatsapp')
            ->assertHasNoErrors()
            ->assertSee('Customer service WhatsApp number updated.');

        $this->assertEquals('+1 (678) 999-8877', Setting::get('cs_whatsapp_phone'));
    }
}
