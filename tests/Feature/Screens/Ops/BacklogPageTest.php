<?php

namespace Tests\Feature\Screens\Ops;

use App\Application\Delivery\Actions\MoveWorkOrderOutOfBacklog;
use App\Application\Delivery\Queries\BacklogQuery;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\Backlog\BacklogPage;
use App\Models\Client;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\Domain\DeliveryTestCase;

class BacklogPageTest extends DeliveryTestCase
{
    private function backlogWorkOrder(string $number, string $company = 'ACME SIGNS', string $piece = 'Banner'): WorkOrder
    {
        $client = Client::firstOrCreate(['name' => $company]);
        $workOrder = WorkOrder::factory()->create(['number' => $number, 'client_id' => $client->id]);
        $this->deliverable(DeliverableStatus::INTAKE, ['task_name' => $piece, 'company_name' => $company, 'in_backlog' => true], $workOrder);

        return $workOrder;
    }

    public function test_everyone_can_open_it_and_only_backlog_cards_show(): void
    {
        $this->backlogWorkOrder('WO 1');
        WorkOrder::factory()->create(['number' => 'WO 2']);

        foreach ([User::factory()->designer()->create(), $this->manager(), $this->customerService(), User::factory()->sales()->create()] as $user) {
            Livewire::actingAs($user)->test(BacklogPage::class)->assertOk()->assertSee('WO 1')->assertDontSee('WO 2')->assertSee('Acme Signs');
        }
    }

    public function test_there_are_no_trello_sync_buttons(): void
    {
        $html = Livewire::actingAs($this->manager())->test(BacklogPage::class)->html();

        $this->assertStringNotContainsStringIgnoringCase('trello', $html);
        $this->assertStringNotContainsString('runTrelloSync', $html);
    }

    public function test_filters_and_sort(): void
    {
        $this->backlogWorkOrder('WO 1', 'ACME SIGNS', 'Banner');
        $this->backlogWorkOrder('WO 2', 'ZED PRINT', 'Poster');

        $page = Livewire::actingAs($this->manager())->test(BacklogPage::class);
        $numbers = fn () => collect($page->instance()->result()->workOrders->items())->map(fn ($workOrder) => $workOrder->number)->all();

        $page->set('search', 'Poster');
        $this->assertSame(['WO 2'], $numbers());
        $page->set('search', '')->set('company', 'ZED PRINT');
        $this->assertSame(['WO 2'], $numbers());
        $page->set('company', '')->set('sortBy', 'company_asc');
        $this->assertSame(['WO 1', 'WO 2'], $numbers());
        $page->set('status', 'ready');
        $this->assertSame([], $numbers());
    }

    public function test_move_to_intake_takes_one_card_out_of_the_backlog_through_the_action(): void
    {
        $workOrder = $this->backlogWorkOrder('WO 1');
        $piece = $workOrder->deliverables()->first();
        $manager = $this->manager();

        Livewire::actingAs($manager)->test(BacklogPage::class)->call('moveToIntake', $workOrder->id)->assertDispatched('toast');

        $this->assertFalse($piece->fresh()->in_backlog);
        $this->assertSame(DeliverableStatus::INTAKE, $piece->fresh()->status);
        $this->assertSame(1, $this->eventCount($piece, 'moved_out_of_backlog'));
        $this->assertSame(1, (int) $this->legacyRow($piece)->in_workspace);
    }

    public function test_bulk_move_moves_every_selected_card(): void
    {
        $first = $this->backlogWorkOrder('WO 1');
        $second = $this->backlogWorkOrder('WO 2', 'ZED PRINT');
        $third = $this->backlogWorkOrder('WO 3', 'OTHER CO');

        Livewire::actingAs($this->manager())->test(BacklogPage::class)
            ->set('selected', [$first->id, $second->id])
            ->call('moveSelectedToIntake')
            ->assertSet('selected', []);

        $stillWaiting = fn (WorkOrder $workOrder): bool => WorkOrder::query()->inBacklog()->whereKey($workOrder->id)->exists();
        $this->assertFalse($stillWaiting($first));
        $this->assertFalse($stillWaiting($second));
        $this->assertTrue($stillWaiting($third));
    }

    public function test_select_page_selects_the_visible_cards(): void
    {
        $this->backlogWorkOrder('WO 1');
        $this->backlogWorkOrder('WO 2', 'ZED PRINT');

        $page = Livewire::actingAs($this->manager())->test(BacklogPage::class)->call('selectPage');

        $this->assertCount(2, $page->get('selected'));
    }

    public function test_the_action_refuses_a_work_order_that_is_not_in_the_backlog(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $this->expectException(WorkflowException::class);
        app(MoveWorkOrderOutOfBacklog::class)($workOrder, $this->manager());
    }

    public function test_renders_in_both_locales_and_the_query_count_does_not_grow(): void
    {
        $manager = $this->manager();
        $this->backlogWorkOrder('WO 1');

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            Livewire::actingAs($manager)->test(BacklogPage::class)->assertSee(__('Move to Intake'))->assertDontSee('enums.');
        }

        $count = function () use ($manager): int {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            Livewire::actingAs($manager)->test(BacklogPage::class);
            $total = count(\DB::getQueryLog());
            \DB::disableQueryLog();

            return $total;
        };
        $count();
        $small = $count();
        foreach (range(2, 9) as $index) {
            $this->backlogWorkOrder('WO '.$index, 'CO '.$index);
        }

        $this->assertSame($small, $count());
    }

    public function test_a_later_phase_waits_in_the_backlog_while_the_current_one_is_worked_on(): void
    {
        $workOrder = WorkOrder::factory()->create(['number' => 'WO 1']);
        $current = $this->deliverable(DeliverableStatus::IN_DESIGN, ['task_name' => 'Phase 2 sign'], $workOrder);
        $later = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Phase 3 wrap', 'in_backlog' => true], $workOrder);

        $listed = app(BacklogQuery::class)->run()->workOrders->items();
        $this->assertCount(1, $listed);
        $this->assertSame([$later->id], $listed[0]->deliverables->modelKeys());

        Livewire::actingAs($this->manager())->test(BacklogPage::class)->call('moveToIntake', $workOrder->id)->assertDispatched('toast');

        $this->assertFalse($later->fresh()->in_backlog);
        $this->assertSame(1, $this->eventCount($later, 'moved_out_of_backlog'));
        $this->assertSame(0, $this->eventCount($current, 'moved_out_of_backlog'));
        $this->assertSame(DeliverableStatus::IN_DESIGN, $current->fresh()->status);
    }
}
