<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class DesignSystemComponentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        App::setLocale('es');
        Carbon::setTestNow('2026-11-20 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_tag_maps_each_tone_to_its_health_color(): void
    {
        $this->blade('<x-ui.tag tone="danger">Atrasada</x-ui.tag>')
            ->assertSee('bg-danger-soft', false)
            ->assertSee('Atrasada');

        $this->blade('<x-ui.tag tone="warn">Bloqueada</x-ui.tag>')->assertSee('bg-warn-soft', false);
        $this->blade('<x-ui.tag tone="info">Con Camila</x-ui.tag>')->assertSee('bg-info-soft', false);
        $this->blade('<x-ui.tag tone="ok">Lista</x-ui.tag>')->assertSee('bg-ok-soft', false);
        $this->blade('<x-ui.tag>Neutral</x-ui.tag>')->assertSee('bg-sunken', false);
    }

    public function test_due_date_shows_relative_label_toned_by_health(): void
    {
        $this->blade('<x-orders.due :date="$date" />', ['date' => '2026-11-18'])
            ->assertSee('Atrasada 2 d')
            ->assertSee('bg-danger-soft', false);

        $this->blade('<x-orders.due :date="$date" />', ['date' => '2026-11-20'])
            ->assertSee('Hoy')
            ->assertSee('bg-warn-soft', false);

        $this->blade('<x-orders.due :date="$date" />', ['date' => '2026-11-21'])
            ->assertSee('Mañana')
            ->assertSee('bg-sunken', false);
    }

    public function test_due_date_is_neutral_when_done_and_hidden_without_date(): void
    {
        $this->blade('<x-orders.due :date="$date" :done="true" />', ['date' => '2026-11-18'])
            ->assertDontSee('Atrasada')
            ->assertDontSee('bg-danger-soft', false);

        $this->assertSame('', trim((string) $this->blade('<x-orders.due :date="null" />')));
    }

    public function test_location_shows_red_pin_and_hides_when_empty(): void
    {
        $this->blade('<x-orders.location location="Coral Way" />')
            ->assertSee('Coral Way')
            ->assertSee('text-pin', false);

        $this->assertSame('', trim((string) $this->blade('<x-orders.location :location="null" />')));
    }

    public function test_person_shows_initials_with_readable_text_on_identity_color(): void
    {
        $this->blade('<x-ui.person name="Euralíz Bravo" color="#F3A8FF" />')
            ->assertSee('EB')
            ->assertSee('background-color: #F3A8FF; color: #1f1f1d;', false);

        $this->blade('<x-ui.person name="César Guzmán" color="#1e3a8a" />')
            ->assertSee('CG')
            ->assertSee('color: #ffffff;', false);

        $this->blade('<x-ui.person />')->assertSee(__('Sin Asignar'));
    }

    public function test_stage_shows_phase_meter_without_hue(): void
    {
        $this->blade('<x-orders.stage :status="$status" />', ['status' => CoreStatus::ENVIADO_A_CAMILA])
            ->assertSee(__('Fase :current de 5', ['current' => 3]))
            ->assertSee(CoreStatus::ENVIADO_A_CAMILA->label());

        $this->blade('<x-orders.stage status="en producción" />')
            ->assertSee(__('Fase :current de 5', ['current' => 5]));
    }

    public function test_wo_badge_is_always_the_black_tag(): void
    {
        foreach (['dark', 'light', 'outline', 'amber'] as $variant) {
            $this->blade('<x-wo-badge number="WO 16412" :variant="$variant" />', ['variant' => $variant])
                ->assertSee('WO 16412')
                ->assertSee('bg-wo', false)
                ->assertSee('font-bold', false)
                ->assertDontSee('bg-amber', false);
        }
    }

    public function test_page_header_and_empty_state_render_their_content(): void
    {
        $this->blade('<x-ui.page-header title="Respaldos" subtitle="Copias diarias" />')
            ->assertSee('Respaldos')
            ->assertSee('Copias diarias')
            ->assertSee('text-title', false);

        $this->blade('<x-ui.empty title="No hay órdenes atrasadas" />')
            ->assertSee('No hay órdenes atrasadas');
    }

    public function test_kanban_card_shows_identity_and_health_without_a_badge_wall(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $order = Order::unguarded(fn () => Order::create([
            'company_name' => 'PANADERÍA SOL DE MAYO',
            'task_name' => 'BANNER 3X1 FACHADA',
            'wo_number' => 'WO 16412',
            'location_name' => 'Coral Way',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'current_due_date' => '2026-11-18',
            'in_workspace' => true,
        ]));

        $html = view('livewire.kanban.card', [
            'order' => $order->fresh(),
            'allColumns' => CoreStatus::cases(),
        ])->render();

        $this->assertStringContainsString('WO 16412', $html);
        $this->assertStringContainsString('bg-wo', $html);
        $this->assertStringContainsString('Coral Way', $html);
        $this->assertStringContainsString('text-pin', $html);
        $this->assertStringContainsString('Atrasada 2 d', $html);
        $this->assertStringContainsString('data-flux-dropdown', $html);
        $this->assertStringNotContainsString('animate-ping', $html);
        $this->assertStringNotContainsString('<select', $html);
    }

    public function test_app_shell_renders_role_based_navigation_once(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/settings/profile')->assertOk();
        $response->assertSee(__('Centro de Control'));
        $response->assertSee('/settings/backups', false);
        $response->assertDontSee('Trello Workflow');
        $this->assertSame(2, substr_count($response->getContent(), 'href="/settings/backups"'), 'Settings pages list each settings link once in the sub-navigation rail and once in the ⌘K palette.');

        $designer = User::factory()->designer()->create();

        $this->actingAs($designer)->get('/settings/profile')
            ->assertOk()
            ->assertDontSee('href="/settings/backups"', false)
            ->assertDontSee('href="/analytics"', false);
    }
}
