<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\UserRole;
use App\Livewire\Notifications\NotificationCenter;
use App\Livewire\Settings\NotificationSettings;
use App\Models\Designer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_actor_does_not_receive_notification_for_own_action(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $designerUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        $designer = Designer::create([
            'name' => $designerUser->name,
            'slug' => 'designer-test',
            'hex_color' => '#000000',
            'user_id' => $designerUser->id,
            'active' => true,
        ]);

        $this->actingAs($admin);

        $order = Order::create([
            'company_name' => 'Empresa Test',
            'task_name' => 'Tarea Test 1',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'designer_id' => $designer->id,
            'in_workspace' => true,
        ]);

        // Admin (the actor who created the order) should NOT receive notification
        $this->assertEquals(0, $admin->fresh()->notifications()->count());

        // Designer assigned SHOULD receive notification
        $this->assertEquals(1, $designerUser->fresh()->notifications()->count());
    }

    public function test_designer_only_sees_notifications_for_assigned_orders(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $designerUser1 = User::factory()->create(['role' => UserRole::DESIGNER]);
        $designer1 = Designer::create([
            'name' => $designerUser1->name,
            'slug' => 'designer-1',
            'hex_color' => '#111111',
            'user_id' => $designerUser1->id,
            'active' => true,
        ]);

        $designerUser2 = User::factory()->create(['role' => UserRole::DESIGNER]);
        $designer2 = Designer::create([
            'name' => $designerUser2->name,
            'slug' => 'designer-2',
            'hex_color' => '#222222',
            'user_id' => $designerUser2->id,
            'active' => true,
        ]);

        $this->actingAs($admin);

        $order1 = Order::create([
            'company_name' => 'Empresa 1',
            'task_name' => 'Tarea 1',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'designer_id' => $designer1->id,
            'in_workspace' => true,
        ]);

        $order2 = Order::create([
            'company_name' => 'Empresa 2',
            'task_name' => 'Tarea 2',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'designer_id' => $designer2->id,
            'in_workspace' => true,
        ]);

        // Dispatch notification for order1
        NotificationDispatcher::dispatch(
            eventType: 'status_changed',
            title: 'Cambio de Estatus Especial',
            message: 'Estatus cambiado',
            order: $order1,
            actor: $admin
        );

        $this->actingAs($designerUser1);
        Livewire::test(NotificationCenter::class)
            ->assertSee('Cambio de Estatus Especial');

        $this->actingAs($designerUser2);
        Livewire::test(NotificationCenter::class)
            ->assertDontSee('Cambio de Estatus Especial');
    }

    public function test_admin_can_toggle_notification_settings(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->actingAs($admin);

        Livewire::test(NotificationSettings::class)
            ->call('toggleSetting', 'new_order')
            ->assertHasNoErrors();

        $saved = Setting::get('admin_notification_settings');
        $this->assertNotNull($saved);
        $settings = is_array($saved) ? $saved : json_decode($saved, true);
        $this->assertFalse($settings['new_order']);
    }

    public function test_urgent_notifications_are_sorted_on_top(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $designerUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        $designer = Designer::create([
            'name' => $designerUser->name,
            'slug' => 'designer-urgent',
            'hex_color' => '#333333',
            'user_id' => $designerUser->id,
            'active' => true,
        ]);

        $this->actingAs($admin);

        $order = Order::create([
            'company_name' => 'Empresa Urgent',
            'task_name' => 'Tarea Urgent',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'designer_id' => $designer->id,
            'in_workspace' => true,
        ]);

        // Normal notification
        NotificationDispatcher::dispatch(
            eventType: 'new_comment',
            title: 'Nuevo Comentario',
            message: 'Comentario normal',
            order: $order,
            actor: $admin
        );

        // Urgent notification
        NotificationDispatcher::dispatch(
            eventType: 'order_overdue',
            title: 'Orden Atrasada',
            message: 'Alerta urgente de atraso',
            order: $order,
            actor: $admin,
            isUrgent: true
        );

        $this->actingAs($designerUser);

        $component = Livewire::test(NotificationCenter::class);
        $notifications = $component->viewData('notifications');

        // designerUser gets 3 notifications (1 from created, 1 comment, 1 overdue)
        $this->assertGreaterThanOrEqual(3, $notifications->count());

        $first = $notifications->first();
        $this->assertEquals('order_overdue', $first->data['event_type']);
        $this->assertTrue($first->data['is_urgent']);
    }
}
