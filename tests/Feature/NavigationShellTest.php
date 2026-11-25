<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Orders\HeaderSearch;
use App\Models\Order;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

class NavigationShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        App::setLocale('es');
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function hrefs(array $items): array
    {
        return array_column($items, 'href');
    }

    public function test_primary_navigation_is_filtered_by_role(): void
    {
        $navigation = app(NavigationService::class);

        $adminHrefs = $this->hrefs(array_merge(...$navigation->primaryGroups(User::factory()->admin()->make())));
        $this->assertContains('/analytics', $adminHrefs);
        $this->assertContains('/overview', $adminHrefs);
        $this->assertContains('/trash', $adminHrefs);

        $designerHrefs = $this->hrefs(array_merge(...$navigation->primaryGroups(User::factory()->designer()->make())));
        $this->assertNotContains('/analytics', $designerHrefs);
        $this->assertNotContains('/overview', $designerHrefs);
        $this->assertNotContains('/trash', $designerHrefs);
        $this->assertContains('/kanban', $designerHrefs);

        $salesHrefs = $this->hrefs(array_merge(...$navigation->primaryGroups(User::factory()->sales()->make())));
        $this->assertNotContains('/trash', $salesHrefs);
    }

    public function test_settings_groups_follow_role_permissions(): void
    {
        $navigation = app(NavigationService::class);

        $adminKeys = array_column($navigation->settingsGroups(User::factory()->admin()->make()), 'key');
        $this->assertSame(['account', 'team', 'workflow', 'integrations', 'data', 'help'], $adminKeys);

        $coordinatorGroups = $navigation->settingsGroups(User::factory()->coordinator()->make());
        $this->assertSame(['account', 'workflow', 'integrations'], array_column($coordinatorGroups, 'key'));

        $designerGroups = $navigation->settingsGroups(User::factory()->designer()->make());
        $this->assertSame(['account'], array_column($designerGroups, 'key'));
        $this->assertSame(['/settings/profile', '/settings/language'], $this->hrefs($designerGroups[0]['items']));
    }

    public function test_settings_hub_shows_only_what_the_role_can_change(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/settings')
            ->assertOk()
            ->assertSee(__('Workflow'))
            ->assertSee('href="/settings/backups"', false)
            ->assertSee('text-title', false);

        $this->actingAs(User::factory()->designer()->create())
            ->get('/settings')
            ->assertOk()
            ->assertSee('href="/settings/profile"', false)
            ->assertDontSee('href="/settings/backups"', false)
            ->assertDontSee(__('Workflow'));
    }

    public function test_sidebar_has_a_single_settings_link_instead_of_an_accordion(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get('/settings');

        $response->assertOk();
        $response->assertSee('href="'.route('settings.index').'"', false);
        $response->assertDontSee('configPopover', false);
    }

    public function test_command_palette_offers_destinations_actions_and_live_order_search(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Order::unguarded(fn () => Order::create([
            'company_name' => 'PANADERÍA SOL DE MAYO',
            'task_name' => 'BANNER 3X1',
            'wo_number' => 'WO 16412',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true,
        ]));

        Livewire::test(HeaderSearch::class)
            ->assertSee(__('Search or jump to…'))
            ->assertSee(__('New Order'))
            ->assertSee('href="/settings"', false)
            ->assertSee('href="/kanban"', false)
            ->assertDontSee(__('Traer Nuevas'))
            ->set('search', 'PANADERÍA')
            ->assertSee('PANADERÍA SOL DE MAYO')
            ->assertSee('WO 16412');
    }

    public function test_command_palette_hides_destinations_the_role_cannot_reach(): void
    {
        $this->actingAs(User::factory()->designer()->create());

        Livewire::test(HeaderSearch::class)
            ->assertSee('href="/kanban"', false)
            ->assertDontSee('href="/settings/backups"', false)
            ->assertDontSee('href="/analytics"', false);
    }
}
