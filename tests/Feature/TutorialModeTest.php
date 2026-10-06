<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorialModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_render_demo_tour_trigger(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
        $response->assertDontSee('Modo Demo', false);
        $response->assertDontSee('tour-demo-btn', false);
        $response->assertSee('tour-dashboard-stats', false);
        $response->assertSee('tour-designer-colors', false);
    }

    public function test_all_demo_acts_routes_have_tour_targets(): void
    {
        $user = User::factory()->create();

        $routesWithTargets = [
            '/' => 'tour-dashboard-stats',
            '/trello-sync' => 'tour-trello-sync-header',
            '/kanban' => 'tour-kanban-header',
            '/resolver' => 'tour-resolver-header',
            '/planner' => 'tour-planner-header',
            '/clients' => 'tour-client-header',
            '/analytics' => 'tour-analytics-header',
        ];

        foreach ($routesWithTargets as $route => $targetId) {
            $response = $this->actingAs($user)->get($route);
            $response->assertStatus(200);
            $response->assertSee($targetId, false);
        }
    }
}
