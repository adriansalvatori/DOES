<?php

namespace Tests\Feature\Screens\Panel;

use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\DeliverableEvent;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\Deliverables\DeliverablePanel;
use App\Models\Client;
use App\Models\ClientLocation;
use App\Models\Media;
use App\Models\OrderComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliverablePanelTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function deliverable(DeliverableStatus $status = DeliverableStatus::IN_DESIGN, array $attributes = [], ?WorkOrder $workOrder = null): Deliverable
    {
        $workOrder ??= WorkOrder::factory()->create(WorkOrder::query()->exists() ? [] : ['number' => 'WO 15741']);

        return Deliverable::factory()->for($workOrder, 'workOrder')->inStatus($status)->create($attributes + ['task_name' => 'LOCATION DESIGN']);
    }

    private function panel(User $user, Deliverable $deliverable): Testable
    {
        return Livewire::actingAs($user)->test(DeliverablePanel::class)->dispatch('open-deliverable', deliverableId: $deliverable->id);
    }

    private function manager(): User
    {
        return User::factory()->coordinator()->create();
    }

    public function test_it_stays_closed_until_a_deliverable_is_opened_then_shows_the_header(): void
    {
        $client = Client::create(['name' => 'KUYEN']);
        $location = ClientLocation::create(['client_id' => $client->id, 'name' => 'TALPA 8', 'address' => '']);
        $workOrder = WorkOrder::factory()->create(['number' => 'WO 15741', 'client_id' => $client->id, 'client_location_id' => $location->id]);
        $phase = Phase::create(['work_order_id' => $workOrder->id, 'name' => 'Phase 3', 'position' => 1]);
        $deliverable = $this->deliverable(attributes: ['phase_id' => $phase->id, 'flag_codes' => [DeliverableFlag::URGENT]], workOrder: $workOrder);

        Livewire::actingAs($this->manager())->test(DeliverablePanel::class)
            ->assertSet('isOpen', false)
            ->assertDontSee('LOCATION DESIGN')
            ->dispatch('open-deliverable', deliverableId: $deliverable->id)
            ->assertSet('isOpen', true)
            ->assertSee('WO 15741')
            ->assertSee('LOCATION DESIGN')
            ->assertSee('Kuyen')
            ->assertSee('Talpa 8')
            ->assertSee('Phase 3')
            ->assertSee('Urgent')
            ->assertSee('In design')
            ->assertSee('Design')
            ->assertSee('Production')
            ->assertSee('Fulfillment');
    }

    public function test_opening_a_missing_deliverable_leaves_the_panel_closed(): void
    {
        Livewire::actingAs($this->manager())->test(DeliverablePanel::class)
            ->dispatch('open-deliverable', deliverableId: 999999)
            ->assertSet('isOpen', false);
    }

    public function test_the_work_order_tag_is_black_and_links_to_the_work_order_page_when_the_route_exists(): void
    {
        $deliverable = $this->deliverable();

        $html = $this->panel($this->manager(), $deliverable)->html();

        $this->assertStringContainsString('bg-wo', $html);
        if (Route::has('work-orders.show')) {
            $this->assertStringContainsString(route('work-orders.show', $deliverable->workOrder), $html);
        }
    }

    /**
     * @return array<string, array{0: DeliverableStatus, 1: string, 2: DeliverableStatus}>
     */
    public static function forwardMoves(): array
    {
        return [
            'intake' => [DeliverableStatus::INTAKE, 'Move to Ready', DeliverableStatus::READY],
            'ready' => [DeliverableStatus::READY, 'Move to In design', DeliverableStatus::IN_DESIGN],
            'in design' => [DeliverableStatus::IN_DESIGN, 'Move to Internal review', DeliverableStatus::INTERNAL_REVIEW],
            'ready for production' => [DeliverableStatus::READY_FOR_PRODUCTION, 'Pull to production', DeliverableStatus::PRODUCTION_QUEUE],
            'production queue' => [DeliverableStatus::PRODUCTION_QUEUE, 'Start production', DeliverableStatus::IN_PRODUCTION],
            'in production' => [DeliverableStatus::IN_PRODUCTION, 'Mark as produced', DeliverableStatus::PRODUCED],
        ];
    }

    /**
     * @dataProvider forwardMoves
     */
    #[DataProvider('forwardMoves')]
    public function test_the_primary_action_is_the_next_move_and_runs_it(DeliverableStatus $from, string $label, DeliverableStatus $to): void
    {
        $deliverable = $this->deliverable($from);

        $this->panel($this->manager(), $deliverable)
            ->assertSee($label)
            ->call('runPrimary')
            ->assertDispatched('deliverable-changed', deliverableId: $deliverable->id)
            ->assertSee($to->label());

        $this->assertSame($to, $deliverable->fresh()->status);
    }

    public function test_the_reviewer_approves_then_sends_the_work_to_the_client(): void
    {
        $camila = User::factory()->coordinator()->reviewer()->create();
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);

        $this->panel($camila, $deliverable)
            ->assertSee('Approve')
            ->call('runPrimary')
            ->assertSee('Approved by the reviewer.')
            ->assertSee('Send to client')
            ->call('runPrimary')
            ->assertSee('Client review');

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $deliverable->fresh()->status);
    }

    public function test_the_reviewer_can_send_work_back_to_design_with_a_note(): void
    {
        $camila = User::factory()->coordinator()->reviewer()->create();
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW);

        $this->panel($camila, $deliverable)
            ->call('openSheet', 'reviewerChanges')
            ->call('submitReviewerChanges')
            ->assertHasErrors('sheetNote')
            ->set('sheetNote', 'Logo too small')
            ->call('submitReviewerChanges')
            ->assertHasNoErrors()
            ->assertSet('sheet', null);

        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
    }

    public function test_a_designer_waiting_on_the_reviewer_has_no_primary_action_in_internal_review(): void
    {
        $designer = User::factory()->designer()->create();
        $deliverable = $this->deliverable(DeliverableStatus::INTERNAL_REVIEW, ['assignee_id' => $designer->id]);

        $this->panel($designer, $deliverable)->assertDontSeeHtml('data-primary-action')->call('showTab', 'approval')->assertSee('Waiting for the reviewer.');
    }

    public function test_client_approval_with_everything_confirmed_moves_to_production_files(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $this->panel($this->manager(), $deliverable)
            ->call('showTab', 'approval')
            ->assertSee('Waiting for the client.')
            ->set('approvalSource', 'client')
            ->set('measurementsConfirmed', true)
            ->set('estimateApproved', true)
            ->set('approvalNote', 'Approved by email')
            ->set('approvalEvidence', 'mail-123')
            ->call('submitClientApproval')
            ->assertHasNoErrors()
            ->assertSee('Client approval recorded.');

        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->fresh()->status);
        $approval = Approval::where('deliverable_id', $deliverable->id)->where('source', ApprovalSource::CLIENT->value)->firstOrFail();
        $this->assertSame('Approved by email', $approval->note);
        $this->assertSame('mail-123', $approval->evidence_path);
    }

    public function test_client_approval_without_measurements_raises_a_block_instead_of_moving(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $this->panel($this->manager(), $deliverable)
            ->call('showTab', 'approval')
            ->set('estimateApproved', true)
            ->call('submitClientApproval')
            ->assertSee('Client approval recorded; the missing items block this deliverable.')
            ->assertSeeHtml('data-banner="blocked"');

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $deliverable->fresh()->status);
        $this->assertTrue($deliverable->fresh()->isBlocked());
    }

    public function test_client_changes_need_a_note_and_send_the_work_back_to_design(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $this->panel($this->manager(), $deliverable)
            ->call('openSheet', 'clientChanges')
            ->call('submitClientChanges')
            ->assertHasErrors('sheetNote')
            ->set('sheetNote', 'Change the colors')
            ->call('submitClientChanges')
            ->assertHasNoErrors();

        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
    }

    public function test_the_primary_action_in_client_review_opens_the_approval_tab(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);

        $this->panel($this->manager(), $deliverable)->assertSee('Record client approval')->call('runPrimary')->assertSet('tab', 'approval');
    }

    public function test_block_and_resolve(): void
    {
        $deliverable = $this->deliverable();
        $panel = $this->panel($this->manager(), $deliverable);

        $panel->call('openSheet', 'block')
            ->call('submitBlock')
            ->assertHasErrors('sheetReason')
            ->set('sheetReason', BlockReason::OTHER->value)
            ->call('submitBlock')
            ->assertHasErrors('sheetNote')
            ->set('sheetReason', BlockReason::MISSING_LOGO->value)
            ->set('sheetNote', 'Waiting for the logo')
            ->call('submitBlock')
            ->assertHasNoErrors()
            ->assertSeeHtml('data-banner="blocked"')
            ->assertSee('Missing logo')
            ->assertSee('Waiting for the logo')
            ->assertDontSeeHtml('data-primary-action');

        $this->assertTrue($deliverable->fresh()->isBlocked());

        $panel->call('openSheet', 'resolve')->set('sheetNote', 'Logo received')->call('submitResolve')->assertDontSeeHtml('data-banner="blocked"');
        $this->assertFalse($deliverable->fresh()->isBlocked());
    }

    public function test_hold_and_resume_are_for_managers_and_customer_service(): void
    {
        $deliverable = $this->deliverable();

        $this->panel($this->manager(), $deliverable)
            ->call('openSheet', 'hold')
            ->set('sheetReason', HoldReason::NO_RESPONSE->value)
            ->set('sheetNote', 'Client is silent')
            ->call('submitHold')
            ->assertSeeHtml('data-banner="hold"')
            ->assertSee('No response')
            ->assertDontSeeHtml('data-primary-action')
            ->call('resume')
            ->assertDontSeeHtml('data-banner="hold"');

        $this->assertFalse($deliverable->fresh()->isOnHold());

        $designer = User::factory()->designer()->create();
        $this->panel($designer, $deliverable)->call('openSheet', 'hold')->set('sheetReason', HoldReason::PAUSED->value)->call('submitHold')->assertSee('You are not allowed to do that.');
        $this->assertFalse($deliverable->fresh()->isOnHold());
    }

    public function test_close_reopen_duplicate_and_trash(): void
    {
        $deliverable = $this->deliverable();
        $panel = $this->panel($this->manager(), $deliverable);

        $panel->call('openSheet', 'close')->call('submitClose')->assertHasErrors('sheetReason')
            ->set('sheetReason', CloseReason::CANCELLED_BY_CLIENT->value)->call('submitClose')
            ->assertSee('Cancelled by client');
        $this->assertSame(DeliverableStatus::DONE, $deliverable->fresh()->status);

        $panel->call('openSheet', 'reopen')->call('submitReopen')->assertDontSee('Cancelled by client');
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);

        $panel->call('duplicate');
        $copy = Deliverable::where('id', '!=', $deliverable->id)->firstOrFail();
        $panel->assertSet('deliverableId', $copy->id)->assertSee('Duplicated; showing the copy.');
        $this->assertSame($deliverable->work_order_id, $copy->work_order_id);

        $panel->call('openSheet', 'trash')->call('submitTrash')->assertSet('isOpen', false);
        $this->assertSoftDeleted('orders', ['id' => $copy->id]);
    }

    public function test_role_gating_of_the_secondary_actions(): void
    {
        $deliverable = $this->deliverable();

        $designer = $this->panel(User::factory()->designer()->create(), $deliverable);
        $designer->assertSee('Block')->assertSee('Duplicate')->assertDontSee('Move to trash')->assertDontSee('Put on hold');
        $designer->call('openSheet', 'trash')->call('submitTrash')->assertSee('You are not allowed to do that.')->assertSet('isOpen', true);
        $this->assertNull($deliverable->fresh()->deleted_at);

        $manager = $this->panel($this->manager(), $deliverable);
        $manager->assertSee('Move to trash')->assertSee('Put on hold')->assertSee('Plan for a day');
    }

    public function test_a_plain_designer_never_sees_blocked_work(): void
    {
        $designer = User::factory()->designer()->create();
        $deliverable = $this->deliverable();
        Block::factory()->create(['deliverable_id' => $deliverable->id]);

        $this->panel($designer, $deliverable)->assertSet('isOpen', false)->assertDontSee('LOCATION DESIGN');
        $this->panel(User::factory()->designer()->reviewer()->create(), $deliverable)->assertSet('isOpen', true);
    }

    public function test_the_workflow_refusal_is_shown_as_a_notice(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCTION_FILES);
        Task::factory()->gate(TaskKind::GATE_PREPARE_PRODUCTION_FILES, DeliverableStatus::READY_FOR_PRODUCTION)->create(['order_id' => $deliverable->id]);
        $designer = User::factory()->designer()->create();

        $this->panel($designer, $deliverable)->call('runPrimary')->assertSet('noticeTone', 'danger')->assertSeeHtml('data-notice="danger"');
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $deliverable->fresh()->status);
    }

    public function test_tasks_show_the_gate_target_and_completing_the_last_gate_advances_the_deliverable(): void
    {
        $deliverable = $this->deliverable();
        Task::factory()->create(['order_id' => $deliverable->id, 'title' => 'First proposal']);
        $gate = Task::factory()->gate(TaskKind::GATE_SEND_PROOF, DeliverableStatus::INTERNAL_REVIEW)->create(['order_id' => $deliverable->id, 'title' => 'Send proof']);

        $panel = $this->panel($this->manager(), $deliverable)
            ->assertSee('First proposal')
            ->assertSee('Send proof')
            ->assertSee('→ Internal review');

        $panel->call('toggleTask', $gate->id)->assertSee('Moved to Internal review.');
        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $deliverable->fresh()->status);

        $panel->call('toggleTask', $gate->id);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $deliverable->fresh()->status);
        $this->assertFalse($gate->fresh()->isDone());
    }

    public function test_a_task_of_another_deliverable_cannot_be_toggled_from_this_panel(): void
    {
        $deliverable = $this->deliverable();
        $other = Task::factory()->create(['order_id' => $this->deliverable()->id]);

        $this->panel($this->manager(), $deliverable)->call('toggleTask', $other->id);

        $this->assertFalse($other->fresh()->isDone());
    }

    public function test_adding_a_work_task_and_a_gate_task(): void
    {
        $deliverable = $this->deliverable();

        $this->panel($this->manager(), $deliverable)
            ->call('addTask')->assertHasErrors('newTaskTitle')
            ->set('newTaskTitle', 'Call the client')->call('addTask')->assertHasNoErrors()->assertSee('Call the client')
            ->set('newTaskTitle', 'Check proof')->set('newTaskIsGate', true)->call('addTask')
            ->assertSee('→ Internal review');

        $this->assertDatabaseHas('related_tasks', ['order_id' => $deliverable->id, 'title' => 'Call the client', 'kind' => TaskKind::WORK->value]);
        $this->assertDatabaseHas('related_tasks', ['order_id' => $deliverable->id, 'title' => 'Check proof', 'advances_to' => DeliverableStatus::INTERNAL_REVIEW->value, 'required' => 1]);
    }

    public function test_editing_details_saves_through_the_action_and_logs_old_and_new(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $phase = Phase::create(['work_order_id' => $workOrder->id, 'name' => 'Phase 2', 'position' => 1]);
        $deliverable = $this->deliverable(attributes: ['size' => '2x1 m', 'quantity' => 1], workOrder: $workOrder);

        $panel = $this->panel($this->manager(), $deliverable)
            ->call('startEditing')
            ->assertSet('editing', true)
            ->assertSet('details.size', '2x1 m')
            ->assertSet('edit.quantity', 1)
            ->assertSeeHtml('data-edit-form')
            ->assertSee('Phase 2');

        $panel->set('edit.size', '3x2 m')->set('edit.quantity', 4)->set('edit.material', 'acrylic')
            ->set('edit.phase_id', $phase->id)->set('edit.flags', ['urgent', 'ticket'])
            ->set('edit.trello_card_url', 'https://trello.com/c/abc')
            ->call('saveDetails')
            ->assertHasNoErrors()
            ->assertSet('editing', false)
            ->assertSee('Details saved.')
            ->assertSee('3x2 m · acrylic · 4');

        $fresh = $deliverable->fresh();
        $this->assertSame('3x2 m', $fresh->size);
        $this->assertSame(4, $fresh->quantity);
        $this->assertSame($phase->id, $fresh->phase_id);
        $this->assertSame('https://trello.com/c/abc', $fresh->trello_card_url);
        $this->assertTrue($fresh->hasFlag(DeliverableFlag::URGENT));
        $event = DeliverableEvent::where('order_id', $deliverable->id)->where('event_type', 'details_updated')->firstOrFail();
        $this->assertSame('2x1 m', $event->metadata['changes']['size']['old']);
        $this->assertSame('3x2 m', $event->metadata['changes']['size']['new']);

        $panel->call('showTab', 'timeline')->assertSee('Size: 2x1 m → 3x2 m');
    }

    public function test_detail_validation_messages_are_translated_and_nothing_is_saved(): void
    {
        $other = Phase::create(['work_order_id' => WorkOrder::factory()->create()->id, 'name' => 'Elsewhere', 'position' => 1]);
        $deliverable = $this->deliverable();

        $this->panel($this->manager(), $deliverable)
            ->call('startEditing')
            ->set('edit.name', '')->set('edit.quantity', 'abc')->set('edit.phase_id', $other->id)
            ->call('saveDetails')
            ->assertHasErrors(['edit.name', 'edit.quantity', 'edit.phase_id'])
            ->assertSee('The deliverable name is required.')
            ->assertSee('The quantity must be a whole number.')
            ->assertSee('Choose a phase of this work order.');

        $this->assertSame('LOCATION DESIGN', $deliverable->fresh()->task_name);
    }

    public function test_details_can_only_be_edited_by_active_staff(): void
    {
        $deliverable = $this->deliverable();

        $this->panel(User::factory()->designer()->create(['active' => false]), $deliverable)->call('startEditing')->assertSet('editing', false);
    }

    public function test_plan_sets_the_assignee_and_day_and_respects_the_work_limit(): void
    {
        $designer = User::factory()->designer()->create(['name' => 'Ana', 'wip_limit' => 1]);
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $designer->id, 'planned_for' => '2026-11-26']);

        $panel = $this->panel($this->manager(), $deliverable)->call('openSheet', 'plan')->set('planAssigneeId', (string) $designer->id)->set('planDay', '2026-11-26')->call('submitPlan');
        $panel->assertSet('noticeTone', 'danger');
        $this->assertNull($deliverable->fresh()->assignee_id);

        $panel->call('openSheet', 'plan')->set('planAssigneeId', (string) $designer->id)->set('planDay', '2026-11-26')->set('planOverride', true)->call('submitPlan')->assertSet('noticeTone', 'ok');
        $this->assertSame($designer->id, $deliverable->fresh()->assignee_id);
    }

    public function test_log_receipt_completes_a_produced_deliverable(): void
    {
        $workOrder = WorkOrder::factory()->create(['fulfillment_method' => FulfillmentMethod::DELIVERY]);
        $deliverable = $this->deliverable(DeliverableStatus::PRODUCED, workOrder: $workOrder);

        $this->panel($this->manager(), $deliverable)
            ->assertSee('Log receipt')
            ->call('runPrimary')
            ->assertSet('sheet', 'receipt')
            ->assertSet('receiptMethod', 'delivery')
            ->call('submitReceipt')
            ->assertHasNoErrors();

        $this->assertSame(DeliverableStatus::DONE, $deliverable->fresh()->status);
    }

    public function test_comments_files_and_the_trello_reference(): void
    {
        $deliverable = $this->deliverable(attributes: ['trello_card_url' => 'https://trello.com/c/xyz']);

        $this->panel($this->manager(), $deliverable)
            ->call('showTab', 'comments')
            ->assertSee('No comments yet.')
            ->assertSee('Older comments and attachments live on the Trello card.');

        OrderComment::factory()->create(['order_id' => $deliverable->id, 'user_id' => null, 'author_name' => 'Camila', 'body' => 'Please use the blue one']);
        Media::factory()->create(['order_id' => $deliverable->id, 'name' => 'logo.pdf']);

        $this->panel($this->manager(), $deliverable)
            ->call('showTab', 'comments')
            ->assertSee('Please use the blue one')
            ->assertSee('Camila')
            ->assertSee('logo.pdf')
            ->assertDontSee('Older comments and attachments live on the Trello card.');
    }

    public function test_the_timeline_is_translated_and_lists_the_most_recent_first(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $panel = $this->panel($this->manager(), $deliverable);
        $panel->call('runPrimary');

        $panel->call('showTab', 'timeline')->assertSee('Moved from Ready to In design')->assertSeeInOrder(['Moved from Ready to In design']);
    }

    public function test_legacy_events_are_translated_with_their_note(): void
    {
        $deliverable = $this->deliverable();
        DeliverableEvent::factory()->create(['order_id' => $deliverable->id, 'event_type' => 'SUBTASK_COMPLETED', 'metadata' => ['task_title' => 'Revision']]);
        DeliverableEvent::factory()->create(['order_id' => $deliverable->id, 'event_type' => 'MOVED_TO_ON_HOLD', 'metadata' => ['reason' => 'Awaiting the permit']]);
        DeliverableEvent::factory()->create(['order_id' => $deliverable->id, 'event_type' => 'SOMETHING_OLD', 'metadata' => []]);

        $this->panel($this->manager(), $deliverable)->call('showTab', 'timeline')
            ->assertSee('Task completed: Revision')
            ->assertSee('Put on hold')
            ->assertSee('Awaiting the permit')
            ->assertSee('Activity');
    }

    public function test_both_locales_render_every_tab_and_sheet_without_missing_keys(): void
    {
        $camila = User::factory()->coordinator()->reviewer()->create();
        $deliverable = $this->deliverable(DeliverableStatus::CLIENT_REVIEW);
        Block::factory()->create(['deliverable_id' => $deliverable->id, 'reason' => BlockReason::MISSING_LOGO]);

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            $panel = $this->panel($camila, $deliverable);
            foreach (['tasks', 'approval', 'comments', 'timeline'] as $tab) {
                $panel->call('showTab', $tab)->assertDontSee('enums.');
            }
            foreach (['block', 'hold', 'close', 'resolve', 'reopen', 'trash', 'plan', 'receipt', 'reviewerChanges', 'clientChanges'] as $sheet) {
                $panel->call('openSheet', $sheet)->assertDontSee('enums.');
            }
            $panel->call('closeSheet')->call('startEditing')->assertDontSee('enums.');
        }
    }

    public function test_queries_and_payload_stay_small_with_many_tasks_and_events(): void
    {
        $deliverable = $this->deliverable();
        Task::factory()->count(40)->create(['order_id' => $deliverable->id]);
        DeliverableEvent::factory()->count(60)->create(['order_id' => $deliverable->id]);

        $panel = $this->panel($this->manager(), $deliverable);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $panel->call('showTab', 'tasks')->call('showTab', 'timeline');
        $queries = count(DB::getQueryLog());

        $this->assertLessThanOrEqual(45, $queries);
        $this->assertLessThan(300 * 1024, strlen($panel->html()));
        $this->assertSame(0, substr_count($panel->call('showTab', 'tasks')->html(), 'x-data="{ open'), 'tasks use no per-row menus');
    }
}
