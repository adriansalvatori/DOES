<?php

namespace Tests\Feature\Screens\Ops;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\Overview\OverviewPage;
use App\Livewire\Overview\Page\OverviewRowLine;
use App\Models\Client;
use App\Models\User;
use Livewire\Livewire;
use Tests\Feature\Domain\DeliveryTestCase;

class OverviewPageTest extends DeliveryTestCase
{
    private function workOrderWithPieces(string $number = 'WO 500', int $pieces = 2): WorkOrder
    {
        $client = Client::firstOrCreate(['name' => 'KUYEN MARIETTA']);
        $workOrder = WorkOrder::factory()->create(['number' => $number, 'client_id' => $client->id, 'promised_date' => '2026-10-10', 'estimate_invoice_number' => 'EST-1']);
        foreach (range(1, $pieces) as $index) {
            $this->deliverable(DeliverableStatus::IN_DESIGN, ['task_name' => "Piece {$index}", 'size' => '4x8', 'material' => 'Acrylic', 'quantity' => $index], $workOrder);
        }

        return $workOrder;
    }

    public function test_only_admin_and_coordinator_may_open_it(): void
    {
        foreach ([User::factory()->designer()->create(), User::factory()->sales()->create(), $this->customerService()] as $user) {
            $this->actingAs($user)->get(route('overview'))->assertForbidden();
        }
        foreach ([User::factory()->admin()->create(), $this->manager()] as $user) {
            $this->actingAs($user)->get(route('overview'))->assertOk();
        }
    }

    public function test_one_line_per_work_order_with_its_deliverables_in_the_accordion(): void
    {
        $this->workOrderWithPieces('WO 500', 3);
        $this->workOrderWithPieces('WO 501', 1);

        Livewire::actingAs($this->manager())->test(OverviewPage::class)
            ->assertSee('WO 500')->assertSee('WO 501')
            ->assertSee('Kuyen Marietta')
            ->assertSee('Piece 3')
            ->assertSee('Acrylic')
            ->assertSeeHtml('Piece 1');

        $this->assertSame(2, OverviewPageProbe::lineCount($this->manager()));
    }

    public function test_tabs_and_filters_narrow_the_list(): void
    {
        $this->workOrderWithPieces('WO 500');
        $done = WorkOrder::factory()->create(['number' => 'WO 600']);
        $this->deliverable(DeliverableStatus::DONE, ['task_name' => 'Finished'], $done);
        $backlog = WorkOrder::factory()->create(['number' => 'WO 700']);
        $this->deliverable(DeliverableStatus::INTAKE, ['in_backlog' => true], $backlog);

        $page = Livewire::actingAs($this->manager())->test(OverviewPage::class);
        $numbers = fn () => collect($page->instance()->rows()->items())->map(fn ($row) => $row->workOrder->number)->sort()->values()->all();

        $this->assertSame(['WO 500', 'WO 600'], $numbers());
        $page->call('setTab', 'done');
        $this->assertSame(['WO 600'], $numbers());
        $page->call('setTab', 'backlog');
        $this->assertSame(['WO 700'], $numbers());
        $page->call('setTab', 'all')->set('workOrderNumber', '500');
        $this->assertSame(['WO 500'], $numbers());
    }

    public function test_loads_a_hundred_lines_at_a_time_as_you_scroll(): void
    {
        foreach (range(1, 102) as $index) {
            $this->deliverable(DeliverableStatus::READY, [], WorkOrder::factory()->create(['number' => 'WO '.(1000 + $index)]));
        }

        $page = Livewire::actingAs($this->manager())->test(OverviewPage::class);

        $this->assertSame(100, count($page->instance()->rows()->items()));
        $this->assertSame(102, $page->instance()->rows()->total());
        $page->assertSee(__('Showing :shown of :total', ['shown' => '100', 'total' => '102']));

        $page->call('loadMore');
        $this->assertSame(102, count($page->instance()->rows()->items()));
        $page->call('loadMore')->assertSet('limit', 200);

        $page->set('search', 'WO 1001');
        $this->assertSame(100, $page->get('limit'), 'Changing a filter starts again from the first chunk.');
    }

    public function test_urgent_and_due_date_toggles_combine_with_tabs_and_old_links_still_work(): void
    {
        $urgent = $this->workOrderWithPieces('WO 500', 1);
        $urgent->deliverables()->first()->forceFill(['flag_codes' => ['urgent']])->save();
        $this->workOrderWithPieces('WO 501', 1);
        $numbers = fn ($page): array => collect($page->instance()->rows()->items())->map(fn ($row) => $row->workOrder->number)->sort()->values()->all();

        $page = Livewire::actingAs($this->manager())->test(OverviewPage::class)->call('setTab', 'active')->set('urgent', true);
        $this->assertSame(['WO 500'], $numbers($page));
        $page->assertSee(__('Urgent'))->call('clearFilter', 'urgent');
        $this->assertSame(['WO 500', 'WO 501'], $numbers($page));

        $legacy = Livewire::withQueryParams(['tab' => 'urgent'])->actingAs($this->manager())->test(OverviewPage::class);
        $legacy->assertSet('tab', 'all')->assertSet('urgent', true);
    }

    public function test_editing_a_work_order_column_goes_through_the_action_and_logs_old_and_new(): void
    {
        $workOrder = $this->workOrderWithPieces('WO 500', 2);
        $manager = $this->manager();

        Livewire::actingAs($manager)->test(OverviewRowLine::class, ['workOrderId' => $workOrder->id])
            ->call('saveWorkOrderField', 'number', '501')
            ->assertReturned(['ok' => true, 'value' => 'WO 501'])
            ->call('saveWorkOrderField', 'estimate_invoice_number', 'INV-77')
            ->call('saveWorkOrderField', 'promised_date', '2026-12-01')
            ->call('saveWorkOrderField', 'installation_types', ['KUDOS', 'CLIENTE']);

        $workOrder->refresh();
        $this->assertSame('WO 501', $workOrder->number);
        $this->assertSame('INV-77', $workOrder->estimate_invoice_number);
        $this->assertSame('2026-12-01', $workOrder->promised_date->toDateString());
        $this->assertSame(['KUDOS', 'CLIENTE'], $workOrder->installation_types);

        $piece = Deliverable::query()->where('work_order_id', $workOrder->id)->first();
        $event = $piece->events()->where('event_type', 'work_order_updated')->oldest('id')->first();
        $this->assertSame(['old' => 'WO 500', 'new' => 'WO 501'], $event->metadata['changes']['number']);
        $this->assertSame('WO 501', $this->legacyRow($piece)->wo_number);
    }

    public function test_a_duplicate_wo_number_is_refused_and_nothing_changes(): void
    {
        $first = $this->workOrderWithPieces('WO 500');
        $this->workOrderWithPieces('WO 501');

        Livewire::actingAs($this->manager())->test(OverviewRowLine::class, ['workOrderId' => $first->id])
            ->call('saveWorkOrderField', 'number', 'WO 501')
            ->assertReturned(fn (array $result): bool => $result['ok'] === false && filled($result['message']))
            ->assertDispatched('toast');

        $this->assertSame('WO 500', $first->fresh()->number);
    }

    public function test_line_fields_apply_to_every_deliverable_of_the_work_order(): void
    {
        $workOrder = $this->workOrderWithPieces('WO 500', 2);

        Livewire::actingAs($this->manager())->test(OverviewRowLine::class, ['workOrderId' => $workOrder->id])
            ->call('saveLineField', 'production_note', 'Rush job')
            ->call('saveLineField', 'delivery_note', 'Dock B')
            ->call('saveLineField', 'email_date', '2026-11-10')
            ->call('saveLineField', 'production_processed_at', '2026-11-12')
            ->call('saveLineField', 'overview_checked', '1');

        foreach (Deliverable::query()->where('work_order_id', $workOrder->id)->get() as $piece) {
            $row = $this->legacyRow($piece);
            $this->assertSame('Rush job', $row->production_note);
            $this->assertSame('Dock B', $row->delivery_note);
            $this->assertSame('2026-11-10', substr($row->email_date, 0, 10));
            $this->assertSame('2026-11-12', substr($row->production_processed_at, 0, 10));
            $this->assertSame(1, (int) $row->overview_checked);
            $this->assertSame(1, $this->eventCount($piece, 'overview_updated') > 0 ? 1 : 0);
        }
    }

    public function test_one_deliverable_can_be_edited_from_the_accordion_and_varying_values_unify_from_the_line(): void
    {
        $workOrder = $this->workOrderWithPieces('WO 500', 2);
        [$first, $second] = Deliverable::query()->where('work_order_id', $workOrder->id)->orderBy('id')->get()->all();
        $line = Livewire::actingAs($this->manager())->test(OverviewRowLine::class, ['workOrderId' => $workOrder->id]);

        $line->call('saveLineField', 'production_note', 'Only the first', $first->id)->assertReturned(['ok' => true, 'value' => 'Only the first']);
        $this->assertSame('Only the first', $this->legacyRow($first)->production_note);
        $this->assertNull($this->legacyRow($second)->production_note);

        // The line shows the first value; typing that same value from the line still writes it to every deliverable.
        $line->call('saveLineField', 'production_note', 'Only the first');
        $this->assertSame('Only the first', $this->legacyRow($second)->production_note);

        $other = $this->workOrderWithPieces('WO 600', 1)->deliverables()->first();
        $line->call('saveLineField', 'production_note', 'Nope', $other->id)->assertReturned(fn (array $result): bool => $result['ok'] === false);
        $this->assertNull($this->legacyRow($other)->production_note);
    }

    public function test_the_estimate_review_saves_on_every_deliverable_and_unknown_reviewers_are_refused(): void
    {
        $workOrder = $this->workOrderWithPieces('WO 500', 2);
        $line = Livewire::actingAs($this->manager())->test(OverviewRowLine::class, ['workOrderId' => $workOrder->id]);

        $line->call('saveLineField', 'review_status', 'camila')->assertReturned(['ok' => true, 'value' => 'CAMILA'])->assertSee('INV');
        foreach ($workOrder->deliverables()->get() as $piece) {
            $this->assertSame('CAMILA', $this->legacyRow($piece)->review_status);
        }

        $line->call('saveLineField', 'review_status', 'Bob')->assertReturned(fn (array $result): bool => $result['ok'] === false);
        $line->call('saveLineField', 'review_status', '')->assertReturned(['ok' => true, 'value' => null])->assertSee('EST');
    }

    public function test_the_designer_can_be_changed_for_the_whole_line_or_one_deliverable(): void
    {
        $workOrder = $this->workOrderWithPieces('WO 500', 2);
        $closed = $this->deliverable(DeliverableStatus::DONE, ['task_name' => 'Delivered piece'], $workOrder);
        $ana = $this->designer('Ana');
        $bea = $this->designer('Bea');
        [$first, $second] = Deliverable::query()->where('work_order_id', $workOrder->id)->whereKeyNot($closed->id)->orderBy('id')->get()->all();
        $line = Livewire::actingAs($this->manager())->test(OverviewRowLine::class, ['workOrderId' => $workOrder->id]);

        $line->call('assignDesigner', $ana->id)->assertReturned(['ok' => true]);
        $this->assertSame([$ana->id, $ana->id], [$first->fresh()->assignee_id, $second->fresh()->assignee_id]);
        $this->assertNull($closed->fresh()->assignee_id, 'Closed work keeps its designer.');
        $this->assertSame(1, $this->eventCount($first, 'assignee_changed'));

        $line->call('assignDesigner', $bea->id, $second->id);
        $this->assertSame([$ana->id, $bea->id], [$first->fresh()->assignee_id, $second->fresh()->assignee_id]);

        $line->call('assignDesigner', null, $first->id);
        $this->assertNull($first->fresh()->assignee_id);
    }

    public function test_only_lines_someone_else_changed_refresh_when_the_page_checks(): void
    {
        $changed = $this->workOrderWithPieces('WO 500', 1);
        $untouched = $this->workOrderWithPieces('WO 501', 1);
        $page = Livewire::actingAs($this->manager())->test(OverviewPage::class);

        $page->call('checkForChanges')->assertNotDispatched("overview-line-stale.{$changed->id}");

        $this->travel(5)->seconds();
        $changed->deliverables()->first()->forceFill(['production_note' => 'Edited elsewhere'])->save();

        $page->call('checkForChanges')
            ->assertDispatched("overview-line-stale.{$changed->id}")
            ->assertNotDispatched("overview-line-stale.{$untouched->id}");
    }

    public function test_the_row_component_refuses_designers_even_when_called_directly(): void
    {
        $workOrder = $this->workOrderWithPieces('WO 500');
        $lead = User::factory()->designer()->create(['is_lead' => true]);

        Livewire::actingAs($lead)->test(OverviewRowLine::class, ['workOrderId' => $workOrder->id])
            ->call('saveWorkOrderField', 'number', 'WO 999')->assertForbidden();
        Livewire::actingAs($lead)->test(OverviewRowLine::class, ['workOrderId' => $workOrder->id])
            ->call('assignDesigner', $lead->id)->assertForbidden();

        $this->assertSame('WO 500', $workOrder->fresh()->number);
    }

    public function test_an_edit_re_renders_only_its_row_and_the_response_is_small(): void
    {
        $workOrder = $this->workOrderWithPieces('WO 500', 3);

        $response = Livewire::actingAs($this->manager())->test(OverviewRowLine::class, ['workOrderId' => $workOrder->id])
            ->call('saveLineField', 'production_note', 'Rush job');

        $this->assertStringContainsString('Rush job', $response->html());
        $this->assertStringNotContainsString('WO 501', $response->html());
        $this->assertLessThan(300 * 1024, strlen($response->html()));
    }

    public function test_renders_in_both_locales_without_missing_keys_and_keeps_queries_flat(): void
    {
        $manager = $this->manager();
        $this->workOrderWithPieces('WO 500', 2);

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            Livewire::actingAs($manager)->test(OverviewPage::class)->assertSee(__('Order name'))->assertDontSee('enums.');
        }

        $count = function () use ($manager): int {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            Livewire::actingAs($manager)->test(OverviewPage::class);
            $total = count(\DB::getQueryLog());
            \DB::disableQueryLog();

            return $total;
        };
        $count();
        $small = $count();
        foreach (range(1, 6) as $index) {
            $this->workOrderWithPieces('WO '.(800 + $index), 3);
        }

        $this->assertSame($small, $count());
    }
}

/**
 * Counts the Overview lines of a rendered page.
 */
class OverviewPageProbe
{
    public static function lineCount(User $user): int
    {
        return substr_count(Livewire::actingAs($user)->test(OverviewPage::class)->html(), 'wire:name="overview.page.overview-row-line"');
    }
}
