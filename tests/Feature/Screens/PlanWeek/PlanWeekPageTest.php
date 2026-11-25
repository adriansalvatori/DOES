<?php

namespace Tests\Feature\Screens\PlanWeek;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\PlanWeek\PlanWeekPage;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PlanWeekPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-25 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ready(array $attributes = [], ?string $promised = null): Deliverable
    {
        $workOrder = WorkOrder::factory()->create([
            'client_id' => Client::create(['name' => fake()->unique()->company()])->id,
            'promised_date' => $promised,
        ]);

        return Deliverable::factory()->for($workOrder, 'workOrder')->inStatus(DeliverableStatus::READY)->create($attributes);
    }

    public function test_manager_roles_see_backlog_and_grid_and_designers_see_only_their_row(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana']);
        $bea = User::factory()->designer()->create(['name' => 'Bea']);
        $this->ready(['task_name' => 'Sign unplanned']);
        $this->ready(['task_name' => 'Ana piece', 'assignee_id' => $ana->id, 'planned_for' => '2026-11-26']);
        $this->ready(['task_name' => 'Bea piece', 'assignee_id' => $bea->id, 'planned_for' => '2026-11-26']);

        foreach ([User::factory()->admin()->create(), User::factory()->coordinator()->create(), User::factory()->designer()->create(['is_lead' => true])] as $manager) {
            Livewire::actingAs($manager)->test(PlanWeekPage::class)
                ->assertSee('Sign unplanned')->assertSee('Ana piece')->assertSee('Bea piece');
        }

        Livewire::actingAs($ana)->test(PlanWeekPage::class)
            ->assertSee('Ana piece')->assertDontSee('Bea piece')->assertDontSee('Sign unplanned');
    }

    public function test_sales_and_customer_service_are_forbidden(): void
    {
        $this->actingAs(User::factory()->sales()->create())->get(route('v2.plan-week'))->assertForbidden();
        $this->actingAs(User::factory()->customerService()->create())->get(route('v2.plan-week'))->assertForbidden();
    }

    public function test_backlog_is_sorted_by_promised_date_and_blocked_items_are_greyed_and_not_draggable(): void
    {
        $this->ready(['task_name' => 'Later piece'], '2026-12-20');
        $this->ready(['task_name' => 'Sooner piece'], '2026-12-01');
        $blocked = $this->ready(['task_name' => 'Blocked piece']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        $component = Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->assertSeeInOrder(['Sooner piece', 'Later piece', "Blocked · can't be planned"]);

        $this->assertSame(1, substr_count($component->html(), 'opacity-50'));
        $this->assertStringNotContainsString('dragstart="start($event, '.$blocked->id.')"', $component->html());
    }

    public function test_search_and_designer_filter_narrow_the_backlog(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana']);
        $this->ready(['task_name' => 'Windows x3']);
        $this->ready(['task_name' => 'Banner', 'assignee_id' => $ana->id]);

        Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->set('search', 'windows')->assertSee('Windows x3')->assertDontSee('Banner')
            ->set('search', '')->set('designerFilter', (string) $ana->id)->assertSee('Banner')->assertDontSee('Windows x3');
    }

    public function test_week_navigation_changes_the_days_and_returns_to_this_week(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->assertSet('weekStart', '2026-11-23')
            ->call('nextWeek')->assertSet('weekStart', '2026-11-30')
            ->call('previousWeek')->call('previousWeek')->assertSet('weekStart', '2026-11-16')
            ->call('thisWeek')->assertSet('weekStart', '2026-11-23');
    }

    public function test_dropping_a_card_plans_it_and_a_second_drop_re_plans_it(): void
    {
        $ana = User::factory()->designer()->create();
        $card = $this->ready();

        $component = Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->call('planDeliverable', $card->id, $ana->id, '2026-11-24');
        $this->assertSame('2026-11-24', $card->fresh()->planned_for->toDateString());
        $this->assertSame($ana->id, $card->fresh()->assignee_id);

        $component->call('planDeliverable', $card->id, $ana->id, '2026-11-26');
        $this->assertSame('2026-11-26', $card->fresh()->planned_for->toDateString());
    }

    public function test_unplan_returns_the_card_to_the_backlog(): void
    {
        $ana = User::factory()->designer()->create();
        $card = $this->ready(['assignee_id' => $ana->id, 'planned_for' => '2026-11-24']);

        Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->call('unplanDeliverable', $card->id);

        $this->assertNull($card->fresh()->planned_for);
    }

    public function test_full_day_asks_for_override_with_the_domain_message_and_manager_can_confirm(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana', 'wip_limit' => 1]);
        $this->ready(['assignee_id' => $ana->id, 'planned_for' => '2026-11-24']);
        $extra = $this->ready();

        $component = Livewire::actingAs(User::factory()->coordinator()->create())->test(PlanWeekPage::class)
            ->call('planDeliverable', $extra->id, $ana->id, '2026-11-24')
            ->assertSee('Ana already has 1 deliverables planned for that day.')
            ->assertSee('Plan anyway');
        $this->assertNull($extra->fresh()->planned_for);

        $component->call('confirmOverride')->assertSet('pendingOverride', null);
        $this->assertSame('2026-11-24', $extra->fresh()->planned_for->toDateString());
    }

    public function test_blocked_card_cannot_be_planned_and_shows_the_domain_message(): void
    {
        $ana = User::factory()->designer()->create();
        $card = $this->ready();
        Block::factory()->create(['deliverable_id' => $card->id]);

        Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->call('planDeliverable', $card->id, $ana->id, '2026-11-24')
            ->assertSet('pendingOverride', null)
            ->assertSee('A blocked deliverable cannot be planned.');
        $this->assertNull($card->fresh()->planned_for);
    }

    public function test_designer_cannot_plan_or_unplan(): void
    {
        $ana = User::factory()->designer()->create();
        $card = $this->ready(['assignee_id' => $ana->id, 'planned_for' => '2026-11-24']);

        Livewire::actingAs($ana)->test(PlanWeekPage::class)
            ->call('planDeliverable', $card->id, $ana->id, '2026-11-25')->assertForbidden();
        Livewire::actingAs($ana)->test(PlanWeekPage::class)
            ->call('unplanDeliverable', $card->id)->assertForbidden();
        $this->assertSame('2026-11-24', $card->fresh()->planned_for->toDateString());
    }

    public function test_blocked_after_planning_is_greyed_not_counted_and_hidden_from_the_designer(): void
    {
        $ana = User::factory()->designer()->create(['wip_limit' => 3]);
        $this->ready(['task_name' => 'Counted', 'assignee_id' => $ana->id, 'planned_for' => '2026-11-24']);
        $blocked = $this->ready(['task_name' => 'Hidden one', 'assignee_id' => $ana->id, 'planned_for' => '2026-11-24', 'carry_over_count' => 1]);
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->assertSee('Blocked · not counted')->assertSee('Carry-over')->assertSee('1/3');
        Livewire::actingAs($ana)->test(PlanWeekPage::class)
            ->assertSee('Counted')->assertDontSee('Blocked · not counted');
    }

    public function test_cards_dispatch_open_deliverable(): void
    {
        $card = $this->ready();

        Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->assertSeeHtml("open-deliverable', { deliverableId: {$card->id} }");
    }

    public function test_both_locales_render_without_missing_keys(): void
    {
        $ana = User::factory()->designer()->create();
        $this->ready(['assignee_id' => $ana->id, 'planned_for' => '2026-11-24']);
        $admin = User::factory()->admin()->create();

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            $html = Livewire::actingAs($admin)->test(PlanWeekPage::class)->html();
            if ($locale === 'es') {
                $this->assertStringContainsString('lun. 23', $html);
            }
        }
    }

    public function test_query_count_does_not_grow_with_more_cards(): void
    {
        $ana = User::factory()->designer()->create(['wip_limit' => 50]);
        $admin = User::factory()->admin()->create();
        $count = function () use ($admin): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($admin)->test(PlanWeekPage::class);
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->ready(['assignee_id' => $ana->id, 'planned_for' => '2026-11-24']);
        $this->ready();
        Block::factory()->create(['deliverable_id' => $this->ready()->id]);
        $count();
        $small = $count();
        foreach (range(1, 8) as $i) {
            $this->ready(['assignee_id' => $ana->id, 'planned_for' => '2026-11-25']);
            $this->ready();
            Block::factory()->create(['deliverable_id' => $this->ready()->id]);
        }
        $this->assertSame($small, $count());
    }
}
