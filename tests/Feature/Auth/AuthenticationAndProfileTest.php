<?php

namespace Tests\Feature\Auth;

use App\Enums\CoreStatus;
use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard\Index;
use App\Livewire\Kanban\Board;
use App\Livewire\Orders\OrderDetailModal;
use App\Livewire\Planner\WeeklyPlanner;
use App\Livewire\Settings\ProfileSettings;
use App\Livewire\Settings\UserManagement;
use App\Models\Designer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationAndProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_successfully_for_guests(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee(config('app.name'));
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

    public function test_authenticated_user_can_recover_password_without_knowing_current_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('forgotten-password-999'),
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileSettings::class)
            ->call('openRecoveryModal')
            ->assertSet('showRecoveryModal', true)
            ->set('recovery_new_password', 'brand-new-pass-456')
            ->set('recovery_new_password_confirmation', 'brand-new-pass-456')
            ->call('resetPasswordWithSession')
            ->assertHasNoErrors()
            ->assertSet('showRecoveryModal', false);

        $this->assertTrue(auth()->attempt([
            'email' => $user->email,
            'password' => 'brand-new-pass-456',
        ]));
    }

    public function test_user_can_request_password_reset_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ProfileSettings::class)
            ->call('sendPasswordResetEmail')
            ->assertHasNoErrors();

        Notification::assertSentTo(
            $user,
            ResetPassword::class
        );
    }

    public function test_user_can_update_phone_with_country_code(): void
    {
        $user = User::factory()->create([
            'phone' => '+58 412 1111111',
        ]);

        $this->actingAs($user);

        Livewire::test(ProfileSettings::class)
            ->assertSet('phone_country', '+58')
            ->assertSet('phone_number', '412 1111111')
            ->set('phone_country', '+57')
            ->set('phone_number', '300 999 8888')
            ->call('updateProfile')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '+57 300 999 8888',
        ]);
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

    public function test_editing_user_without_change_password_flag_preserves_existing_password(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $targetUser = User::factory()->create([
            'role' => UserRole::DESIGNER,
            'password' => bcrypt('my-secret-vault-password'),
        ]);

        $this->actingAs($admin);

        Livewire::test(UserManagement::class)
            ->call('openEditModal', $targetUser->id)
            ->assertSet('changePassword', false)
            ->set('name', 'Nombre Actualizado')
            ->call('saveUser')
            ->assertHasNoErrors();

        $targetUser->refresh();
        $this->assertEquals('Nombre Actualizado', $targetUser->name);
        $this->assertTrue(Hash::check('my-secret-vault-password', $targetUser->password));
    }

    public function test_login_fill_demo_credentials_handles_default_and_custom_passwords(): void
    {
        $defaultUser = User::factory()->create([
            'email' => 'default@kudos.com',
            'password' => bcrypt('password'),
        ]);

        $customUser = User::factory()->create([
            'email' => 'custom@kudos.com',
            'password' => bcrypt('custom-super-secret'),
        ]);

        Livewire::test(Login::class)
            ->call('fillDemoCredentials', 'default@kudos.com')
            ->assertSet('email', 'default@kudos.com')
            ->assertSet('password', 'password');

        Livewire::test(Login::class)
            ->call('fillDemoCredentials', 'custom@kudos.com')
            ->assertSet('email', 'custom@kudos.com')
            ->assertSet('password', '');
    }

    public function test_login_page_renders_four_demo_account_buttons_with_icons(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);

        // Ensure the 4 requested demo buttons are present
        $response->assertSee('Admin');
        $response->assertSee('Manager');
        $response->assertSee('Designer');
        $response->assertSee('Comercial');

        // Ensure old labels and emojis are no longer present
        $response->assertDontSee('PM Camila');
        $response->assertDontSee('Euralíz (Admin)');
        $response->assertDontSee('👑');
        $response->assertDontSee('📋');
        $response->assertDontSee('🎨');
        $response->assertDontSee('💼');
    }

    public function test_user_can_switch_tabs_and_update_preferences(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ProfileSettings::class)
            ->call('setTab', 'preferences')
            ->assertSet('activeTab', 'preferences')
            ->call('setLocale', 'en')
            ->assertRedirect(route('settings.profile', ['tab' => 'preferences']))
            ->assertSessionHas('locale', 'en');

        $user->refresh();
        $this->assertEquals('en', $user->getPreference('locale'));

        Livewire::test(ProfileSettings::class)
            ->set('date_format', 'm/d/Y')
            ->set('default_landing_page', 'planner')
            ->call('updatePreferences')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertEquals('m/d/Y', $user->getPreference('date_format'));
        $this->assertEquals('planner', $user->getPreference('default_landing_page'));
    }

    public function test_user_can_update_notification_preferences(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(ProfileSettings::class)
            ->call('setTab', 'notifications')
            ->set('notify_order_assigned', true)
            ->set('notify_order_blocked', false)
            ->set('notify_sound_enabled', true)
            ->call('updateNotificationSettings')
            ->assertHasNoErrors();

        $user->refresh();
        $notifications = $user->getPreference('notifications');
        $this->assertTrue($notifications['order_assigned']);
        $this->assertFalse($notifications['order_blocked']);
        $this->assertTrue($notifications['sound_enabled']);
    }

    public function test_profile_page_renders_logout_button_and_sections(): void
    {
        $user = User::factory()->create(['role' => UserRole::DESIGNER]);
        $this->actingAs($user);

        $response = $this->get(route('settings.profile'));
        $response->assertStatus(200);
        $response->assertSee(__('Cerrar Sesión'));
        $response->assertSee(__('Mi Perfil y Configuración'));
        $response->assertSee(__('General & Perfil'));
        $response->assertSee(__('Notificaciones'));
        $response->assertDontSee(__('Ficha Operativa de Diseñador'));
    }

    public function test_user_can_upload_profile_picture(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->image('profile.jpg', 200, 200);

        Livewire::test(ProfileSettings::class)
            ->set('avatar_file', $file)
            ->call('updateProfile')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertNotNull($user->avatar_url);
        $this->assertStringStartsWith('/storage/avatars/', $user->avatar_url);

        $storedPath = str_replace('/storage/', '', $user->avatar_url);
        Storage::disk('public')->assertExists($storedPath);
    }

    public function test_user_can_remove_profile_picture(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_url' => '/storage/avatars/old_avatar.png',
        ]);
        Storage::disk('public')->put('avatars/old_avatar.png', 'fake image content');

        $this->actingAs($user);

        Livewire::test(ProfileSettings::class)
            ->call('removeAvatar')
            ->assertHasNoErrors()
            ->assertSet('avatar_url', '');

        $user->refresh();
        $this->assertNull($user->avatar_url);
        Storage::disk('public')->assertMissing('avatars/old_avatar.png');
    }

    public function test_profile_picture_validation_rejects_non_image(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $file = UploadedFile::fake()->create('document.pdf', 500);

        Livewire::test(ProfileSettings::class)
            ->set('avatar_file', $file)
            ->call('updateProfile')
            ->assertHasErrors(['avatar_file']);
    }

    public function test_designer_is_redirected_away_from_overview(): void
    {
        $user = User::factory()->create(['role' => UserRole::DESIGNER]);
        $this->actingAs($user);

        $response = $this->get(route('overview'));
        $response->assertRedirect(route('kanban'));
    }

    public function test_designer_cannot_access_technical_settings(): void
    {
        $designer = User::factory()->create(['role' => UserRole::DESIGNER]);
        $this->actingAs($designer);

        $this->get('/settings/trello-mapping')->assertStatus(403);
        $this->get('/settings/backups')->assertStatus(403);
        $this->get('/settings/substatuses')->assertStatus(403);
        $this->get('/settings/subtasks')->assertStatus(403);
        $this->get('/trello-sync')->assertStatus(403);
        $this->get('/trash')->assertStatus(403);
    }

    public function test_coordinator_can_access_substatuses_and_subtasks_but_not_backups(): void
    {
        $coordinator = User::factory()->create(['role' => UserRole::COORDINATOR]);
        $this->actingAs($coordinator);

        $this->get('/settings/substatuses')->assertStatus(200);
        $this->get('/settings/subtasks')->assertStatus(200);
        $this->get('/trello-sync')->assertStatus(200);
        $this->get('/trash')->assertStatus(200);
        $this->get('/settings/trello-mapping')->assertStatus(403);
        $this->get('/settings/backups')->assertStatus(403);
    }

    public function test_designer_kanban_and_planner_defaults_to_their_own_orders(): void
    {
        $designerModel = Designer::create(['name' => 'Diseñador Filtro', 'active' => true]);
        $user = User::factory()->create(['role' => UserRole::DESIGNER]);
        $designerModel->update(['user_id' => $user->id]);

        $this->actingAs($user);

        Livewire::test(Board::class)
            ->assertSet('designerFilter', (string) $designerModel->id);

        Livewire::test(WeeklyPlanner::class)
            ->assertSet('selectedDesignerFilter', (string) $designerModel->id);
    }

    public function test_designer_cannot_trash_orders(): void
    {
        $designer = User::factory()->create(['role' => UserRole::DESIGNER]);
        $order = Order::create([
            'company_name' => 'Empresa Test',
            'task_name' => 'Diseño Test',
            'core_status' => CoreStatus::ENTRANTE,
        ]);

        $this->actingAs($designer);

        Livewire::test(Board::class)
            ->call('trashOrder', $order->id)
            ->assertStatus(403);

        Livewire::test(OrderDetailModal::class, ['orderId' => $order->id])
            ->call('deleteOrder')
            ->assertStatus(403);
    }

    public function test_designer_is_redirected_away_from_analytics_and_resolver(): void
    {
        $designer = User::factory()->create(['role' => UserRole::DESIGNER]);
        $this->actingAs($designer);

        $this->get(route('analytics'))->assertRedirect(route('kanban'));
        $this->get(route('resolver'))->assertRedirect(route('kanban'));

        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertDontSee('/analytics');
        $dashboardResponse->assertDontSee('/resolver');
        $dashboardResponse->assertDontSee('id="tour-stats-resolver"', false);

        Livewire::test(Index::class)
            ->call('setActiveTab', 'resolver')
            ->assertSet('activeTab', 'all');
    }

    public function test_admin_can_access_analytics_and_resolver(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->actingAs($admin);

        $this->get(route('analytics'))->assertStatus(200);
        $this->get(route('resolver'))->assertStatus(200);

        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('/analytics');
        $dashboardResponse->assertSee('/resolver');
        $dashboardResponse->assertSee('id="tour-stats-resolver"', false);
    }
}
