<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorialModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_demo_tour_trigger_and_elements(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('Modo Demo', false);
        $response->assertSee('tour-demo-btn', false);
        $response->assertSee('tour-dashboard-stats', false);
        $response->assertSee('tour-designer-colors', false);
    }

    public function test_all_demo_acts_routes_have_tour_targets(): void
    {
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
            $response = $this->get($route);
            $response->assertStatus(200);
            $response->assertSee($targetId, false);
        }
    }
}
