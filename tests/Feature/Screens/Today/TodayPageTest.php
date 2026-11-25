<?php

namespace Tests\Feature\Screens\Today;

use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\Today\CustomerServiceSection;
use App\Livewire\Today\DesignerSection;
use App\Livewire\Today\ManagerSection;
use App\Livewire\Today\ReviewerSection;
use App\Livewire\Today\TodayPage;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class TodayPageTest extends TestCase
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

    private function deliverable(DeliverableStatus $status, array $attributes = [], ?WorkOrder $workOrder = null): Deliverable
    {
        $workOrder ??= WorkOrder::factory()->create(['client_id' => Client::firstOrCreate(['name' => 'ACME SIGNS LLC'])->id]);

        return Deliverable::factory()->for($workOrder, 'workOrder')->inStatus($status)->create($attributes);
    }

    public function test_designer_sees_planned_gate_checkbox_and_never_blocked_cards(): void
    {
        $designer = User::factory()->designer()->create(['wip_limit' => 4]);
        $planned = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-25', 'task_name' => 'Planned piece']);
        Task::factory()->gate(TaskKind::GATE_SEND_PROOF, DeliverableStatus::INTERNAL_REVIEW)->create(['order_id' => $planned->id, 'title' => 'Send proof']);
        $blocked = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-25', 'task_name' => 'Blocked piece']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        $this->actingAs($designer)->get(route('v2.today'))->assertOk()->assertSee('Planned piece')->assertDontSee('Blocked piece');

        Livewire::actingAs($designer)->test(DesignerSection::class, ['designerId' => $designer->id])
            ->assertSee('Acme Signs LLC')->assertSee('→ Internal review')->assertSee('1 / 4');
    }

    public function test_completing_the_gate_moves_the_card_and_undo_reopens_it(): void
    {
        $designer = User::factory()->designer()->create();
        $planned = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-25']);
        $gate = Task::factory()->gate(TaskKind::GATE_SEND_PROOF, DeliverableStatus::INTERNAL_REVIEW)->create(['order_id' => $planned->id]);

        Livewire::actingAs($designer)->test(DesignerSection::class, ['designerId' => $designer->id])
            ->call('completeTask', $gate->id)
            ->assertSet('undoTaskId', $gate->id)
            ->call('undoTask')
            ->assertSet('undoTaskId', null);

        $this->assertSame(DeliverableStatus::IN_DESIGN, $planned->refresh()->status);
        $this->assertFalse($gate->refresh()->isDone());

        Livewire::actingAs($designer)->test(DesignerSection::class, ['designerId' => $designer->id])->call('completeTask', $gate->id);
        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $planned->refresh()->status);
    }

    public function test_designer_cannot_complete_someone_elses_task(): void
    {
        $designer = User::factory()->designer()->create();
        $other = User::factory()->designer()->create();
        $theirs = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $other->id]);
        $gate = Task::factory()->gate(TaskKind::GATE_SEND_PROOF, DeliverableStatus::INTERNAL_REVIEW)->create(['order_id' => $theirs->id]);

        Livewire::actingAs($designer)->test(DesignerSection::class, ['designerId' => $designer->id])
            ->call('completeTask', $gate->id)->assertForbidden();
        Livewire::actingAs($designer)->test(DesignerSection::class, ['designerId' => $other->id])->assertForbidden();
    }

    public function test_follow_up_alert_can_be_logged(): void
    {
        $designer = User::factory()->designer()->create();
        $silent = $this->deliverable(DeliverableStatus::CLIENT_REVIEW, ['assignee_id' => $designer->id, 'task_name' => 'Silent piece']);
        $alert = Alert::factory()->create(['deliverable_id' => $silent->id, 'user_id' => $designer->id, 'kind' => AlertKind::CLIENT_FOLLOW_UP_2]);

        Livewire::actingAs($designer)->test(DesignerSection::class, ['designerId' => $designer->id])
            ->assertSee('Client silent 6 days')->assertSee('Silent piece')
            ->call('logFollowUp', $alert->id)->assertDontSee('Silent piece');

        $this->assertNotNull($alert->refresh()->resolved_at);
    }

    public function test_reviewer_approves_requests_changes_and_sends_a_ready_batch(): void
    {
        $camila = User::factory()->coordinator()->reviewer()->create();
        $workOrder = WorkOrder::factory()->create(['client_id' => Client::create(['name' => 'KUYEN'])->id]);
        $phase = Phase::factory()->create(['work_order_id' => $workOrder->id, 'name' => 'Location']);
        $first = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW, ['phase_id' => $phase->id, 'task_name' => 'First'], $workOrder);
        $second = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW, ['phase_id' => $phase->id, 'task_name' => 'Second'], $workOrder);
        $behind = $this->deliverable(DeliverableStatus::IN_DESIGN, ['phase_id' => $phase->id, 'task_name' => 'Still designing'], $workOrder);

        $component = Livewire::actingAs($camila)->test(ReviewerSection::class)
            ->assertSee('0 / 2 approved')->assertSee('Still designing');

        $component->call('sendBatch', $workOrder->id, $phase->id)->assertDispatched('toast', type: 'error');
        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $first->refresh()->status);

        $component->call('approve', $first->id)->assertSee('1 / 2 approved');
        $component->call('startRequestChanges', $second->id)->set('changesNote', '')->call('requestChanges')->assertHasErrors('changesNote');
        $component->set('changesNote', 'Fix the logo')->call('requestChanges')->assertHasNoErrors();
        $this->assertSame(DeliverableStatus::IN_DESIGN, $second->refresh()->status);

        $component->call('sendBatch', $workOrder->id, $phase->id);
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $first->refresh()->status);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $behind->refresh()->status);
        $this->assertTrue(Approval::where('deliverable_id', $first->id)->where('source', ApprovalSource::REVIEWER->value)->exists());
    }

    public function test_reviewer_section_is_forbidden_without_the_flag(): void
    {
        Livewire::actingAs(User::factory()->designer()->create())->test(ReviewerSection::class)->assertForbidden();
    }

    public function test_manager_sees_attention_lists_and_wip_and_can_view_a_designer(): void
    {
        $admin = User::factory()->admin()->create();
        $designer = User::factory()->designer()->create(['name' => 'Euraliz', 'wip_limit' => 3]);
        $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Nobody piece', 'due_target' => '2026-11-25']);
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'task_name' => 'Late piece', 'due_target' => '2026-11-01', 'planned_for' => '2026-11-25']);

        Livewire::actingAs($admin)->test(ManagerSection::class)
            ->assertSee('Nobody piece')->assertSee('Late piece')->assertSee('1 / 3');

        Livewire::actingAs($admin)->test(TodayPage::class)
            ->assertSee('Needs attention')->assertSet('sections', ['manager'])
            ->call('viewDesigner', $designer->id)->assertSet('sections', ['designer'])->assertSee('Late piece');
    }

    public function test_camila_style_user_sees_reviewer_and_manager_sections(): void
    {
        $camila = User::factory()->coordinator()->reviewer()->create();

        Livewire::actingAs($camila)->test(TodayPage::class)->assertSet('sections', ['reviewer', 'manager']);
    }

    public function test_customer_service_summarises_blocks_and_links_to_the_resolver(): void
    {
        $service = User::factory()->customerService()->create();
        $blocked = $this->deliverable(DeliverableStatus::IN_DESIGN);
        Block::factory()->create(['deliverable_id' => $blocked->id]);

        Livewire::actingAs($service)->test(CustomerServiceSection::class)
            ->assertSee('Acme Signs LLC')->assertSee(route('v2.resolver'), false);
    }

    public function test_sales_user_gets_the_empty_state(): void
    {
        Livewire::actingAs(User::factory()->sales()->create())->test(TodayPage::class)->assertSet('sections', [])->assertSee('Nothing for you today');
    }

    public function test_every_string_renders_in_both_locales_without_missing_keys(): void
    {
        $camila = User::factory()->coordinator()->reviewer()->create();
        $designer = User::factory()->designer()->create();
        $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);
        $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-25']);

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            $this->actingAs($camila)->get(route('v2.today'))->assertOk()->assertDontSee('enums.');
            $this->actingAs($designer)->get(route('v2.today'))->assertOk()->assertDontSee('enums.');
        }
    }

    public function test_page_does_not_run_queries_per_row(): void
    {
        $designer = User::factory()->designer()->create();
        $make = function (int $count) use ($designer): void {
            for ($i = 0; $i < $count; $i++) {
                $deliverable = $this->deliverable(DeliverableStatus::IN_DESIGN, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-25']);
                Task::factory()->gate(TaskKind::GATE_SEND_PROOF, DeliverableStatus::INTERNAL_REVIEW)->create(['order_id' => $deliverable->id]);
            }
        };
        $count = function () use ($designer): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($designer)->test(DesignerSection::class, ['designerId' => $designer->id]);

            return count(DB::getQueryLog());
        };

        $make(2);
        $few = $count();
        $make(8);

        $this->assertLessThanOrEqual($few + 2, $count());
    }
}
