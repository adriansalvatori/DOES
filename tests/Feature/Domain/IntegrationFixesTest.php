<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Data\OverviewFilters;
use App\Application\Delivery\Queries\BoardLimits;
use App\Application\Delivery\Queries\OverviewQuery;
use App\Application\Delivery\Queries\PlanWeekQuery;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\WorkflowLabel;
use App\Livewire\Board\BoardPage;
use App\Livewire\Board\Column;
use App\Livewire\PlanWeek\PlanWeekPage;
use App\Livewire\Today\DesignerSection;
use App\Livewire\WorkOrders\WorkOrderShow;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

class IntegrationFixesTest extends DeliveryTestCase
{
    public function test_enum_labels_read_the_edited_labels_and_fall_back_to_the_translation_files(): void
    {
        WorkflowLabel::query()->where('kind', 'block_reason')->where('code', 'other')->delete();
        $this->assertSame(__('enums.block_reason.other'), BlockReason::OTHER->label());

        WorkflowLabel::query()->updateOrCreate(['kind' => 'block_reason', 'code' => 'missing_logo'], ['label_en' => 'No logo file', 'label_es' => 'Sin archivo de logo']);
        WorkflowLabel::query()->updateOrCreate(['kind' => 'hold_reason', 'code' => 'paused'], ['label_en' => 'Paused by owner', 'label_es' => 'Pausada por el dueño']);
        WorkflowLabel::query()->updateOrCreate(['kind' => 'close_reason', 'code' => 'completed'], ['label_en' => 'All done', 'label_es' => 'Todo listo']);
        WorkflowLabel::query()->updateOrCreate(['kind' => 'deliverable_flag', 'code' => 'urgent'], ['label_en' => 'Rush', 'label_es' => 'Prisa']);

        $this->assertSame('No logo file', BlockReason::MISSING_LOGO->label());
        $this->assertSame('Paused by owner', HoldReason::PAUSED->label());
        $this->assertSame('All done', CloseReason::COMPLETED->label());
        $this->assertSame('Rush', DeliverableFlag::URGENT->label());
        $this->assertSame(__('enums.block_reason.other'), BlockReason::OTHER->label());

        app()->setLocale('es');
        $this->assertSame('Sin archivo de logo', BlockReason::MISSING_LOGO->label());
        $this->assertSame('Prisa', DeliverableFlag::URGENT->label());
    }

    public function test_labels_are_read_once_per_request_and_refreshed_when_edited(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        foreach (range(1, 20) as $ignored) {
            foreach (BlockReason::cases() as $reason) {
                $reason->label();
            }
        }
        $this->assertCount(1, DB::getQueryLog());

        $label = WorkflowLabel::query()->updateOrCreate(['kind' => 'block_reason', 'code' => 'other'], ['label_en' => 'Something else', 'label_es' => 'Otra cosa']);
        $this->assertSame('Something else', BlockReason::OTHER->label());

        $label->update(['label_en' => 'Anything else']);
        $this->assertSame('Anything else', BlockReason::OTHER->label());
    }

    public function test_the_board_columns_honour_the_board_and_wip_caps(): void
    {
        Setting::set('board_column_caps', json_encode(['ready' => 5]));
        Deliverable::factory()->count(12)->inStatus(DeliverableStatus::READY)->create();
        Deliverable::factory()->count(30)->inStatus(DeliverableStatus::INTAKE)->create();

        $this->assertSame(5, app(BoardLimits::class)->columnCap(DeliverableStatus::READY));

        $ready = Livewire::actingAs($this->manager())->test(Column::class, ['status' => 'ready']);
        $this->assertCount(5, $ready->instance()->column->cards);
        $this->assertSame(12, $ready->instance()->column->total);
        $ready->assertSee('Show 5 more')->call('showMore');
        $this->assertCount(10, $ready->instance()->column->cards);

        $intake = Livewire::actingAs($this->manager())->test(Column::class, ['status' => 'intake']);
        $this->assertCount(25, $intake->instance()->column->cards);
    }

    public function test_the_plan_week_query_returns_the_blocked_ready_backlog_capped_at_thirty(): void
    {
        $blockedCards = Deliverable::factory()->count(PlanWeekQuery::BLOCKED_BACKLOG_CAP + 2)->inStatus(DeliverableStatus::READY)->create();
        foreach ($blockedCards as $card) {
            Block::factory()->for($card, 'deliverable')->create();
        }
        $free = Deliverable::factory()->inStatus(DeliverableStatus::READY)->create();

        $plan = app(PlanWeekQuery::class)->run(now());

        $this->assertCount(30, $plan->blockedBacklog);
        $this->assertTrue($plan->backlog->contains('id', $free->id));
        $this->assertFalse($plan->blockedBacklog->contains('id', $free->id));
    }

    public function test_overview_filters_by_work_order_id(): void
    {
        $first = Deliverable::factory()->inStatus(DeliverableStatus::READY)->create();
        Deliverable::factory()->inStatus(DeliverableStatus::READY)->create();

        $page = app(OverviewQuery::class)->run(new OverviewFilters(workOrderId: $first->work_order_id));

        $this->assertSame([$first->work_order_id], array_map(fn ($row) => $row->workOrder->getKey(), $page->items()));
    }

    public function test_deliverable_changed_refreshes_the_board_only_where_something_moved(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::INTAKE);
        $page = Livewire::actingAs($this->manager())->test(BoardPage::class);

        $page->dispatch('deliverable-changed', deliverableId: $deliverable->id)->assertNotDispatched('board-refresh.intake');

        $this->freezeAt('2026-09-30 09:05:00');
        $deliverable->update(['status' => DeliverableStatus::READY]);

        $page->dispatch('deliverable-changed', deliverableId: $deliverable->id)
            ->assertDispatched('board-refresh.intake')
            ->assertDispatched('board-refresh.ready')
            ->assertNotDispatched('board-refresh.in_design');
    }

    public function test_deliverable_changed_reloads_today_plan_week_and_the_work_order_page(): void
    {
        $designer = $this->designer();
        $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'planned_for' => '2026-09-30']);

        $section = Livewire::actingAs($designer)->test(DesignerSection::class, ['designerId' => $designer->id])->assertSee($deliverable->task_name);
        $deliverable->update(['planned_for' => null, 'due_target' => '2026-10-20']);
        $section->dispatch('deliverable-changed', deliverableId: $deliverable->id)->assertDontSee($deliverable->task_name);

        $manager = $this->manager();
        $plan = Livewire::actingAs($manager)->test(PlanWeekPage::class)->assertDontSee('Fresh backlog card');
        $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Fresh backlog card']);
        $plan->dispatch('deliverable-changed', deliverableId: $deliverable->id)->assertSee('Fresh backlog card');

        $page = Livewire::actingAs($manager)->test(WorkOrderShow::class, ['workOrder' => $deliverable->workOrder])->assertSee($deliverable->task_name);
        $deliverable->update(['task_name' => 'Renamed piece']);
        $page->dispatch('deliverable-changed', deliverableId: $deliverable->id)->assertSee('Renamed piece');
    }
}
