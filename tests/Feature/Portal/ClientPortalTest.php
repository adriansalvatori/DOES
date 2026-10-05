<?php

namespace Tests\Feature\Portal;

use App\Enums\CoreStatus;
use App\Enums\UserRole;
use App\Livewire\Clients\ClientFlyoutPanel;
use App\Livewire\Portal\ClientPortal;
use App\Livewire\Settings\UserManagement;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\ClientTimelineService;
use App\Services\QrCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response->assertSee('FUERZA LATINA');
        $response->assertSee('Seguimiento en Vivo');
    }

    public function test_client_portal_shows_not_found_on_invalid_token(): void
    {
        $response = $this->get('/c/invalid-random-token-xyz');

        $response->assertStatus(200);
        $response->assertSee('Enlace o Código QR No Válido');
    }

    public function test_client_portal_displays_active_orders_and_filters(): void
    {
        $client = Client::create([
            'name' => 'Fuerza Latina',
        ]);

        $order1 = Order::create([
            'client_id' => $client->id,
            'company_name' => 'FUERZA LATINA',
            'task_name' => 'ROLLUP BANNER GAINESVILLE',
            'wo_number' => 'WO 16362',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'in_workspace' => true,
        ]);

        $order2 = Order::create([
            'client_id' => $client->id,
            'company_name' => 'FUERZA LATINA',
            'task_name' => 'WINDOW GRAPHICS',
            'wo_number' => 'WO 16227',
            'core_status' => CoreStatus::EN_PRODUCCION,
            'in_workspace' => true,
        ]);

        // Archived order should not appear
        $order3 = Order::create([
            'client_id' => $client->id,
            'company_name' => 'FUERZA LATINA',
            'task_name' => 'OLD ARCHIVED PROJECT',
            'wo_number' => 'WO 15000',
            'core_status' => CoreStatus::ARCHIVED,
            'in_workspace' => true,
        ]);

        Livewire::test(ClientPortal::class, ['token' => $client->portal_token])
            ->assertSee('ROLLUP BANNER GAINESVILLE')
            ->assertSee('WO 16362')
            ->assertSee('Esperando tu respuesta')
            ->assertSee('WINDOW GRAPHICS')
            ->assertSee(__('En Producción'))
            ->assertDontSee('OLD ARCHIVED PROJECT');
    }

    public function test_client_portal_can_select_order_and_view_compact_timeline(): void
    {
        $client = Client::create([
            'name' => 'Fuerza Latina',
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'company_name' => 'FUERZA LATINA',
            'task_name' => 'PARTIAL WRAP BLACK TRUCK',
            'wo_number' => 'WO 16289',
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'in_workspace' => true,
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_CREATED',
            'actor' => 'Sistema',
            'previous_value' => null,
            'new_value' => 'FUERZA LATINA',
            'created_at' => now()->subDays(5),
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'ORDER_SENT_TO_CLIENT',
            'actor' => 'Disenador',
            'previous_value' => null,
            'new_value' => CoreStatus::ENVIADO_AL_CLIENTE->value,
            'created_at' => now()->subDays(3),
        ]);

        OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'SUBTASK_COMPLETED',
            'actor' => 'Disenador',
            'previous_value' => null,
            'new_value' => 'Ajustes cliente',
            'created_at' => now()->subDays(2),
        ]);

        Livewire::test(ClientPortal::class, ['token' => $client->portal_token])
            ->call('selectOrder', $order->id)
            ->assertSet('selectedOrderId', $order->id)
            ->assertSee('Línea de Tiempo de la Orden')
            ->assertSee('Orden recibida e iniciada')
            ->assertSee('Diseño enviado para tu revisión')
            ->assertSee('Cambios / comentarios recibidos')
            ->call('closeOrder')
            ->assertSet('selectedOrderId', null);
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
            ->assertSeeHtml('Portal de Seguimiento (QR)')
            ->assertSee($client->portal_url)
            ->assertSee('Descargar Card JPG')
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

        $this->assertEquals('En Pausa', $status['label']);
        $this->assertStringContainsString('Esperando confirmación de medidas', $status['description']);

        $holdMilestone = $timeline->firstWhere('type', 'on_hold');
        $this->assertNotNull($holdMilestone);
        $this->assertStringContainsString('En pausa', $holdMilestone['title']);
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
            ->assertSee('Número de WhatsApp de Atención al Cliente actualizado exitosamente.');

        $this->assertEquals('+1 (678) 999-8877', Setting::get('cs_whatsapp_phone'));
    }

    public function test_portal_displays_cs_whatsapp_and_designer_chat_buttons(): void
    {
        Setting::set('cs_whatsapp_phone', '+16783580594');

        $client = Client::create(['name' => 'Fuerza Latina']);

        $designerUser = User::factory()->create([
            'name' => 'Euraliz Disenadora',
            'phone' => '+1 (555) 123-4567',
        ]);

        $designer = Designer::create([
            'name' => 'Euraliz',
            'user_id' => $designerUser->id,
            'active' => true,
        ]);

        $order = Order::create([
            'client_id' => $client->id,
            'company_name' => 'Fuerza Latina',
            'task_name' => 'Wrap Design',
            'wo_number' => 'WO 999',
            'designer_id' => $designer->id,
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'in_workspace' => true,
        ]);

        Livewire::test(ClientPortal::class, ['token' => $client->portal_token])
            ->call('selectOrder', $order->id)
            ->assertSee('Chat with Customer Service')
            ->assertSee('+1 (678) 358-0594')
            ->assertSee('Chat with Euraliz, your designer')
            ->assertSee('+1 (555) 123-4567')
            ->assertSee('5555 Oakborook Pkwy')
            ->assertSee('+1 (770) 696-4048 Ext. 301')
            ->assertSee('cs@kudosprintmedia.com');
    }
}
