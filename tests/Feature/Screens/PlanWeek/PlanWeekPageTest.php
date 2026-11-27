<?php

namespace Tests\Feature\Screens\PlanWeek;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\TaskTemplate;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\PlanWeek\PlanWeekPage;
use App\Models\Client;
use App\Models\User;
use App\Services\NavigationService;
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

    private function ready(array $attributes = [], ?string $promised = null, DeliverableStatus $status = DeliverableStatus::READY): Deliverable
    {
        $workOrder = WorkOrder::factory()->create([
            'client_id' => Client::create(['name' => fake()->unique()->company()])->id,
            'promised_date' => $promised,
        ]);

        return Deliverable::factory()->for($workOrder, 'workOrder')->inStatus($status)->create($attributes);
    }

    private function task(Deliverable $deliverable, ?string $day, array $attributes = []): Task
    {
        return Task::factory()->create(['order_id' => $deliverable->id, 'scheduled_date' => $day, ...$attributes]);
    }

    public function test_managers_see_backlog_and_everyone_and_designers_start_on_their_own_row(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana Uno']);
        $bea = User::factory()->designer()->create(['name' => 'Bea Dos']);
        $this->ready(['task_name' => 'Sign unplanned']);
        $this->task($this->ready(['task_name' => 'Ana piece', 'assignee_id' => $ana->id], status: DeliverableStatus::IN_DESIGN), '2026-11-26', ['title' => 'Ana first proposal']);
        $this->ready(['task_name' => 'Bea piece', 'assignee_id' => $bea->id, 'planned_for' => '2026-11-26']);

        foreach ([User::factory()->admin()->create(), User::factory()->coordinator()->create(), User::factory()->designer()->create(['is_lead' => true])] as $manager) {
            Livewire::actingAs($manager)->test(PlanWeekPage::class)
                ->assertSee('Sign unplanned')->assertSee('Ana first proposal')->assertSee('Bea piece')->assertSee(__('Add the first task'));
        }

        Livewire::actingAs($ana)->test(PlanWeekPage::class)
            ->assertSet('designerFilter', (string) $ana->id)
            ->assertSee('Ana first proposal')->assertDontSee('Bea piece')->assertDontSee('Sign unplanned')
            ->set('designerFilter', (string) $bea->id)->assertSee('Bea piece')->assertDontSee(__('Add the first task'));
    }

    public function test_designers_see_plan_week_in_the_menu_and_sales_and_customer_service_are_forbidden(): void
    {
        $labels = fn (User $user): array => collect(app(NavigationService::class)->primaryGroups($user))->flatten(1)->pluck('label')->all();

        $this->assertContains(__('Plan week'), $labels(User::factory()->designer()->create()));
        $this->assertNotContains(__('Plan week'), $labels(User::factory()->sales()->create()));
        $this->actingAs(User::factory()->sales()->create())->get(route('plan-week'))->assertForbidden();
        $this->actingAs(User::factory()->customerService()->create())->get(route('plan-week'))->assertForbidden();
    }

    public function test_backlog_is_sorted_by_promised_date_and_blocked_items_are_greyed_and_not_draggable(): void
    {
        $this->ready(['task_name' => 'Later piece'], '2026-12-20');
        $this->ready(['task_name' => 'Sooner piece'], '2026-12-01');
        $blocked = $this->ready(['task_name' => 'Blocked piece']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        $component = Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->assertSeeInOrder(['Sooner piece', 'Later piece', "Blocked · can't be planned"]);

        $this->assertStringNotContainsString('startDeliverable($event, '.$blocked->id.')', $component->html());
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

    public function test_week_navigation_and_go_to_date(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->assertSet('weekStart', '2026-11-23')
            ->call('nextWeek')->assertSet('weekStart', '2026-11-30')
            ->call('previousWeek')->call('previousWeek')->assertSet('weekStart', '2026-11-16')
            ->call('thisWeek')->assertSet('weekStart', '2026-11-23')
            ->call('goToDate', '2027-01-07')->assertSet('weekStart', '2027-01-04');
    }

    public function test_dropping_tasks_moves_and_reorders_them_and_undo_puts_them_back(): void
    {
        $ana = User::factory()->designer()->create();
        $bea = User::factory()->designer()->create();
        $piece = $this->ready(['assignee_id' => $ana->id], status: DeliverableStatus::IN_DESIGN);
        $first = $this->task($piece, '2026-11-24', ['sort_order' => 0]);
        $second = $this->task($piece, '2026-11-24', ['sort_order' => 1]);
        $waiting = $this->task($this->ready(['assignee_id' => $bea->id], status: DeliverableStatus::IN_DESIGN), '2026-11-26');

        $page = Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->call('placeTasks', [$waiting->id, $second->id, $first->id], $ana->id, '2026-11-24')
            ->assertDispatched('plan-week-undoable');

        $this->assertSame(['2026-11-24', $ana->id, 0], [$waiting->fresh()->scheduled_date->toDateString(), $waiting->fresh()->assignee_user_id, $waiting->fresh()->sort_order]);
        $this->assertSame([1, 2], [$second->fresh()->sort_order, $first->fresh()->sort_order]);
        $this->assertSame(1, DB::table('order_events')->where('event_type', 'task_scheduled')->count());

        $page->call('placeTasks', [$first->id], $ana->id, 'next');
        $this->assertSame('2026-11-30', $first->fresh()->scheduled_date->toDateString());

        $page->call('undo')->call('undo');
        $this->assertSame(['2026-11-26', null], [$waiting->fresh()->scheduled_date->toDateString(), $waiting->fresh()->assignee_user_id]);
        $this->assertSame(['2026-11-24', 0], [$first->fresh()->scheduled_date->toDateString(), $first->fresh()->sort_order]);
        $page->call('undo')->assertDispatched('toast', message: __('Nothing to undo.'));
    }

    public function test_a_designer_plans_their_own_row_but_cannot_hand_work_to_someone_else(): void
    {
        $ana = User::factory()->designer()->create();
        $bea = User::factory()->designer()->create();
        $mine = $this->task($this->ready(['assignee_id' => $ana->id], status: DeliverableStatus::IN_DESIGN), '2026-11-24');
        $theirs = $this->task($this->ready(['assignee_id' => $bea->id], status: DeliverableStatus::IN_DESIGN), '2026-11-24');

        $page = Livewire::actingAs($ana)->test(PlanWeekPage::class)
            ->call('placeTasks', [$mine->id], $ana->id, '2026-11-27')
            ->call('toggleTask', $mine->id);
        $this->assertSame('2026-11-27', $mine->fresh()->scheduled_date->toDateString());
        $this->assertTrue($mine->fresh()->isDone());

        $page->call('placeTasks', [$mine->id], $bea->id, '2026-11-27')->assertDispatched('toast', type: 'error')
            ->call('placeTasks', [$theirs->id], $ana->id, '2026-11-27')
            ->call('toggleTask', $theirs->id)
            ->call('bulkAssign', [$mine->id], $bea->id);
        $this->assertSame($ana->id, $mine->fresh()->assignee_user_id);
        $this->assertSame('2026-11-24', $theirs->fresh()->scheduled_date->toDateString());
        $this->assertFalse($theirs->fresh()->isDone());
    }

    public function test_ticking_a_task_finishes_it_keeps_it_on_the_day_and_undo_reopens_it(): void
    {
        $ana = User::factory()->designer()->create();
        $task = $this->task($this->ready(['assignee_id' => $ana->id], status: DeliverableStatus::IN_DESIGN), '2026-11-25', ['title' => 'Send the proof']);

        $page = Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)->call('toggleTask', $task->id);

        $this->assertTrue($task->fresh()->isDone());
        $page->assertSee('Send the proof')->assertSeeHtml('task-'.$task->id.'-d');
        $page->call('undo');
        $this->assertFalse($task->fresh()->isDone());
    }

    public function test_bulk_moves_keep_each_task_with_its_person_and_bulk_done_finishes_them(): void
    {
        $ana = User::factory()->designer()->create();
        $bea = User::factory()->designer()->create();
        $anaTask = $this->task($this->ready(['assignee_id' => $ana->id], status: DeliverableStatus::IN_DESIGN), '2026-11-23');
        $beaTask = $this->task($this->ready(['assignee_id' => $bea->id], status: DeliverableStatus::IN_DESIGN), '2026-11-23');
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class);

        $page->call('bulkMove', [$anaTask->id, $beaTask->id], 'tomorrow');
        $this->assertSame(['2026-11-26', $ana->id], [$anaTask->fresh()->scheduled_date->toDateString(), $anaTask->fresh()->assignee_user_id]);
        $this->assertSame(['2026-11-26', $bea->id], [$beaTask->fresh()->scheduled_date->toDateString(), $beaTask->fresh()->assignee_user_id]);

        $page->call('bulkMove', [$anaTask->id], 'next_week')->call('bulkMove', [$beaTask->id], 'today');
        $this->assertSame('2026-11-30', $anaTask->fresh()->scheduled_date->toDateString());
        $this->assertSame('2026-11-25', $beaTask->fresh()->scheduled_date->toDateString());

        $page->call('bulkAssign', [$beaTask->id], $ana->id);
        $this->assertSame(['2026-11-25', $ana->id], [$beaTask->fresh()->scheduled_date->toDateString(), $beaTask->fresh()->assignee_user_id]);

        $page->call('bulkComplete', [$anaTask->id, $beaTask->id]);
        $this->assertTrue($anaTask->fresh()->isDone() && $beaTask->fresh()->isDone());
    }

    public function test_dropping_backlog_work_plans_it_its_tasks_follow_and_a_full_day_only_warns(): void
    {
        $ana = User::factory()->designer()->create(['name' => 'Ana', 'wip_limit' => 1]);
        $this->ready(['assignee_id' => $ana->id, 'planned_for' => '2026-11-24']);
        $withTasks = $this->ready();
        $loose = $this->task($withTasks, null);
        $empty = $this->ready(['task_name' => 'Fresh piece']);
        $page = Livewire::actingAs(User::factory()->coordinator()->create())->test(PlanWeekPage::class);

        $page->call('planDeliverable', $withTasks->id, $ana->id, '2026-11-24')
            ->assertReturned(fn (array $result): bool => $result['ok'] && ! $result['needsTask']);
        $this->assertSame(['2026-11-24', $ana->id], [$withTasks->fresh()->planned_for->toDateString(), $withTasks->fresh()->assignee_id]);
        $this->assertSame(['2026-11-24', $ana->id], [$loose->fresh()->scheduled_date->toDateString(), $loose->fresh()->assignee_user_id]);

        $page->call('planDeliverable', $empty->id, $ana->id, '2026-11-24')
            ->assertReturned(fn (array $result): bool => $result['ok'] && $result['needsTask'] && str_contains($result['label'], 'Fresh piece'))
            ->assertSee('3/1');

        $page->call('undo');
        $this->assertNull($empty->fresh()->planned_for);
    }

    public function test_blocked_work_cannot_be_planned_and_designers_cannot_plan_deliverables(): void
    {
        $ana = User::factory()->designer()->create();
        $blocked = $this->ready();
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->call('planDeliverable', $blocked->id, $ana->id, '2026-11-24')
            ->assertDispatched('toast', message: __('A blocked deliverable cannot be planned.'), type: 'error');
        $this->assertNull($blocked->fresh()->planned_for);

        $mine = $this->ready(['assignee_id' => $ana->id, 'planned_for' => '2026-11-24']);
        Livewire::actingAs($ana)->test(PlanWeekPage::class)->call('planDeliverable', $mine->id, $ana->id, '2026-11-25')->assertForbidden();
        Livewire::actingAs($ana)->test(PlanWeekPage::class)->call('unplanDeliverable', $mine->id)->assertDispatched('toast', type: 'error');
        $this->assertSame('2026-11-24', $mine->fresh()->planned_for->toDateString());
    }

    public function test_quick_add_creates_the_task_on_that_day_takes_a_templates_kind_and_plans_ready_work(): void
    {
        $ana = User::factory()->designer()->create();
        $bea = User::factory()->designer()->create();
        TaskTemplate::query()->create(['name_en' => 'Send proof to client', 'name_es' => 'Enviar Proof cliente', 'kind' => 'gate', 'advances_to' => 'client_review', 'required' => true, 'active' => true, 'sort' => 1]);
        $piece = $this->ready(['task_name' => 'Window decal']);
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class);

        $page->call('quickAdd', $piece->id, 'send proof to client', $ana->id, '2026-11-26')->assertReturned(['ok' => true]);
        $task = $piece->tasks()->sole();
        $this->assertSame(['Send proof to client', TaskKind::GATE_SEND_PROOF, '2026-11-26', $ana->id], [$task->title, $task->kind, $task->scheduled_date->toDateString(), $task->assignee_user_id]);
        $this->assertSame(['2026-11-26', $ana->id], [$piece->fresh()->planned_for->toDateString(), $piece->fresh()->assignee_id]);

        $page->call('quickAdd', $piece->id, 'Check sizes', $ana->id, 'next');
        $this->assertSame('2026-11-30', $piece->tasks()->where('title', 'Check sizes')->sole()->scheduled_date->toDateString());

        Livewire::actingAs($ana)->test(PlanWeekPage::class)
            ->call('quickAdd', $piece->id, 'Sneaky', $bea->id, '2026-11-26')->assertReturned(['ok' => false]);
        $this->assertFalse($piece->tasks()->where('title', 'Sneaky')->exists());
    }

    public function test_deliverable_search_unschedule_and_promise_alerts(): void
    {
        $ana = User::factory()->designer()->create();
        $piece = $this->ready(['task_name' => 'Lobby letters', 'assignee_id' => $ana->id], '2026-11-24', DeliverableStatus::IN_DESIGN);
        $task = $this->task($piece, '2026-11-25');
        $page = Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class);

        $this->assertSame([$piece->id], array_column($page->instance()->searchDeliverables('lobby'), 'id'));
        $this->assertSame([], $page->instance()->searchDeliverables('l'));
        $page->assertSee(trans_choice('{1} 1 promise at risk|[2,*] :count promises at risk', 1, ['count' => 1]));

        $page->call('unscheduleTask', $task->id);
        $this->assertNull($task->fresh()->scheduled_date);
        $page->assertSee(trans_choice('{1} 1 task to place|[2,*] :count tasks to place', 1, ['count' => 1]));
    }

    public function test_cards_open_their_deliverable(): void
    {
        $ana = User::factory()->designer()->create();
        $piece = $this->ready(['assignee_id' => $ana->id], status: DeliverableStatus::IN_DESIGN);
        $task = $this->task($piece, '2026-11-25');
        $backlog = $this->ready();

        Livewire::actingAs(User::factory()->admin()->create())->test(PlanWeekPage::class)
            ->assertSeeHtml("cardClick(\$event, {$task->id}, {$piece->id})")
            ->assertSeeHtml("open-deliverable', { deliverableId: {$backlog->id} }");
    }

    public function test_both_locales_render_and_layout_and_sort_are_remembered(): void
    {
        $ana = User::factory()->designer()->create();
        $this->task($this->ready(['assignee_id' => $ana->id], status: DeliverableStatus::IN_DESIGN), '2026-11-24');
        $admin = User::factory()->admin()->create();

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            $html = Livewire::actingAs($admin)->test(PlanWeekPage::class)->set('layout', 'people')->set('sort', 'priority')->html();
            if ($locale === 'es') {
                $this->assertStringContainsString('lun. 23', $html);
            }
        }

        Livewire::actingAs($admin)->test(PlanWeekPage::class)->assertSet('layout', 'people')
            ->set('sort', 'nonsense')->assertSet('sort', 'manual');
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
        $seed = function () use ($ana): void {
            $this->task($this->ready(['assignee_id' => $ana->id], status: DeliverableStatus::IN_DESIGN), '2026-11-24');
            $this->ready(['assignee_id' => $ana->id, 'planned_for' => '2026-11-25']);
            $this->ready();
            Block::factory()->create(['deliverable_id' => $this->ready()->id]);
        };

        $seed();
        $count();
        $small = $count();
        foreach (range(1, 8) as $unused) {
            $seed();
        }
        $this->assertSame($small, $count());
    }
}
