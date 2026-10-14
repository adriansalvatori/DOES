<?php

namespace Tests\Feature\Settings;

use App\Enums\UserRole;
use App\Livewire\Settings\UserManagement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalManagerSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_approval_manager_settings(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $managerUser = User::factory()->create([
            'name' => 'Gabriela Requena',
            'email' => 'cs@kudosprintmedia.com',
            'role' => UserRole::COORDINATOR,
        ]);

        $otherUser = User::factory()->create([
            'name' => 'Sofia Morales',
            'email' => 'sofia@kudos.com',
            'role' => UserRole::COORDINATOR,
        ]);

        $this->actingAs($admin);

        Livewire::test(UserManagement::class)
            ->assertSet('manager_name', 'Gabriela')
            ->set('manager_id', $otherUser->id)
            ->assertSet('manager_name', 'Sofia')
            ->set('manager_name', 'Sofia Manager')
            ->call('saveManagerSettings')
            ->assertHasNoErrors()
            ->assertSee(__('Configuración del Manager de Aprobaciones actualizada exitosamente.'));

        $this->assertEquals($otherUser->id, (int) Setting::get('manager_id'));
        $this->assertEquals('Sofia Manager', Setting::get('manager_name'));
        $this->assertEquals('Sofia Manager', Setting::getManagerName());
        $this->assertEquals($otherUser->id, Setting::getManagerUser()?->id);
    }

    public function test_manager_settings_validation(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::ADMIN,
        ]);

        $this->actingAs($admin);

        Livewire::test(UserManagement::class)
            ->set('manager_id', 999999)
            ->set('manager_name', '')
            ->call('saveManagerSettings')
            ->assertHasErrors(['manager_id', 'manager_name']);
    }
}
