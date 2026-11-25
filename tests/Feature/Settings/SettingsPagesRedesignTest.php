<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsPagesRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_owned_settings_pages_use_the_shared_page_header(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $routes = [
            'settings.backups',
            'settings.color-coding',
            'settings.csv-reconciliation',
            'settings.documentation',
            'settings.installation-types',
            'settings.language',
            'settings.notifications',
            'settings.profile',
            'settings.reasons',
            'settings.task-templates',
            'settings.board',
            'settings.users',
        ];

        foreach ($routes as $route) {
            $response = $this->actingAs($admin)->get(route($route));

            $response->assertOk();
            $response->assertSee('text-title', false);
            $response->assertDontSee('text-3xl', false);
            $response->assertDontSee('font-extrabold', false);
        }
    }
}
