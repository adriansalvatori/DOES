<?php

namespace Tests\Feature;

use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\Orders\HeaderSearch;
use App\Models\Client;
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
        foreach (['/', '/board', '/plan-week', '/work-orders', '/resolver', '/overview', '/analytics', '/clients', '/contacts', '/pricing', '/design-resources', '/backlog', '/trash'] as $href) {
            $this->assertContains($href, $adminHrefs);
        }

        $designerHrefs = $this->hrefs(array_merge(...$navigation->primaryGroups(User::factory()->designer()->make())));
        $this->assertSame(['/', '/board', '/work-orders', '/clients', '/contacts', '/pricing', '/design-resources'], $designerHrefs);

        $leadDesignerHrefs = $this->hrefs(array_merge(...$navigation->primaryGroups(User::factory()->designer()->make(['is_lead' => true]))));
        $this->assertContains('/plan-week', $leadDesignerHrefs);

        $salesHrefs = $this->hrefs(array_merge(...$navigation->primaryGroups(User::factory()->sales()->make())));
        $this->assertContains('/analytics', $salesHrefs);
        $this->assertNotContains('/trash', $salesHrefs);
        $this->assertNotContains('/resolver', $salesHrefs);
        $this->assertNotContains('/plan-week', $salesHrefs);

        $customerServiceHrefs = $this->hrefs(array_merge(...$navigation->primaryGroups(User::factory()->customerService()->make())));
        $this->assertContains('/resolver', $customerServiceHrefs);
        $this->assertNotContains('/overview', $customerServiceHrefs);
        $this->assertNotContains('/backlog', $customerServiceHrefs);

        $coordinatorHrefs = $this->hrefs(array_merge(...$navigation->primaryGroups(User::factory()->coordinator()->make())));
        foreach (['/plan-week', '/resolver', '/overview', '/backlog', '/trash'] as $href) {
            $this->assertContains($href, $coordinatorHrefs);
        }
    }

    public function test_navigation_no_longer_offers_the_trello_screens(): void
    {
        $navigation = app(NavigationService::class);
        $admin = User::factory()->admin()->make();

        $hrefs = $this->hrefs([
            ...array_merge(...$navigation->primaryGroups($admin)),
            ...$navigation->bottomItems($admin),
            ...array_merge(...array_column($navigation->settingsGroups($admin), 'items')),
        ]);

        foreach (['/trello-sync', '/settings/trello-mapping', '/kanban', '/planner', '/archived', '/settings/substatuses', '/settings/subtasks'] as $legacy) {
            $this->assertNotContains($legacy, $hrefs);
        }
    }

    public function test_settings_groups_follow_role_permissions(): void
    {
        $navigation = app(NavigationService::class);

        $adminKeys = array_column($navigation->settingsGroups(User::factory()->admin()->make()), 'key');
        $this->assertSame(['account', 'team', 'workflow', 'integrations', 'data', 'help'], $adminKeys);

        $coordinatorGroups = $navigation->settingsGroups(User::factory()->coordinator()->make());
        $this->assertSame(['account', 'workflow'], array_column($coordinatorGroups, 'key'));
        $this->assertContains('/settings/reasons', $this->hrefs($coordinatorGroups[1]['items']));
        $this->assertContains('/settings/task-templates', $this->hrefs($coordinatorGroups[1]['items']));
        $this->assertContains('/settings/board', $this->hrefs($coordinatorGroups[1]['items']));

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

        $client = Client::create(['name' => 'PANADERÍA SOL DE MAYO']);
        WorkOrder::factory()->create(['number' => 'WO 16412', 'client_id' => $client->id]);

        Livewire::test(HeaderSearch::class)
            ->assertSee(__('Search or jump to…'))
            ->assertSee(__('New work order'))
            ->assertSee('href="/settings"', false)
            ->assertSee('href="/board"', false)
            ->assertDontSee(__('Traer Nuevas'))
            ->set('search', '16412')
            ->assertSee('WO 16412');
    }

    public function test_command_palette_hides_destinations_the_role_cannot_reach(): void
    {
        $this->actingAs(User::factory()->designer()->create());

        Livewire::test(HeaderSearch::class)
            ->assertSee('href="/board"', false)
            ->assertDontSee('href="/settings/backups"', false)
            ->assertDontSee('href="/analytics"', false);
    }
}
