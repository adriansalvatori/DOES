<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Livewire\Settings\ProfileSettings;
use App\Livewire\Settings\UserManagement;
use App\Models\Designer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_successfully_for_guests(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Kudos Design Ops');
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'designer@kudos.com',
            'password' => bcrypt('password123'),
            'active' => true,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'designer@kudos.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'inactive@kudos.com',
            'password' => bcrypt('password123'),
            'active' => false,
        ]);

        Livewire::test(Login::class)
            ->set('email', 'inactive@kudos.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors(['email']);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_update_profile_and_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileSettings::class)
            ->set('name', 'Nombre Actualizado')
            ->set('email', 'nuevo@kudos.com')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Nombre Actualizado',
            'email' => 'nuevo@kudos.com',
        ]);

        Livewire::test(ProfileSettings::class)
            ->set('current_password', 'old-password')
            ->set('new_password', 'new-password-123')
            ->set('new_password_confirmation', 'new-password-123')
            ->call('updatePassword')
            ->assertHasNoErrors();
    }

    public function test_admin_can_manage_users_and_bind_designer(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $designer = Designer::create(['name' => 'Diseñador Prueba', 'active' => true]);

        $this->actingAs($admin);

        Livewire::test(UserManagement::class)
            ->call('openCreateModal')
            ->set('name', 'Nuevo Diseñador')
            ->set('email', 'nuevo.designer@kudos.com')
            ->set('role', UserRole::DESIGNER->value)
            ->set('designer_id', $designer->id)
            ->set('password', 'secret-pass-123')
            ->call('saveUser')
            ->assertHasNoErrors();

        $newUser = User::where('email', 'nuevo.designer@kudos.com')->first();
        $this->assertNotNull($newUser);
        $this->assertEquals($newUser->id, $designer->fresh()->user_id);
    }
}
