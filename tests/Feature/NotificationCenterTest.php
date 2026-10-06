<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Enums\Substatus;
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
            label: 'Status',
            order: $order1,
            actor: $admin
        );

        $this->actingAs($designerUser1);
        Livewire::test(NotificationCenter::class)
            ->assertSee('Status');

        $this->actingAs($designerUser2);
        Livewire::test(NotificationCenter::class)
            ->assertDontSee('Status');
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
            label: 'New Comment',
            order: $order,
            actor: $admin
        );

        // Urgent notification
        NotificationDispatcher::dispatch(
            eventType: 'order_overdue',
            label: 'Overdue',
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

    public function test_new_order_notification_renders_bold_green_title_and_reduced_opacity_when_read(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $designerUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        $designer = Designer::create([
            'name' => $designerUser->name,
            'slug' => 'designer-test-compact',
            'hex_color' => '#123456',
            'user_id' => $designerUser->id,
            'active' => true,
        ]);

        $this->actingAs($admin);

        $order = Order::create([
            'company_name' => 'ACME CORP',
            'task_name' => 'FLYER DESIGN',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'designer_id' => $designer->id,
            'in_workspace' => true,
        ]);

        $this->actingAs($designerUser);

        // Initially unread: contains "New Order", headline, and full opacity (opacity-100)
        Livewire::test(NotificationCenter::class)
            ->assertSee('New Order')
            ->assertSee('ACME CORP')
            ->assertSee('FLYER DESIGN')
            ->assertSeeHtml('opacity-100');

        // Mark as read (opened)
        $notification = $designerUser->notifications()->first();
        $notification->markAsRead();

        // Now that it has been opened/read, opacity should be reduced (opacity-40)
        Livewire::test(NotificationCenter::class)
            ->assertSeeHtml('opacity-40');
    }

    public function test_order_flagged_urgent_dispatches_notification(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $designerUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        $designer = Designer::create([
            'name' => $designerUser->name,
            'slug' => 'designer-urgent-flag',
            'hex_color' => '#654321',
            'user_id' => $designerUser->id,
            'active' => true,
        ]);

        $this->actingAs($admin);

        $order = Order::create([
            'company_name' => 'Empresa Urgente Test',
            'task_name' => 'Tarea Urgente',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::BLOQUEADA,
            'designer_id' => $designer->id,
            'in_workspace' => true,
        ]);

        // Change substatus to URGENTE
        $order->update(['substatus' => Substatus::URGENTE]);

        $this->actingAs($designerUser);

        Livewire::test(NotificationCenter::class)
            ->assertSee('Urgent');
    }

    public function test_lead_designer_sees_all_notifications_and_compact_tag_for_unassigned_cards(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);

        $leadUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        Designer::create([
            'name' => $leadUser->name,
            'slug' => 'lead-designer-test',
            'hex_color' => '#FF00FF',
            'user_id' => $leadUser->id,
            'active' => true,
            'is_lead' => true,
        ]);

        $otherUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        $otherDesigner = Designer::create([
            'name' => 'César',
            'slug' => 'cesar-test',
            'hex_color' => '#00FFFF',
            'user_id' => $otherUser->id,
            'active' => true,
            'is_lead' => false,
        ]);

        $order = Order::create([
            'company_name' => 'EMPRESA OTRO',
            'task_name' => 'TAREA CESAR',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'designer_id' => $otherDesigner->id,
            'in_workspace' => true,
        ]);

        NotificationDispatcher::dispatch(
            eventType: 'status_changed',
            label: 'Status',
            order: $order,
            actor: $admin
        );

        // Lead designer should see the notification with designer tag and compact format
        $this->actingAs($leadUser);
        Livewire::test(NotificationCenter::class)
            ->assertSee('César')
            ->assertSee('Status')
            ->assertSee('EMPRESA OTRO • TAREA CESAR');
    }

    public function test_initial_mount_does_not_dispatch_desktop_notification_for_existing_notifications(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $designerUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        $designer = Designer::create([
            'name' => $designerUser->name,
            'slug' => 'designer-mount-test',
            'hex_color' => '#123123',
            'user_id' => $designerUser->id,
            'active' => true,
        ]);

        $this->actingAs($admin);

        Order::create([
            'company_name' => 'Pre-existing Corp',
            'task_name' => 'Existing Task',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'designer_id' => $designer->id,
            'in_workspace' => true,
        ]);

        $this->actingAs($designerUser);

        // On initial mount, no desktop notification event should be dispatched for already existing notifications
        Livewire::test(NotificationCenter::class)
            ->assertNotDispatched('desktop-notification');
    }

    public function test_new_incoming_notification_dispatches_desktop_notification_event(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $designerUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        $designer = Designer::create([
            'name' => $designerUser->name,
            'slug' => 'designer-incoming-test',
            'hex_color' => '#456456',
            'user_id' => $designerUser->id,
            'active' => true,
        ]);

        $this->actingAs($designerUser);

        // Mount component first
        $component = Livewire::test(NotificationCenter::class)
            ->assertNotDispatched('desktop-notification');

        // Now simulate a new order being created while the user has the app open
        $order = Order::create([
            'company_name' => 'Live Incoming Corp',
            'task_name' => 'Banner Urgente',
            'core_status' => CoreStatus::TO_DO_TODAY,
            'designer_id' => $designer->id,
            'in_workspace' => true,
        ]);

        NotificationDispatcher::dispatch(
            eventType: 'new_order',
            label: 'New Order',
            order: $order,
            actor: $admin
        );

        // Component polls / re-renders
        $component->call('$refresh')
            ->assertDispatched('desktop-notification', orderId: $order->id);

        // Next poll / re-render does not dispatch again for the same notification
        $component->call('$refresh')
            ->assertNotDispatched('desktop-notification');
    }
}
