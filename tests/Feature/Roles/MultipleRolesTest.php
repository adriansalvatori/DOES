<?php

namespace Tests\Feature\Roles;

use App\Domain\Delivery\Support\Roles;
use App\Enums\UserRole;
use App\Livewire\PlanWeek\PlanWeekPage;
use App\Livewire\Settings\UserManagement;
use App\Livewire\Today\TodayPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MultipleRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_list_falls_back_to_the_primary_role_and_has_role_matches_any(): void
    {
        $sales = User::factory()->sales()->create();

        $this->assertSame([UserRole::SALES], $sales->roleList());
        $this->assertTrue($sales->hasRole(UserRole::ADMIN, UserRole::SALES));
        $this->assertFalse($sales->hasRole(UserRole::ADMIN, UserRole::COORDINATOR));
    }

    public function test_the_primary_role_follows_the_priority_when_roles_are_saved(): void
    {
        $user = User::factory()->withRoles(UserRole::DESIGNER, UserRole::ADMIN)->create();

        $this->assertSame(UserRole::ADMIN, $user->fresh()->role);
        $this->assertEqualsCanonicalizing([UserRole::ADMIN, UserRole::DESIGNER], $user->fresh()->roleList());

        $user->update(['roles' => [UserRole::DESIGNER, UserRole::SALES]]);
        $this->assertSame(UserRole::SALES, $user->fresh()->role);

        $user->update(['roles' => [UserRole::CUSTOMER_SERVICE, UserRole::SALES]]);
        $this->assertSame(UserRole::CUSTOMER_SERVICE, $user->fresh()->role);
    }

    public function test_designer_only_semantics_exclude_admins_and_coordinators(): void
    {
        $this->assertTrue(User::factory()->designer()->create()->isDesigner());
        $this->assertFalse(User::factory()->withRoles(UserRole::ADMIN, UserRole::DESIGNER)->create()->isDesigner());
        $this->assertFalse(User::factory()->withRoles(UserRole::COORDINATOR, UserRole::DESIGNER)->create()->isDesigner());
        $this->assertTrue(User::factory()->withRoles(UserRole::SALES, UserRole::DESIGNER)->create()->isDesigner());

        $both = User::factory()->withRoles(UserRole::ADMIN, UserRole::DESIGNER)->create();
        $this->assertTrue($both->isAdmin());
        $this->assertTrue($both->designs());
        $this->assertTrue(User::factory()->admin()->create(['is_lead' => true])->designs());
        $this->assertFalse(User::factory()->admin()->create()->designs());
    }

    public function test_design_team_and_role_scope_include_multi_role_users(): void
    {
        $multi = User::factory()->withRoles(UserRole::ADMIN, UserRole::DESIGNER)->create();
        $plainAdmin = User::factory()->admin()->create();
        $csDesigner = User::factory()->withRoles(UserRole::CUSTOMER_SERVICE, UserRole::DESIGNER)->create();

        $team = User::query()->designTeam()->pluck('id')->all();
        $this->assertContains($multi->id, $team);
        $this->assertContains($csDesigner->id, $team);
        $this->assertNotContains($plainAdmin->id, $team);

        $support = User::query()->withAnyRole(UserRole::CUSTOMER_SERVICE)->pluck('id')->all();
        $this->assertSame([$csDesigner->id], $support);
    }

    public function test_an_admin_designer_sees_manager_sections_and_is_listed_as_a_designer(): void
    {
        $euraliz = User::factory()->withRoles(UserRole::ADMIN, UserRole::DESIGNER)->create(['name' => 'Euraliz Bravo']);

        Livewire::actingAs($euraliz)->test(TodayPage::class)->assertOk();
        Livewire::actingAs($euraliz)->test(PlanWeekPage::class)->assertOk()->assertSee('Euraliz Bravo');
        $this->assertTrue(Roles::isManager($euraliz));
    }

    public function test_user_management_saves_several_roles_and_derives_the_primary(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->designer()->create();

        Livewire::test(UserManagement::class)
            ->call('openEditModal', $user->id)
            ->assertSet('selectedRoles', ['designer'])
            ->set('selectedRoles', ['designer', 'coordinator'])
            ->call('saveUser')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame(UserRole::COORDINATOR, $user->role);
        $this->assertTrue($user->hasRole(UserRole::DESIGNER, UserRole::COORDINATOR));
        $this->assertFalse($user->isDesigner());
    }

    public function test_user_management_requires_at_least_one_valid_role(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UserManagement::class)
            ->call('openCreateModal')
            ->set('name', 'No Role')
            ->set('email', 'norole@example.test')
            ->set('password', 'secret-pass-123')
            ->set('selectedRoles', [])
            ->call('saveUser')
            ->assertHasErrors(['selectedRoles'])
            ->set('selectedRoles', ['nope'])
            ->call('saveUser')
            ->assertHasErrors(['selectedRoles.0']);

        $this->assertDatabaseMissing('users', ['email' => 'norole@example.test']);
    }
}
