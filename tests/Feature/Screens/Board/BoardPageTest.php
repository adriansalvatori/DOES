<?php

namespace Tests\Feature\Screens\Board;

use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\Receipt;
use App\Domain\Delivery\Models\WorkOrder;
use App\Enums\CoreStatus;
use App\Livewire\Board\BoardPage;
use App\Livewire\Board\Column;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Feature\Domain\DeliveryTestCase;

class BoardPageTest extends DeliveryTestCase
{
    private function workOrderFor(string $client, ?string $location = null, string $number = 'WO 16001'): WorkOrder
    {
        $clientModel = Client::create(['name' => $client]);
        $locationModel = $location !== null ? ClientLocation::create(['client_id' => $clientModel->id, 'name' => $location, 'address' => '1 Main St']) : null;

        return WorkOrder::factory()->create(['number' => $number, 'client_id' => $clientModel->id, 'client_location_id' => $locationModel?->id]);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function board(User $viewer, array $query = []): Testable
    {
        return Livewire::actingAs($viewer)->withQueryParams($query)->test(BoardPage::class);
    }

    public function test_the_route_renders_for_a_manager_with_the_card_essentials(): void
    {
        $workOrder = $this->workOrderFor('FUERZA LATINA', 'ALBERTVILLE');
        $this->deliverable(DeliverableStatus::READY, [
            'company_name' => 'FUERZA LATINA',
            'task_name' => 'Sign + flyers',
            'flag_codes' => [DeliverableFlag::URGENT],
            'due_target' => '2026-09-25',
        ], $workOrder);

        $this->actingAs($this->manager())->get(route('board'))
            ->assertOk()
            ->assertSee('Board')
            ->assertSee('WO 16001')
            ->assertSee('Fuerza Latina')
            ->assertSee('Albertville')
            ->assertSee('Sign + flyers')
            ->assertSee('Urgent')
            ->assertSee('Overdue 5 d')
            ->assertSeeHtml('data-board-column="intake"')
            ->assertSeeHtml('data-board-column="ready_for_production"');
    }

    public function test_a_blocked_card_shows_its_reason_to_managers_and_is_hidden_from_designers(): void
    {
        $blocked = $this->deliverable(DeliverableStatus::IN_DESIGN, ['task_name' => 'Hidden banner']);
        Block::factory()->create(['deliverable_id' => $blocked->id, 'reason' => BlockReason::MISSING_LOGO]);
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['task_name' => 'Visible banner']);

        Livewire::actingAs($this->manager())->test(BoardPage::class)
            ->assertSee('Hidden banner')
            ->assertSee('Blocked · Missing logo');

        Livewire::actingAs($this->designer())->test(BoardPage::class)
            ->assertDontSee('Hidden banner')
            ->assertDontSee('Blocked ·')
            ->assertSee('Visible banner');
    }

    public function test_a_held_card_shows_its_hold_reason_and_planned_today_chip(): void
    {
        $held = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['planned_for' => '2026-09-30']);
        Hold::factory()->create(['deliverable_id' => $held->id, 'reason' => HoldReason::NO_RESPONSE]);

        Livewire::actingAs($this->manager())->test(BoardPage::class)
            ->assertSee('On hold · No response')
            ->assertSee('Planned today');
    }

    public function test_the_area_tabs_show_their_own_columns(): void
    {
        $this->deliverable(DeliverableStatus::PRODUCTION_QUEUE, ['task_name' => 'Queue item']);
        $this->deliverable(DeliverableStatus::DONE, ['task_name' => 'Finished item']);
        $manager = $this->manager();

        $this->board($manager)
            ->assertSeeHtml('data-board-column="intake"')
            ->assertDontSeeHtml('data-board-column="production_queue"')
            ->assertDontSee('Queue item');

        $this->board($manager, ['area' => 'production'])
            ->assertSeeHtml('data-board-column="ready_for_production"')
            ->assertSeeHtml('data-board-column="production_queue"')
            ->assertSeeHtml('data-board-column="produced"')
            ->assertDontSeeHtml('data-board-column="intake"')
            ->assertSee('Queue item');

        $this->board($manager, ['area' => 'fulfillment'])
            ->assertSeeHtml('data-board-column="fulfillment"')
            ->assertSeeHtml('data-board-column="done"')
            ->assertDontSeeHtml('data-board-column="production_queue"')
            ->assertSee('Finished item');
    }

    public function test_filters_narrow_the_columns(): void
    {
        $designer = $this->designer();
        $mine = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Mine only', 'assignee_id' => $designer->id, 'company_name' => 'ALPHA CO'], $this->workOrderFor('ALPHA CO', null, 'WO 1'));
        $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Someone elses', 'flag_codes' => [DeliverableFlag::TICKET], 'company_name' => 'BETA CO'], $this->workOrderFor('BETA CO', null, 'WO 2'));

        $manager = $this->manager();

        $this->board($manager, ['designer' => (string) $designer->id])->assertSee('Mine only')->assertDontSee('Someone elses');
        $this->board($manager, ['flags' => ['ticket']])->assertSee('Someone elses')->assertDontSee('Mine only');
        $this->board($manager, ['q' => 'ALPHA'])->assertSee('Mine only')->assertDontSee('Someone elses');
        $this->board($manager, ['company' => (string) $mine->workOrder->client_id])->assertSee('Mine only')->assertDontSee('Someone elses');
        $this->board($manager)->assertSee('Mine only')->assertSee('Someone elses');
    }

    public function test_designer_lanes_show_wip_on_in_design_and_can_be_turned_off(): void
    {
        $designer = $this->designer('Adrián');
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'task_name' => 'One']);
        $blocked = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'task_name' => 'Two']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        $manager = $this->manager();

        $this->board($manager, ['group' => 'designer'])->assertSee('Adrián')->assertSeeHtml('>1/4<');
        $this->board($manager, ['group' => 'none'])->assertDontSeeHtml('>1/4<')->assertSee('One');
    }

    public function test_a_card_can_be_moved_and_a_refused_move_shows_the_domain_message(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTAKE);

        $page = Livewire::actingAs($this->manager())->test(BoardPage::class)
            ->call('move', $deliverable->id, 'ready')
            ->assertDispatched('toast', message: 'Moved to Ready.')
            ->assertDispatched('board-refresh.intake')
            ->assertDispatched('board-refresh.ready');

        $this->assertSame(DeliverableStatus::READY, $deliverable->fresh()->status);

        $page->call('move', $deliverable->id, 'done')
            ->assertDispatched('toast', fn (string $event, array $params): bool => ($params['type'] ?? null) === 'error' && $params['message'] !== '');
        $this->assertNotSame(DeliverableStatus::DONE, $deliverable->fresh()->status);
    }

    public function test_a_designer_cannot_move_another_designers_card(): void
    {
        $other = $this->designer('Other', CoreStatus::CESAR_ORDERS_RECEIVED);
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $other->id]);

        Livewire::actingAs($this->designer())->test(BoardPage::class)
            ->call('move', $deliverable->id, 'in_design')
            ->assertDispatched('toast', message: 'You are not allowed to do this.', type: 'error');

        $this->assertSame(DeliverableStatus::READY, $deliverable->fresh()->status);
    }

    public function test_block_hold_resume_and_resolve_go_through_their_actions(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $page = Livewire::actingAs($this->manager())->test(BoardPage::class);

        $page->call('block', $deliverable->id, 'missing_logo', 'No logo yet')->assertDispatched('toast', message: 'Blocked.');
        $block = Block::query()->where('deliverable_id', $deliverable->id)->whereNull('resolved_at')->first();
        $this->assertSame(BlockReason::MISSING_LOGO, $block->reason);
        $this->assertSame('No logo yet', $block->note);

        $page->call('resolveBlock', $deliverable->id, 'Logo arrived')->assertDispatched('toast', message: 'Block resolved.');
        $this->assertNotNull($block->fresh()->resolved_at);

        $page->call('putOnHold', $deliverable->id, 'paused')->assertDispatched('toast', message: 'Put on hold.');
        $this->assertTrue($deliverable->fresh()->isOnHold());

        $page->call('resume', $deliverable->id)->assertDispatched('toast', message: 'Resumed.');
        $this->assertFalse($deliverable->fresh()->isOnHold());
    }

    public function test_designers_may_block_but_not_put_on_hold(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $page = Livewire::actingAs($this->designer())->test(BoardPage::class);

        $page->call('putOnHold', $deliverable->id, 'paused')->assertDispatched('toast', message: 'You are not allowed to do this.', type: 'error');
        $this->assertFalse($deliverable->fresh()->isOnHold());

        $page->call('block', $deliverable->id, 'other', 'Need info')->assertDispatched('toast', message: 'Blocked.');
        $this->assertTrue($deliverable->fresh()->isBlocked());
    }

    public function test_duplicate_and_trash(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Original']);
        $before = Deliverable::query()->count();

        Livewire::actingAs($this->manager())->test(BoardPage::class)
            ->call('duplicate', $deliverable->id)
            ->assertDispatched('toast', message: 'Duplicated.');
        $this->assertSame($before + 1, Deliverable::query()->count());

        Livewire::actingAs($this->designer())->test(BoardPage::class)
            ->call('trash', $deliverable->id)
            ->assertDispatched('toast', message: 'You are not allowed to do this.', type: 'error');
        $this->assertNotNull(Deliverable::query()->find($deliverable->id));

        Livewire::actingAs($this->manager())->test(BoardPage::class)
            ->call('trash', $deliverable->id)
            ->assertDispatched('toast', message: 'Moved to the trash.');
        $this->assertNull(Deliverable::query()->find($deliverable->id));
    }

    public function test_the_production_actions_pull_start_and_mark_produced(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY_FOR_PRODUCTION);
        $page = $this->board($this->manager(), ['area' => 'production'])->assertSee('Pull');

        $page->call('pullToProduction', $deliverable->id)->assertDispatched('board-refresh.production_queue');
        $this->assertSame(DeliverableStatus::PRODUCTION_QUEUE, $deliverable->fresh()->status);

        $page->call('startProduction', $deliverable->id);
        $this->assertSame(DeliverableStatus::IN_PRODUCTION, $deliverable->fresh()->status);

        $page->call('markProduced', $deliverable->id);
        $this->assertSame(DeliverableStatus::PRODUCED, $deliverable->fresh()->status);
    }

    public function test_the_fulfillment_tab_groups_by_work_order_and_confirms_receipts(): void
    {
        $workOrder = $this->workOrderFor('TALPA', null, 'WO 16125');
        $first = $this->deliverable(DeliverableStatus::FULFILLMENT, ['task_name' => 'Columna'], $workOrder);
        $second = $this->deliverable(DeliverableStatus::FULFILLMENT, ['task_name' => 'Poster'], $workOrder);
        $third = $this->deliverable(DeliverableStatus::FULFILLMENT, ['task_name' => 'Other order']);

        $page = $this->board($this->manager(), ['area' => 'fulfillment'])
            ->assertSee('Confirm all received')
            ->assertSee('Confirm received');

        $page->call('confirmReceived', 'deliverable', $third->id, 'pickup', '2026-09-29')
            ->assertDispatched('toast', message: 'Receipt logged for 1 deliverable.');
        $receipt = Receipt::query()->where('deliverable_id', $third->id)->first();
        $this->assertSame(FulfillmentMethod::PICKUP, $receipt->method);
        $this->assertSame('2026-09-29', $receipt->received_at->toDateString());
        $this->assertSame(DeliverableStatus::DONE, $third->fresh()->status);

        $page->call('confirmReceived', 'work_order', $workOrder->id, 'delivery')
            ->assertDispatched('toast', message: 'Receipt logged for 2 deliverables.');
        $this->assertSame(DeliverableStatus::DONE, $first->fresh()->status);
        $this->assertSame(DeliverableStatus::DONE, $second->fresh()->status);
    }

    public function test_a_column_pages_its_cards(): void
    {
        Deliverable::factory()->count(25 + 5)->inStatus(DeliverableStatus::READY)->create();

        Livewire::actingAs($this->manager())->test(Column::class, ['status' => 'ready'])
            ->assertSee('Show 5 more')
            ->call('showMore')
            ->assertDontSee('Show 5 more');
    }

    public function test_the_poll_only_refreshes_columns_that_changed(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTAKE);
        $page = Livewire::actingAs($this->manager())->test(BoardPage::class);

        $page->call('checkForChanges')->assertNotDispatched('board-refresh.intake');

        $this->freezeAt('2026-09-30 09:05:00');
        $deliverable->update(['status' => DeliverableStatus::READY]);

        $page->call('checkForChanges')
            ->assertDispatched('board-refresh.intake')
            ->assertDispatched('board-refresh.ready')
            ->assertNotDispatched('board-refresh.in_design');
    }

    public function test_the_card_menu_offers_actions_by_role(): void
    {
        $this->deliverable(DeliverableStatus::READY);

        Livewire::actingAs($this->manager())->test(BoardPage::class)
            ->assertSee('Put on hold...')->assertSee('Resolve block')->assertSee('Trash')->assertSee('Duplicate');

        Livewire::actingAs($this->designer())->test(BoardPage::class)
            ->assertSee('Block...')->assertDontSee('Put on hold...')->assertDontSee('Trash');

        Livewire::actingAs($this->customerService())->test(BoardPage::class)
            ->assertSee('Put on hold...')->assertDontSee('Trash');
    }

    public function test_cards_open_the_deliverable_panel_and_the_menu_is_rendered_once(): void
    {
        $this->deliverable(DeliverableStatus::READY);
        $this->deliverable(DeliverableStatus::READY);

        $html = Livewire::actingAs($this->manager())->test(BoardPage::class)->html();

        $this->assertStringContainsString('open-deliverable', $html);
        $this->assertSame(1, substr_count($html, 'role="menu"'));
    }

    public function test_it_renders_in_both_locales_without_missing_translations(): void
    {
        $workOrder = $this->workOrderFor('TALPA', 'MARIETTA');
        $held = $this->deliverable(DeliverableStatus::FULFILLMENT, ['flag_codes' => [DeliverableFlag::URGENT]], $workOrder);
        Hold::factory()->create(['deliverable_id' => $held->id]);
        $this->deliverable(DeliverableStatus::READY_FOR_PRODUCTION, [], $workOrder);

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            $manager = $this->manager();

            foreach (['design', 'production', 'fulfillment'] as $area) {
                $html = $this->board($manager, ['area' => $area])->html();
                $this->assertDoesNotMatchRegularExpression('/\b(enums|livewire)\.[a-z_]+\.[a-z_]+/', strip_tags($html), "Missing translation key in $locale/$area");
            }
        }

        app()->setLocale('es');
        Livewire::actingAs($this->manager())->test(BoardPage::class)
            ->assertSee('Diseño')
            ->assertSee('Producción')
            ->assertSee('Entrega')
            ->assertSee('Entrada');
    }

    public function test_the_board_does_not_run_a_query_per_card(): void
    {
        $workOrder = $this->workOrderFor('TALPA', 'MARIETTA');
        $designer = $this->designer();
        foreach ([DeliverableStatus::INTAKE, DeliverableStatus::READY, DeliverableStatus::IN_DESIGN] as $status) {
            Deliverable::factory()->count(10)->inStatus($status)->create(['work_order_id' => $workOrder->id, 'assignee_id' => $designer->id]);
        }

        $manager = $this->manager();
        $this->actingAs($manager)->get(route('board'))->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($manager)->get(route('board'))->assertOk();
        $withThirtyCards = count(DB::getQueryLog());

        Deliverable::factory()->count(30)->inStatus(DeliverableStatus::READY)->create(['work_order_id' => $workOrder->id, 'assignee_id' => $designer->id]);
        DB::flushQueryLog();
        $this->actingAs($manager)->get(route('board'))->assertOk();

        $this->assertSame($withThirtyCards, count(DB::getQueryLog()));
        $this->assertLessThan(60, $withThirtyCards);
    }

    public function test_cards_group_by_work_order_by_default_with_one_wo_tag_per_order(): void
    {
        $workOrder = $this->workOrderFor('TALPA', null, 'WO 16125');
        $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Columna'], $workOrder);
        $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Poster'], $workOrder);
        $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Solo sign'], $this->workOrderFor('LA RUANA', null, 'WO 16126'));
        $manager = $this->manager();

        $grouped = $this->board($manager)->assertSee('Columna')->assertSee('Poster')->html();
        $ungrouped = $this->board($manager, ['group' => 'none'])->html();

        $this->assertSame(1, substr_count($grouped, '<span>WO 16125</span>'));
        $this->assertSame(2, substr_count($ungrouped, '<span>WO 16125</span>'));
        $this->assertSame(1, substr_count($grouped, 'wire:key="wo-'), 'A work order with one card is not grouped.');
        $this->assertSame(1, substr_count($grouped, '<span>WO 16126</span>'));
        $this->assertStringNotContainsString(__('Group by: designer'), $this->board($manager, ['area' => 'production'])->html());
    }

    public function test_the_kanban_hides_cards_still_in_the_backlog(): void
    {
        $this->deliverable(DeliverableStatus::INTAKE, ['task_name' => 'Backlog card', 'in_backlog' => true]);
        $this->deliverable(DeliverableStatus::INTAKE, ['task_name' => 'Working card']);

        $this->board($this->manager())->assertSee('Working card')->assertDontSee('Backlog card');
    }
}
