<?php

namespace Tests\Feature\Screens\WorkOrders;

use App\Domain\Delivery\Enums\AlertKind;
use App\Domain\Delivery\Enums\ApprovalSource;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\FulfillmentMethod;
use App\Domain\Delivery\Models\Alert;
use App\Domain\Delivery\Models\Approval;
use App\Domain\Delivery\Models\Block;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Hold;
use App\Domain\Delivery\Models\Phase;
use App\Domain\Delivery\Models\Receipt;
use App\Domain\Delivery\Models\WorkOrder;
use App\Livewire\WorkOrders\WorkOrderShow;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class WorkOrderShowTest extends TestCase
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

    private function workOrder(array $attributes = []): WorkOrder
    {
        $client = Client::create(['name' => 'KUYEN AREPAS']);

        return WorkOrder::factory()->create(['number' => 'WO 15741', 'client_id' => $client->id] + $attributes);
    }

    private function piece(WorkOrder $workOrder, DeliverableStatus $status, array $attributes = [], ?Phase $phase = null): Deliverable
    {
        return Deliverable::factory()->for($workOrder, 'workOrder')->inStatus($status)->create(['phase_id' => $phase?->id] + $attributes);
    }

    private function reviewerApproval(Deliverable $deliverable): void
    {
        Approval::factory()->create(['deliverable_id' => $deliverable->id, 'source' => ApprovalSource::REVIEWER]);
    }

    public function test_every_role_can_open_the_page_with_header_data(): void
    {
        $client = Client::create(['name' => 'TALPA']);
        $location = $client->locations()->create(['name' => 'MARIETTA', 'address' => '1 Main St']);
        $workOrder = WorkOrder::factory()->create(['number' => 'WO 15741', 'client_id' => $client->id, 'client_location_id' => $location->id, 'estimate_invoice_number' => 'EST-4821', 'fulfillment_method' => FulfillmentMethod::INSTALLATION]);
        $this->piece($workOrder, DeliverableStatus::IN_DESIGN, ['task_name' => 'Top sign']);

        foreach (['admin', 'coordinator', 'designer', 'sales', 'customerService'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())->get(route('work-orders.show', $workOrder))
                ->assertOk()->assertSee('WO 15741')->assertSee('Talpa')->assertSee('Marietta')->assertSee('EST-4821')->assertSee('Installation')->assertSee('Top sign')
                ->assertSee('Open client portal');
        }
    }

    public function test_meters_roll_up_approved_and_received(): void
    {
        $workOrder = $this->workOrder();
        $first = $this->piece($workOrder, DeliverableStatus::PRODUCTION_FILES);
        $second = $this->piece($workOrder, DeliverableStatus::FULFILLMENT);
        $this->piece($workOrder, DeliverableStatus::IN_DESIGN);
        Approval::factory()->fromClient()->create(['deliverable_id' => $first->id]);
        Receipt::factory()->create(['deliverable_id' => $second->id]);

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertSeeInOrder(['Approved by client', '1 / 3', 'Received', '1 / 3']);
    }

    public function test_phases_render_as_sections_and_a_single_batch_has_no_phase_title(): void
    {
        $workOrder = $this->workOrder();
        $location = Phase::factory()->create(['work_order_id' => $workOrder->id, 'name' => 'Phase 3 Location', 'position' => 1]);
        $catering = Phase::factory()->create(['work_order_id' => $workOrder->id, 'name' => 'Phase 4 Catering', 'position' => 2]);
        $this->piece($workOrder, DeliverableStatus::IN_DESIGN, ['task_name' => 'Location design'], $location);
        $this->piece($workOrder, DeliverableStatus::IN_DESIGN, ['task_name' => 'Catering design'], $catering);

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertSeeInOrder(['Phase 3 Location', 'Location design', 'Phase 4 Catering', 'Catering design']);

        $single = WorkOrder::factory()->create(['number' => 'WO 1']);
        $this->piece($single, DeliverableStatus::IN_DESIGN, ['task_name' => 'Only piece']);
        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $single])
            ->assertSee('Only piece')->assertSee('Deliverables');
    }

    public function test_deliverable_rows_open_the_panel_and_show_flags_hold_and_reviewer_check(): void
    {
        $workOrder = $this->workOrder();
        $held = $this->piece($workOrder, DeliverableStatus::INTERNAL_REVIEW, ['task_name' => 'Held piece']);
        Hold::factory()->create(['deliverable_id' => $held->id]);
        $approved = $this->piece($workOrder, DeliverableStatus::INTERNAL_REVIEW, ['task_name' => 'Approved piece']);
        $this->reviewerApproval($approved);
        User::factory()->reviewer()->create(['name' => 'Camila Ruiz']);

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertSeeHtml("open-deliverable', { deliverableId: {$held->id} }")
            ->assertSee('On hold')->assertSee('Camila');
    }

    public function test_designers_never_see_blocked_deliverables(): void
    {
        $workOrder = $this->workOrder();
        $blocked = $this->piece($workOrder, DeliverableStatus::IN_DESIGN, ['task_name' => 'Secret blocked piece']);
        Block::factory()->create(['deliverable_id' => $blocked->id]);
        $this->piece($workOrder, DeliverableStatus::IN_DESIGN, ['task_name' => 'Normal piece']);

        Livewire::actingAs(User::factory()->designer()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertSee('Normal piece')->assertDontSee('Secret blocked piece');
        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertSee('Secret blocked piece');
    }

    public function test_send_to_client_is_disabled_with_reason_until_every_piece_is_approved(): void
    {
        $workOrder = $this->workOrder();
        $approved = $this->piece($workOrder, DeliverableStatus::INTERNAL_REVIEW, ['task_name' => 'Done by Camila']);
        $this->piece($workOrder, DeliverableStatus::INTERNAL_REVIEW, ['task_name' => 'Waiting piece']);
        $this->piece($workOrder, DeliverableStatus::IN_DESIGN, ['task_name' => 'Still designing']);
        $this->reviewerApproval($approved);
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertSee('1/2')->assertSee('Not ready yet:')->assertSee('Still designing');
        $this->assertMatchesRegularExpression('/disabled(?!")/', $this->sendButtonHtml($component->html()));

        $this->reviewerApproval(Deliverable::where('task_name', 'Waiting piece')->first());
        $component = Livewire::actingAs($admin)->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertSee('Staying behind:')->assertSee('Still designing');
        $this->assertDoesNotMatchRegularExpression('/disabled(?!")/', $this->sendButtonHtml($component->html()));
    }

    private function sendButtonHtml(string $html): string
    {
        preg_match('/<button[^>]*sendToClient[^>]*>/', $html, $match);

        return $match[0] ?? '';
    }

    public function test_send_to_client_moves_the_ready_batch(): void
    {
        $workOrder = $this->workOrder();
        $piece = $this->piece($workOrder, DeliverableStatus::INTERNAL_REVIEW);
        $this->reviewerApproval($piece);

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->call('sendToClient')->assertDispatched('toast');

        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $piece->fresh()->status);
    }

    public function test_send_to_client_refusal_shows_a_toast_and_changes_nothing(): void
    {
        $workOrder = $this->workOrder();
        $piece = $this->piece($workOrder, DeliverableStatus::INTERNAL_REVIEW);

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->call('sendToClient')->assertDispatched('toast', type: 'error');

        $this->assertSame(DeliverableStatus::INTERNAL_REVIEW, $piece->fresh()->status);
    }

    public function test_approve_all_only_touches_client_review_pieces(): void
    {
        $workOrder = $this->workOrder();
        $waiting = $this->piece($workOrder, DeliverableStatus::CLIENT_REVIEW);
        $other = $this->piece($workOrder, DeliverableStatus::IN_DESIGN);

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->call('approveAll', 'client', true, true, 'Approved by phone')->assertDispatched('toast');

        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, $waiting->fresh()->status);
        $this->assertSame(DeliverableStatus::IN_DESIGN, $other->fresh()->status);
        $this->assertSame('Approved by phone', Approval::where('deliverable_id', $waiting->id)->first()->note);
    }

    public function test_approve_all_button_is_disabled_when_nothing_waits_for_the_client(): void
    {
        $workOrder = $this->workOrder();
        $this->piece($workOrder, DeliverableStatus::IN_DESIGN);

        $component = Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder]);
        $this->assertMatchesRegularExpression('/<button[^>]*open\(.approve.\)[^>]*disabled/', $component->html());
    }

    public function test_confirm_all_received_logs_receipts_and_completes_the_pieces(): void
    {
        $workOrder = $this->workOrder();
        $produced = $this->piece($workOrder, DeliverableStatus::PRODUCED);
        $stopped = $this->piece($workOrder, DeliverableStatus::PRODUCED);
        Hold::factory()->create(['deliverable_id' => $stopped->id]);

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->call('confirmAllReceived', 'pickup', '2026-11-24', 'Picked up by Kevin')->assertDispatched('toast');

        $this->assertSame(DeliverableStatus::DONE, $produced->fresh()->status);
        $this->assertSame(DeliverableStatus::PRODUCED, $stopped->fresh()->status);
        $receipt = Receipt::where('deliverable_id', $produced->id)->first();
        $this->assertSame(FulfillmentMethod::PICKUP, $receipt->method);
        $this->assertSame('Picked up by Kevin', $receipt->note);
    }

    public function test_sales_cannot_see_the_receipt_button(): void
    {
        $workOrder = $this->workOrder();
        $this->piece($workOrder, DeliverableStatus::PRODUCED);

        Livewire::actingAs(User::factory()->sales()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertDontSee('Confirm all received');
    }

    public function test_add_deliverable_and_add_phase(): void
    {
        $workOrder = $this->workOrder();
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->call('addPhase', 'Phase 2 Catering', '2026-12-18')->assertDispatched('toast');
        $phase = Phase::where('work_order_id', $workOrder->id)->first();
        $this->assertSame('Phase 2 Catering', $phase->name);
        $this->assertSame('2026-12-18', $phase->promised_date->toDateString());

        $component->call('addDeliverable', 'Catering menu', '2x1 m', 'Acrylic', 2, $phase->id)->assertDispatched('toast');
        $piece = Deliverable::where('task_name', 'Catering menu')->first();
        $this->assertSame($phase->id, $piece->phase_id);
        $this->assertSame(DeliverableStatus::INTAKE, $piece->status);
        $this->assertSame(2, $piece->quantity);
    }

    public function test_follow_up_alert_is_resolved_from_the_strip(): void
    {
        $workOrder = $this->workOrder();
        $piece = $this->piece($workOrder, DeliverableStatus::CLIENT_REVIEW);
        $alert = Alert::factory()->create(['deliverable_id' => $piece->id, 'kind' => AlertKind::CLIENT_FOLLOW_UP_1]);

        $component = Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertSee('Client follow-up 1')->assertSee('Log follow-up');
        $component->call('logFollowUp', $alert->id)->assertDispatched('toast')->assertDontSee('Client follow-up 1');

        $this->assertNotNull($alert->fresh()->resolved_at);
    }

    public function test_alert_of_another_work_order_cannot_be_resolved_here(): void
    {
        $workOrder = $this->workOrder();
        $this->piece($workOrder, DeliverableStatus::CLIENT_REVIEW);
        $foreign = Alert::factory()->create();

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->call('logFollowUp', $foreign->id)->assertNotFound();

        $this->assertNull($foreign->fresh()->resolved_at);
    }

    public function test_manager_edits_record_data_through_the_actions(): void
    {
        $workOrder = $this->workOrder(['promised_date' => '2026-12-04']);
        $this->piece($workOrder, DeliverableStatus::IN_DESIGN);
        $newClient = Client::create(['name' => 'TALPA']);
        $location = $newClient->locations()->create(['name' => 'CORPORATE', 'address' => '2 Main St']);

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->call('saveDetails', [
                'number' => 'WO 15742',
                'estimate_invoice_number' => 'EST-1',
                'promised_date' => '2026-12-10',
                'fulfillment_method' => 'installation',
                'client_id' => $newClient->id,
                'client_location_id' => $location->id,
            ])->assertDispatched('toast', message: 'Work order updated.');

        $fresh = $workOrder->fresh();
        $this->assertSame('WO 15742', $fresh->number);
        $this->assertSame('EST-1', $fresh->estimate_invoice_number);
        $this->assertSame('2026-12-10', $fresh->promised_date->toDateString());
        $this->assertSame(FulfillmentMethod::INSTALLATION, $fresh->fulfillment_method);
        $this->assertSame($newClient->id, $fresh->client_id);
        $this->assertSame($location->id, $fresh->client_location_id);
    }

    public function test_changing_the_client_clears_the_old_location_and_a_foreign_location_is_refused(): void
    {
        $workOrder = $this->workOrder();
        $oldLocation = $workOrder->client->locations()->create(['name' => 'OLD', 'address' => '1 Main St']);
        $workOrder->update(['client_location_id' => $oldLocation->id]);
        $other = Client::create(['name' => 'TALPA']);
        $admin = User::factory()->admin()->create();

        $component = Livewire::actingAs($admin)->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->call('saveDetails', ['client_id' => $other->id]);
        $this->assertNull($workOrder->fresh()->client_location_id);

        $component->call('saveDetails', ['client_location_id' => $oldLocation->id])->assertDispatched('toast', type: 'error', message: 'That location does not belong to the company.');
        $this->assertNull($workOrder->fresh()->client_location_id);
    }

    public function test_non_managers_cannot_edit_record_data(): void
    {
        $workOrder = $this->workOrder();
        $this->piece($workOrder, DeliverableStatus::IN_DESIGN);

        $component = Livewire::actingAs(User::factory()->designer()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertDontSee('Edit details')
            ->call('saveDetails', ['number' => 'WO 99999'])->assertDispatched('toast', type: 'error');

        $this->assertSame('WO 15741', $workOrder->fresh()->number);
    }

    public function test_record_edits_are_confirmed_old_to_new_in_the_browser(): void
    {
        $workOrder = $this->workOrder();
        $this->piece($workOrder, DeliverableStatus::IN_DESIGN);

        Livewire::actingAs(User::factory()->admin()->create())->test(WorkOrderShow::class, ['workOrder' => $workOrder])
            ->assertSeeHtml('KudosConfirmChange(confirmation)');
    }

    public function test_both_locales_render_translated(): void
    {
        $workOrder = $this->workOrder(['fulfillment_method' => FulfillmentMethod::PICKUP]);
        $piece = $this->piece($workOrder, DeliverableStatus::INTERNAL_REVIEW);
        $this->reviewerApproval($piece);
        $admin = User::factory()->admin()->create();

        app()->setLocale('en');
        Livewire::actingAs($admin)->test(WorkOrderShow::class, ['workOrder' => $workOrder])->assertSee('Send to client')->assertSee('Approve all');

        app()->setLocale('es');
        $html = Livewire::actingAs($admin)->test(WorkOrderShow::class, ['workOrder' => $workOrder])->html();
        $this->assertStringContainsString('Recogida', $html);
    }

    public function test_query_count_does_not_grow_with_more_deliverables(): void
    {
        $workOrder = $this->workOrder();
        $phase = Phase::factory()->create(['work_order_id' => $workOrder->id]);
        $admin = User::factory()->admin()->create();
        $count = function () use ($admin, $workOrder): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($admin)->test(WorkOrderShow::class, ['workOrder' => $workOrder]);
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $this->piece($workOrder, DeliverableStatus::INTERNAL_REVIEW, ['assignee_id' => User::factory()->designer()->create()->id], $phase);
        Block::factory()->create(['deliverable_id' => $this->piece($workOrder, DeliverableStatus::IN_DESIGN)->id]);
        $count();
        $small = $count();
        foreach (range(1, 8) as $i) {
            $piece = $this->piece($workOrder, DeliverableStatus::INTERNAL_REVIEW, ['assignee_id' => User::factory()->designer()->create()->id], $phase);
            $this->reviewerApproval($piece);
            Block::factory()->create(['deliverable_id' => $this->piece($workOrder, DeliverableStatus::IN_DESIGN)->id]);
        }
        $this->assertSame($small, $count());
    }
}
