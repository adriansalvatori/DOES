<?php

namespace Tests\Feature\Characterization;

use App\Enums\CoreStatus;
use App\Enums\UserRole;
use App\Livewire\Notifications\NotificationCenter;
use App\Models\Designer;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\NotificationDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Characterizes who receives which notification (NotificationDispatcher) and what the
 * NotificationCenter shows each role.
 */
class SupportNotificationRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $coordinator;

    private User $leadUser;

    private User $assignedUser;

    private User $otherDesignerUser;

    private User $salesUser;

    private User $inactiveAdmin;

    private Designer $assignedDesigner;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->coordinator = User::factory()->create(['role' => UserRole::COORDINATOR]);
        $this->salesUser = User::factory()->create(['role' => UserRole::SALES]);
        $this->inactiveAdmin = User::factory()->create(['role' => UserRole::ADMIN, 'active' => false]);

        $this->leadUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        Designer::create(['name' => 'Lead D', 'slug' => 'lead-d', 'user_id' => $this->leadUser->id, 'active' => true, 'is_lead' => true]);

        $this->assignedUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        $this->assignedDesigner = Designer::create(['name' => 'Assigned D', 'slug' => 'assigned-d', 'user_id' => $this->assignedUser->id, 'active' => true]);

        $this->otherDesignerUser = User::factory()->create(['role' => UserRole::DESIGNER]);
        Designer::create(['name' => 'Other D', 'slug' => 'other-d', 'user_id' => $this->otherDesignerUser->id, 'active' => true]);

        $this->order = Order::create([
            'company_name' => 'NOTIFY CO', 'task_name' => 'Poster', 'core_status' => CoreStatus::TO_DO_TODAY,
            'in_workspace' => true, 'designer_id' => $this->assignedDesigner->id,
        ]);

        DB::table('notifications')->delete();
    }

    /**
     * @return array<int, int>
     */
    private function recipientIds(string $eventType): array
    {
        return DB::table('notifications')
            ->where('data->event_type', $eventType)
            ->pluck('notifiable_id')
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, User>  $users
     * @return array<int, int>
     */
    private function ids(array $users): array
    {
        return collect($users)->map(fn (User $user) => $user->id)->sort()->values()->all();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function eventTypes(): array
    {
        return [
            'new_order' => ['new_order'],
            'status_changed' => ['status_changed'],
            'order_blocked' => ['order_blocked'],
            'order_overdue' => ['order_overdue'],
            'order_urgent' => ['order_urgent'],
            'new_comment' => ['new_comment'],
            'welcome_email_sent' => ['welcome_email_sent'],
            'unknown_event' => ['some_custom_event'],
        ];
    }

    #[DataProvider('eventTypes')]
    public function test_every_event_type_goes_to_admins_coordinators_leads_and_the_assigned_designer(string $eventType): void
    {
        NotificationDispatcher::dispatch(eventType: $eventType, label: 'L', order: $this->order);

        $this->assertSame(
            $this->ids([$this->admin, $this->coordinator, $this->leadUser, $this->assignedUser]),
            $this->recipientIds($eventType)
        );
    }

    public function test_sales_other_designers_and_inactive_users_never_receive_notifications(): void
    {
        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'L', order: $this->order);

        $recipients = $this->recipientIds('status_changed');
        $this->assertNotContains($this->salesUser->id, $recipients);
        $this->assertNotContains($this->otherDesignerUser->id, $recipients);
        $this->assertNotContains($this->inactiveAdmin->id, $recipients);
    }

    public function test_actor_is_excluded_from_recipients(): void
    {
        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'L', order: $this->order, actor: $this->admin);

        $this->assertSame($this->ids([$this->coordinator, $this->leadUser, $this->assignedUser]), $this->recipientIds('status_changed'));
    }

    public function test_authenticated_user_is_the_default_actor(): void
    {
        $this->actingAs($this->assignedUser);

        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'L', order: $this->order);

        $this->assertNotContains($this->assignedUser->id, $this->recipientIds('status_changed'));
    }

    public function test_notification_without_order_goes_to_managers_and_leads_only(): void
    {
        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'L');

        $this->assertSame($this->ids([$this->admin, $this->coordinator, $this->leadUser]), $this->recipientIds('status_changed'));
    }

    public function test_order_without_assigned_designer_notifies_managers_and_leads_only(): void
    {
        $unassigned = Order::create(['company_name' => 'NO DESIGNER', 'task_name' => 'x', 'core_status' => CoreStatus::ENTRANTE, 'in_workspace' => false]);
        DB::table('notifications')->delete();

        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'L', order: $unassigned);

        $this->assertSame($this->ids([$this->admin, $this->coordinator, $this->leadUser]), $this->recipientIds('status_changed'));
    }

    public function test_event_disabled_in_admin_settings_dispatches_nothing_while_others_still_do(): void
    {
        Setting::set('admin_notification_settings', json_encode(['status_changed' => false]));

        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'L', order: $this->order);
        NotificationDispatcher::dispatch(eventType: 'new_comment', label: 'L', order: $this->order);

        $this->assertSame([], $this->recipientIds('status_changed'));
        $this->assertCount(4, $this->recipientIds('new_comment'));
    }

    public function test_overdue_and_urgent_events_are_always_flagged_urgent(): void
    {
        NotificationDispatcher::dispatch(eventType: 'order_overdue', label: 'L', order: $this->order, isUrgent: false);
        NotificationDispatcher::dispatch(eventType: 'order_urgent', label: 'L', order: $this->order, isUrgent: false);
        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'L', order: $this->order, isUrgent: false);

        $this->assertSame(8, DB::table('notifications')->whereIn('data->event_type', ['order_overdue', 'order_urgent'])->where('data->is_urgent', true)->count());
        $this->assertSame(0, DB::table('notifications')->where('data->event_type', 'status_changed')->where('data->is_urgent', true)->count());
    }

    public function test_payload_carries_order_designer_and_actor_details(): void
    {
        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'Status', order: $this->order, detailText: 'A -> B', actor: $this->admin);

        $data = $this->coordinator->notifications()->first()->data;
        $this->assertSame($this->order->id, $data['order_id']);
        $this->assertSame('NOTIFY CO', $data['company_name']);
        $this->assertSame('POSTER', $data['task_name']);
        $this->assertSame('A -> B', $data['detail_text']);
        $this->assertSame($this->admin->id, $data['actor_id']);
        $this->assertSame($this->assignedDesigner->id, $data['designer_id']);
        $this->assertSame('Assigned D', $data['designer_name']);
    }

    public function test_notification_center_designer_sees_only_assigned_orders_but_general_notices(): void
    {
        $otherOrder = Order::create(['company_name' => 'ELSEWHERE', 'task_name' => 'x', 'core_status' => CoreStatus::ENTRANTE, 'in_workspace' => true]);
        DB::table('notifications')->delete();

        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'MINE', order: $this->order, actor: $this->admin);
        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'NOTMINE', order: $otherOrder, actor: $this->admin);
        $this->otherDesignerUser->notify(new SystemNotification('general', 'GENERALNOTICE', 'General', 'Orden'));
        $this->assignedUser->notify(new SystemNotification('general', 'GENERALNOTICE', 'General', 'Orden'));

        $this->actingAs($this->assignedUser);
        Livewire::test(NotificationCenter::class)
            ->assertSee('MINE')
            ->assertDontSee('NOTMINE')
            ->assertSee('GENERALNOTICE');
    }

    public function test_notification_center_lead_designer_and_managers_see_every_order(): void
    {
        $otherOrder = Order::create(['company_name' => 'ELSEWHERE', 'task_name' => 'x', 'core_status' => CoreStatus::ENTRANTE, 'in_workspace' => true]);
        DB::table('notifications')->delete();

        NotificationDispatcher::dispatch(eventType: 'status_changed', label: 'NOTMINE', order: $otherOrder, actor: $this->salesUser);

        foreach ([$this->leadUser, $this->admin, $this->coordinator] as $viewer) {
            $this->actingAs($viewer);
            Livewire::test(NotificationCenter::class)->assertSee('NOTMINE');
        }
    }

    public function test_notification_center_hides_notifications_authored_by_the_viewer(): void
    {
        $this->admin->notify(new SystemNotification(
            eventType: 'status_changed', label: 'OWNACTION', companyName: 'X', taskName: 'Y', actorId: $this->admin->id
        ));

        $this->actingAs($this->admin);
        Livewire::test(NotificationCenter::class)->assertDontSee('OWNACTION');
    }
}
