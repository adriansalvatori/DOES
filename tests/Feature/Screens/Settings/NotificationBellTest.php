<?php

namespace Tests\Feature\Screens\Settings;

use App\Application\Delivery\Actions\BlockDeliverable;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\WorkflowLabel;
use App\Livewire\Notifications\NotificationCenter;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Domain\DeliveryTestCase;

class NotificationBellTest extends DeliveryTestCase
{
    public function test_a_block_reaches_customer_service_and_managers_and_renders_in_each_readers_locale(): void
    {
        $manager = $this->manager();
        $service = $this->customerService();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);

        App::setLocale('en');
        app(BlockDeliverable::class)($deliverable, BlockReason::MISSING_LOGO, null, $this->designer());

        foreach ([$manager, $service] as $reader) {
            $this->assertSame(1, $reader->notifications()->count());
        }

        $english = BlockReason::MISSING_LOGO->label();
        $this->actingAs($manager);
        Livewire::test(NotificationCenter::class)->assertSee($english);

        App::setLocale('es');
        $spanish = __('enums.block_reason.missing_logo');
        $this->assertNotSame($english, $spanish);

        $this->actingAs($service);
        Livewire::test(NotificationCenter::class)->assertSee($spanish)->assertDontSee($english);
    }

    public function test_an_edited_label_shows_in_the_bell(): void
    {
        $manager = $this->manager();
        $this->customerService();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);

        App::setLocale('en');
        app(BlockDeliverable::class)($deliverable, BlockReason::MISSING_LOGO, null, $this->designer());
        WorkflowLabel::where(['kind' => 'block_reason', 'code' => 'missing_logo'])->update(['label_es' => 'Falta el archivo del logo']);

        App::setLocale('es');
        $this->actingAs($manager);
        Livewire::test(NotificationCenter::class)->assertSee('Falta el archivo del logo');
    }

    public function test_the_list_is_capped_at_fifty_and_the_unread_count_stays_accurate(): void
    {
        $user = $this->manager();
        foreach (range(1, 60) as $index) {
            $user->notify(new SystemNotification('order_urgent', 'Urgent', 'Client', 'Task '.$index, 1000 + $index));
        }

        $this->actingAs($user);
        $component = Livewire::test(NotificationCenter::class);

        $component->assertViewHas('unreadCount', 60)
            ->assertViewHas('notifications', fn ($rows) => $rows->count() === NotificationCenter::LIST_LIMIT);
        $this->assertSame(50, NotificationCenter::LIST_LIMIT);
    }

    public function test_unread_count_ignores_own_actions_and_cards_of_other_designers(): void
    {
        $designer = $this->designer('Eura');
        $mine = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id]);
        $theirs = $this->deliverable(DeliverableStatus::IN_DESIGN);

        $designer->notify(new SystemNotification('order_urgent', 'Urgent', 'Client', 'Mine', $mine->id));
        $designer->notify(new SystemNotification('order_urgent', 'Urgent', 'Client', 'Theirs', $theirs->id));
        $designer->notify(new SystemNotification('status_changed', 'Status', 'Client', 'Own action', $mine->id, actorId: $designer->id));

        $this->actingAs($designer);
        Livewire::test(NotificationCenter::class)
            ->assertViewHas('unreadCount', 1)
            ->assertViewHas('notifications', fn ($rows) => $rows->count() === 1 && $rows->first()->data['task_name'] === 'Mine');
    }

    public function test_mark_all_as_read_and_opening_a_notification(): void
    {
        $user = $this->manager();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN);
        $user->notify(new SystemNotification('order_urgent', 'Urgent', 'Client', 'Task', $deliverable->id));
        $user->notify(new SystemNotification('order_urgent', 'Urgent', 'Client', 'Task two', $deliverable->id));
        $this->actingAs($user);

        $component = Livewire::test(NotificationCenter::class);
        $component->call('markAsRead', $user->notifications()->first()->id)
            ->assertDispatched('open-deliverable', deliverableId: $deliverable->id)
            ->assertViewHas('unreadCount', 1)
            ->call('markAllAsRead')
            ->assertViewHas('unreadCount', 0);
    }

    public function test_the_bell_costs_a_fixed_number_of_queries(): void
    {
        $user = $this->manager();
        foreach (range(1, 40) as $index) {
            $user->notify(new SystemNotification('order_urgent', 'Urgent', 'Client', 'Task '.$index, $index));
        }
        $this->actingAs($user);

        DB::enableQueryLog();
        Livewire::test(NotificationCenter::class);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(8, $queries);
    }
}
