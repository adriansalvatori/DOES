<?php

namespace Tests\Feature\Screens\Ops;

use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\Resolver\ResolverPage;
use App\Models\Client;
use App\Models\Contact;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\Domain\DeliveryTestCase;

class ResolverPageTest extends DeliveryTestCase
{
    private function blockedDeliverable(string $number = 'WO 100', ?User $owner = null, int $ageDays = 2): Block
    {
        $client = Client::firstOrCreate(['name' => 'NORTH GEORGIA CLEANING']);
        $contact = Contact::firstOrCreate(['name' => 'KEVIN'], ['phone' => '7705550142']);
        $workOrder = WorkOrder::factory()->create(['number' => $number, 'client_id' => $client->id, 'contact_id' => $contact->id]);
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['task_name' => 'Menu board'], $workOrder);

        return Block::factory()->create([
            'deliverable_id' => $deliverable->id,
            'reason' => BlockReason::MISSING_MEASUREMENTS,
            'owner_id' => $owner?->id,
            'raised_at' => now()->subDays($ageDays),
        ]);
    }

    public function test_designers_and_sales_get_403_staff_who_resolve_can_open_it(): void
    {
        $this->actingAs(User::factory()->designer()->create())->get(route('resolver'))->assertForbidden();
        $this->actingAs(User::factory()->sales()->create())->get(route('resolver'))->assertForbidden();

        foreach ([$this->manager(), $this->customerService(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)->get(route('resolver'))->assertOk();
        }
    }

    public function test_it_lists_blocks_oldest_first_with_title_case_company_reason_owner_and_phone(): void
    {
        $cs = $this->customerService();
        $this->blockedDeliverable('WO 1', $cs, 1);
        $this->blockedDeliverable('WO 2', $cs, 9);

        Livewire::actingAs($cs)->test(ResolverPage::class)
            ->assertSeeInOrder(['WO 2', 'WO 1'])
            ->assertSee('North Georgia Cleaning')
            ->assertSee(__('enums.block_reason.missing_measurements'))
            ->assertSee('(770) 555-0142')
            ->assertSee('9 d')
            ->assertSet('tab', 'blocked');
    }

    public function test_the_phone_falls_back_to_the_company_phones(): void
    {
        $cs = $this->customerService();
        $block = $this->blockedDeliverable('WO 1', $cs);
        $block->deliverable->workOrder->update(['contact_id' => null]);
        $block->deliverable->workOrder->client->update(['phones' => [['label' => '', 'phone' => '(470) 210-2280']]]);

        Livewire::actingAs($cs)->test(ResolverPage::class)->assertSee('(470) 210-2280');
    }

    public function test_tabs_show_counts_and_hold_tab_lists_holds(): void
    {
        $manager = $this->manager();
        $this->blockedDeliverable('WO 1');
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Parked piece']);
        Hold::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => HoldReason::PAUSED, 'started_at' => now()->subDays(4)]);

        $component = Livewire::actingAs($manager)->test(ResolverPage::class);
        $this->assertSame(['blocked' => 1, 'hold' => 1], $component->instance()->counts());
        $component->assertDontSee('Parked piece')
            ->call('setTab', 'hold')
            ->assertSee('Parked piece')
            ->assertSee(__('Resume'));
    }

    public function test_resolve_goes_through_the_action_with_the_note(): void
    {
        $cs = $this->customerService();
        $block = $this->blockedDeliverable('WO 1', $cs);

        Livewire::actingAs($cs)->test(ResolverPage::class)->call('resolveBlock', $block->id, 'Client sent measurements');

        $block->refresh();
        $this->assertNotNull($block->resolved_at);
        $this->assertSame('Client sent measurements', $block->resolution_note);
        $this->assertSame($cs->id, $block->resolved_by);
    }

    public function test_follow_up_keeps_the_block_open_and_logs_the_note(): void
    {
        $cs = $this->customerService();
        $block = $this->blockedDeliverable('WO 1', $cs);

        Livewire::actingAs($cs)->test(ResolverPage::class)->call('logFollowUp', $block->id, 'Called Kevin, no answer');

        $this->assertNull($block->fresh()->resolved_at);
        $this->assertSame(1, $this->eventCount($block->deliverable, 'block_follow_up'));
    }

    public function test_a_follow_up_without_a_note_is_refused(): void
    {
        $cs = $this->customerService();
        $block = $this->blockedDeliverable('WO 1', $cs);

        Livewire::actingAs($cs)->test(ResolverPage::class)->call('logFollowUp', $block->id, '  ')->assertDispatched('toast');

        $this->assertSame(0, $this->eventCount($block->deliverable, 'block_follow_up'));
    }

    public function test_reassigning_changes_the_owner(): void
    {
        $cs = $this->customerService();
        $manager = $this->manager();
        $block = $this->blockedDeliverable('WO 1', $cs);

        Livewire::actingAs($manager)->test(ResolverPage::class)->call('reassignOwner', $block->id, $manager->id);

        $this->assertSame($manager->id, $block->fresh()->owner_id);
        $this->assertSame(1, $this->eventCount($block->deliverable, 'block_reassigned'));
    }

    public function test_resume_ends_the_hold(): void
    {
        $cs = $this->customerService();
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $hold = Hold::factory()->create(['deliverable_id' => $deliverable->id]);

        Livewire::actingAs($cs)->test(ResolverPage::class)->call('setTab', 'hold')->call('resume', $deliverable->id);

        $this->assertNotNull($hold->fresh()->ended_at);
    }

    public function test_customer_service_sees_only_their_blocks_and_unowned_ones(): void
    {
        $cs = $this->customerService();
        $other = $this->customerService();
        $this->blockedDeliverable('WO 11', $cs);
        $this->blockedDeliverable('WO 12', $other);
        $this->blockedDeliverable('WO 13', null);

        Livewire::actingAs($cs)->test(ResolverPage::class)->assertSee('WO 11')->assertSee('WO 13')->assertDontSee('WO 12');
        Livewire::actingAs($this->manager())->test(ResolverPage::class)->assertSee('WO 12');
    }

    public function test_renders_in_both_locales_and_the_query_count_does_not_grow(): void
    {
        $cs = $this->customerService();
        $this->blockedDeliverable('WO 1', $cs);

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            Livewire::actingAs($cs)->test(ResolverPage::class)->assertSee(__('Blocked'))->assertSee(__('Log follow-up'))->assertDontSee('enums.');
        }

        $count = function () use ($cs): int {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            Livewire::actingAs($cs)->test(ResolverPage::class);
            $total = count(\DB::getQueryLog());
            \DB::disableQueryLog();

            return $total;
        };
        $count();
        $small = $count();
        foreach (range(2, 8) as $i) {
            $this->blockedDeliverable('WO '.($i * 10), $cs, $i);
        }

        $this->assertSame($small, $count());
    }
}
