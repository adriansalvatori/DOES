<?php

namespace Tests\Feature\Settings;

use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus as SubstatusEnum;
use App\Enums\UserRole;
use App\Livewire\Settings\ColorCoding;
use App\Models\Designer;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use App\Models\Substatus;
use App\Models\User;
use App\Services\ColorCodingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ColorCodingSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic Substatuses and Designers
        Substatus::create([
            'name' => SubstatusEnum::CAMBIOS_CAMILA->value,
            'bg_color' => '#FAF5FF',
            'text_color' => '#7E22CE',
            'border_color' => '#E9D5FF',
            'color' => '#a855f7',
            'is_system' => true,
        ]);

        Substatus::create([
            'name' => SubstatusEnum::PONER_EN_ALTA->value,
            'bg_color' => '#FDF2F8',
            'text_color' => '#9D174D',
            'border_color' => '#FBCFE8',
            'color' => '#ec4899',
            'is_system' => true,
        ]);

        Designer::create([
            'name' => 'Euralíz Bravo',
            'slug' => 'euraliz',
            'color_type' => 'magenta',
            'hex_color' => '#F3A8FF',
            'active' => true,
            'is_lead' => true,
        ]);
    }

    public function test_color_coding_settings_page_renders_for_admin(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $response = $this->actingAs($admin)->get('/settings/color-coding');

        $response->assertStatus(200);
        $response->assertSee('Personalización del Color Coding');
        $response->assertSee('Camila (QA & Revisiones)');
        $response->assertSee('Producción (ALTA & producción)');
        $response->assertSee('Euralíz (Lead Designer)');
    }

    public function test_color_coding_settings_forbidden_for_regular_designer(): void
    {
        $designer = User::factory()->create([
            'role' => UserRole::DESIGNER,
        ]);

        $response = $this->actingAs($designer)->get('/settings/color-coding');

        $response->assertStatus(403);
    }

    public function test_can_update_camila_color_and_syncs_system_wide(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $newPurple = '#9333EA';

        Livewire::actingAs($admin)
            ->test(ColorCoding::class)
            ->call('selectPreset', 'camila', $newPurple)
            ->assertHasNoErrors()
            ->assertSee('Color actualizado para');

        $service = app(ColorCodingService::class);
        $this->assertEquals($newPurple, $service->getHex('camila'));

        // CoreStatus ENVIADO_A_CAMILA should now return the updated color
        $this->assertEquals($newPurple, CoreStatus::ENVIADO_A_CAMILA->hexColor());

        // Substatus CAMBIOS CAMILA should be updated in the database
        $sub = Substatus::where('name', SubstatusEnum::CAMBIOS_CAMILA->value)->first();
        $this->assertNotNull($sub);
        $this->assertEquals($newPurple, $sub->color);
    }

    public function test_can_update_production_color_and_syncs_system_wide(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $newProductionHex = '#DB2777';

        Livewire::actingAs($admin)
            ->test(ColorCoding::class)
            ->call('saveColor', 'production', $newProductionHex)
            ->assertHasNoErrors();

        $service = app(ColorCodingService::class);
        $this->assertEquals($newProductionHex, $service->getHex('production'));

        // CoreStatus EN_PRODUCCION should now return the updated color
        $this->assertEquals($newProductionHex, CoreStatus::EN_PRODUCCION->hexColor());

        // Substatus PONER EN ALTA should be updated in the database
        $sub = Substatus::where('name', SubstatusEnum::PONER_EN_ALTA->value)->first();
        $this->assertNotNull($sub);
        $this->assertEquals($newProductionHex, $sub->color);
    }

    public function test_can_update_designer_color_and_syncs_to_designer_model(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $newEuralizHex = '#D946EF';

        Livewire::actingAs($admin)
            ->test(ColorCoding::class)
            ->call('saveColor', 'designer_euraliz', $newEuralizHex)
            ->assertHasNoErrors();

        // CoreStatus EURALIZ_ORDERS_RECEIVED should now return the updated color
        $this->assertEquals($newEuralizHex, CoreStatus::EURALIZ_ORDERS_RECEIVED->hexColor());

        // Designer model should be updated
        $designer = Designer::where('slug', 'euraliz')->first();
        $this->assertNotNull($designer);
        $this->assertEquals($newEuralizHex, $designer->hex_color);
    }

    public function test_can_reset_single_key_and_all_colors_to_defaults(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $service = app(ColorCodingService::class);

        // Customize Camila
        $service->updateColor('camila', '#111827');
        $this->assertEquals('#111827', $service->getHex('camila'));

        // Reset single key
        Livewire::actingAs($admin)
            ->test(ColorCoding::class)
            ->call('resetKey', 'camila')
            ->assertHasNoErrors();

        $this->assertEquals('#a855f7', strtolower($service->getHex('camila')));

        // Customize multiple and reset all
        $service->updateColor('camila', '#111827');
        $service->updateColor('production', '#222222');

        Livewire::actingAs($admin)
            ->test(ColorCoding::class)
            ->call('resetAll')
            ->assertHasNoErrors();

        $this->assertEquals('#a855f7', strtolower($service->getHex('camila')));
        $this->assertEquals('#ec4899', strtolower($service->getHex('production')));
    }

    public function test_order_timeline_events_respond_to_color_coding_dynamically(): void
    {
        $order = Order::create([
            'company_name' => 'TEST TIMELINE',
            'task_name' => 'TASK COLOR TEST',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $service = app(ColorCodingService::class);

        // 1. Create a Camila event and verify it gets Camila's hex
        $camilaEvent = OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'CAMILA_NOTE',
            'actor' => 'Camila',
            'new_value' => 'Revisión solicitada',
        ]);

        $this->assertEquals($service->getHex('camila'), $camilaEvent->getNodeHexColor());
        $this->assertStringContainsString($service->getHex('camila'), $camilaEvent->getNodeInlineStyle());

        // 2. Change Camila's color in ColorCodingService and verify the timeline node style updates
        $customCamilaPurple = '#7C3AED';
        $service->updateColor('camila', $customCamilaPurple);

        $this->assertEquals($customCamilaPurple, $camilaEvent->fresh()->getNodeHexColor());
        $this->assertStringContainsString($customCamilaPurple, $camilaEvent->fresh()->getNodeInlineStyle());

        // 3. Create a Production event and verify it gets Production's hex
        $productionEvent = OrderEvent::create([
            'order_id' => $order->id,
            'event_type' => 'STATUS_CHANGED',
            'actor' => 'Adrian',
            'old_value' => 'TO DO TODAY',
            'new_value' => 'EN PRODUCCIÓN',
        ]);

        $this->assertEquals($service->getHex('production'), $productionEvent->getNodeHexColor());
        $this->assertStringContainsString($service->getHex('production'), $productionEvent->getNodeInlineStyle());

        // 4. Change Production's color and verify the timeline node style updates
        $customProductionPink = '#F43F5E';
        $service->updateColor('production', $customProductionPink);

        $this->assertEquals($customProductionPink, $productionEvent->fresh()->getNodeHexColor());
        $this->assertStringContainsString($customProductionPink, $productionEvent->fresh()->getNodeInlineStyle());
    }

    public function test_urgent_color_coding_is_available_and_applies_to_subtasks(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $service = app(ColorCodingService::class);

        $defs = ColorCodingService::defaultDefinitions();
        $this->assertArrayHasKey('urgent', $defs);
        $this->assertEquals('#ef4444', $defs['urgent']['default_hex']);

        // 1. Verify admin can customize urgent color
        $customRed = '#DC2626';
        Livewire::actingAs($admin)
            ->test(ColorCoding::class)
            ->call('saveColor', 'urgent', $customRed)
            ->assertHasNoErrors();

        $this->assertEquals($customRed, $service->getHex('urgent'));

        // 2. Test subtask color coding resolution
        $order = Order::create([
            'company_name' => 'ACME CORP',
            'task_name' => 'BANNER DESIGN',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]);

        $urgentSubtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'ENVIAR CORREO DE ATRASO',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'trigger_type' => 'AUTOMATIC_OVERDUE_DETECTION',
            'priority' => 'urgent',
            'is_work_task' => false,
        ]);

        $this->assertEquals('urgent', $urgentSubtask->colorCodingKey());
        $this->assertStringContainsString('var(--cc-urgent-bg-light)', $urgentSubtask->systemBadgeStyle());
        $this->assertStringContainsString('var(--cc-urgent-solid)', $urgentSubtask->systemDotStyle());
        $this->assertStringContainsString('var(--cc-urgent-text-dark)', $urgentSubtask->systemTextStyle());
        $this->assertEquals(__('Urgente'), $urgentSubtask->colorCodingShortLabel());

        $altaSubtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'PONER EN ALTA',
            'type' => RelatedTaskType::PONER_ALTA,
            'trigger_type' => 'SYSTEM_AUTOMATION',
            'is_work_task' => false,
        ]);
        $this->assertEquals('production', $altaSubtask->colorCodingKey());
        $this->assertStringContainsString('var(--cc-production-bg-light)', $altaSubtask->systemBadgeStyle());

        $clientSubtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'FOLLOW UP CLIENTE',
            'type' => RelatedTaskType::FOLLOW_UP_CLIENTE,
            'trigger_type' => 'SYSTEM_AUTOMATION',
            'is_work_task' => false,
        ]);
        $this->assertEquals('client', $clientSubtask->colorCodingKey());
        $this->assertStringContainsString('var(--cc-client-bg-light)', $clientSubtask->systemBadgeStyle());

        $camilaSubtask = RelatedTask::create([
            'order_id' => $order->id,
            'title' => 'FOLLOW UP CAMILA',
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA,
            'trigger_type' => 'SYSTEM_AUTOMATION',
            'is_work_task' => false,
        ]);
        $this->assertEquals('camila', $camilaSubtask->colorCodingKey());
        $this->assertStringContainsString('var(--cc-camila-bg-light)', $camilaSubtask->systemBadgeStyle());
    }
}
