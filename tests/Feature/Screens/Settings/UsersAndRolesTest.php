<?php

namespace Tests\Feature\Screens\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\UserManagement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UsersAndRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_it(): void
    {
        foreach (['coordinator', 'designer', 'sales', 'customerService'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());
            Livewire::test(UserManagement::class)->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(UserManagement::class)->assertOk();
    }

    public function test_role_picker_offers_every_role_including_customer_service(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $component = Livewire::test(UserManagement::class)->call('openCreateModal');

        foreach (UserRole::cases() as $role) {
            $component->assertSeeHtml('value="'.$role->value.'"');
        }
        $component->assertSee(UserRole::CUSTOMER_SERVICE->label())->assertSee(__('Reviewer'))->assertSee(__('Design lead'))->assertSee(__('WIP limit'));
    }

    public function test_a_customer_service_reviewer_lead_user_is_created_with_flags_and_wip_limit(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UserManagement::class)
            ->call('openCreateModal')
            ->set('name', 'Katherine')
            ->set('email', 'katherine@example.test')
            ->set('password', 'secret-pass-123')
            ->set('role', UserRole::CUSTOMER_SERVICE->value)
            ->set('is_reviewer', true)
            ->set('is_lead', true)
            ->set('wip_limit', 7)
            ->call('saveUser')
            ->assertHasNoErrors();

        $user = User::where('email', 'katherine@example.test')->first();
        $this->assertSame(UserRole::CUSTOMER_SERVICE, $user->role);
        $this->assertTrue($user->is_reviewer);
        $this->assertTrue($user->is_lead);
        $this->assertSame(7, $user->wip_limit);
    }

    public function test_new_users_start_with_the_default_wip_limit_from_board_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Setting::set('default_wip_limit', 9);

        Livewire::test(UserManagement::class)->call('openCreateModal')->assertSet('wip_limit', 9);
    }

    public function test_flags_and_wip_limit_are_edited_on_an_existing_user(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->designer()->create(['wip_limit' => 4, 'is_reviewer' => false, 'is_lead' => false]);

        Livewire::test(UserManagement::class)
            ->call('openEditModal', $user->id)
            ->assertSet('wip_limit', 4)
            ->assertSet('is_reviewer', false)
            ->set('is_reviewer', true)
            ->set('wip_limit', 3)
            ->call('saveUser')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue($user->is_reviewer);
        $this->assertFalse($user->is_lead);
        $this->assertSame(3, $user->wip_limit);
        $this->assertSame(UserRole::DESIGNER, $user->role);
    }

    public function test_wip_limit_and_role_are_validated(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->designer()->create();

        Livewire::test(UserManagement::class)
            ->call('openEditModal', $user->id)
            ->set('wip_limit', 0)
            ->set('role', 'superuser')
            ->call('saveUser')
            ->assertHasErrors(['wip_limit', 'role']);
    }

    public function test_table_shows_reviewer_and_lead_badges(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        User::factory()->designer()->create(['name' => 'Camila Rev', 'is_reviewer' => true, 'is_lead' => true, 'wip_limit' => 2]);

        Livewire::test(UserManagement::class)->assertSee('Camila Rev')->assertSee(__('Reviewer'))->assertSee(__('Design lead'))->assertSee(__('WIP :count', ['count' => 2]));
    }

    public function test_cs_whatsapp_and_approvals_manager_settings_still_save(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(UserManagement::class)
            ->set('cs_whatsapp_phone', '+1 (678) 999-8877')
            ->call('saveCsWhatsapp')
            ->set('manager_id', $admin->id)
            ->set('manager_name', 'Gabriela')
            ->call('saveManagerSettings')
            ->assertHasNoErrors();

        $this->assertSame('+1 (678) 999-8877', Setting::get('cs_whatsapp_phone'));
        $this->assertSame('Gabriela', Setting::get('manager_name'));
    }
}
