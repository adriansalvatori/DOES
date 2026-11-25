<?php

namespace Tests\Feature\Switchover;

use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function legacyRedirects(): array
    {
        return [
            'kanban' => ['/kanban', '/board'],
            'planner' => ['/planner', '/plan-week'],
            'tasks' => ['/tasks', '/'],
            'dashboard' => ['/dashboard', '/'],
            'today' => ['/today', '/'],
            'archived' => ['/archived', '/work-orders?tab=done'],
            'trello sync' => ['/trello-sync', '/settings'],
            'trello mapping' => ['/settings/trello-mapping', '/settings'],
            'substatuses' => ['/settings/substatuses', '/settings/reasons'],
            'subtasks' => ['/settings/subtasks', '/settings/task-templates'],
            'v2 board' => ['/v2/board', '/board'],
            'v2 today' => ['/v2/today', '/'],
            'v2 settings' => ['/v2/settings/board', '/settings/board'],
        ];
    }

    #[DataProvider('legacyRedirects')]
    public function test_legacy_urls_redirect_to_the_final_screens(string $from, string $to): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get($from)
            ->assertRedirect(url($to));
    }

    public function test_v2_work_order_urls_redirect_to_the_final_page(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/v2/work-orders/'.$workOrder->getKey())
            ->assertRedirect(route('work-orders.show', $workOrder));
    }

    public function test_every_final_screen_opens_for_an_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $workOrder = WorkOrder::factory()->create();

        foreach (['today', 'board', 'plan-week', 'work-orders.index', 'resolver', 'overview', 'backlog', 'analytics', 'settings.reasons', 'settings.task-templates', 'settings.board'] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }

        $this->actingAs($admin)->get(route('work-orders.show', $workOrder))->assertOk();
    }

    public function test_root_is_today_and_the_dashboard_name_still_resolves(): void
    {
        $this->assertSame('/', route('today', absolute: false));
        $this->assertTrue(Route::has('dashboard'));
        $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))->assertRedirect('/');
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk();
    }

    public function test_every_legacy_route_name_still_referenced_in_code_resolves(): void
    {
        foreach (['dashboard', 'kanban', 'planner', 'tasks', 'archived', 'trello-sync', 'settings.substatuses', 'settings.subtasks', 'settings.trello-mapping', 'v2.board', 'v2.work-orders.show', 'v2.resolver'] as $name) {
            $this->assertTrue(Route::has($name), $name);
        }
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/board')->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('login'));
    }
}
