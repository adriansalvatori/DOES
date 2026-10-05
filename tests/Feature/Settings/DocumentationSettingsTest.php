<?php

namespace Tests\Feature\Settings;

use App\Livewire\Settings\Documentation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_behavior_guide_page(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/settings/documentation');

        $response->assertStatus(200);
        $response->assertSeeLivewire(Documentation::class);
        $response->assertSee(__('Guía de Comportamientos'));
    }

    public function test_non_admin_users_cannot_access_behavior_guide_page(): void
    {
        $roles = ['designer', 'coordinator', 'sales'];

        foreach ($roles as $role) {
            $user = User::factory()->{$role}()->create();

            $response = $this->actingAs($user)->get('/settings/documentation');

            $response->assertStatus(403);
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/settings/documentation');

        $response->assertRedirect('/login');
    }

    public function test_behavior_guide_link_is_visible_in_layout_only_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $designer = User::factory()->designer()->create();

        $adminResponse = $this->actingAs($admin)->get('/settings/profile');
        $adminResponse->assertSee('/settings/documentation');
        $adminResponse->assertSee(__('Guía de Comportamientos'));

        $designerResponse = $this->actingAs($designer)->get('/settings/profile');
        $designerResponse->assertDontSee('/settings/documentation');
        $designerResponse->assertDontSee(__('Guía de Comportamientos'));
    }
}
