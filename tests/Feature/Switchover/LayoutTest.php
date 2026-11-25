<?php

namespace Tests\Feature\Switchover;

use App\Domain\Delivery\Models\WorkOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_mounts_the_new_panel_and_modal_instead_of_the_legacy_ones(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get('/board')->assertOk()->getContent();

        $this->assertStringContainsString('wire:name="deliverables.deliverable-panel"', $html);
        $this->assertStringContainsString('wire:name="create-work-order.create-work-order-modal"', $html);
        $this->assertStringNotContainsString('wire:name="orders.order-detail-modal"', $html);
        $this->assertStringNotContainsString('wire:name="orders.create-order-modal"', $html);
    }

    public function test_header_has_a_single_new_work_order_button(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get('/work-orders')->assertOk()->getContent();
        $header = substr($html, (int) strpos($html, '<header'), (int) strpos($html, '</header>') - (int) strpos($html, '<header'));

        $this->assertSame(1, substr_count($header, "@click=\"\$dispatch('open-create-work-order')\""));
        $this->assertStringNotContainsString('open-create-order', $html);
    }

    public function test_legacy_order_detail_events_are_bridged_to_the_deliverable_panel(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('@open-order-detail.window', $html);
        $this->assertStringContainsString("\$dispatch('open-deliverable', { deliverableId: \$event.detail?.orderId })", $html);
    }

    public function test_toast_listens_for_created_work_orders_and_links_to_the_page(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('@work-order-created.window="created($event.detail)"', $html);
        $this->assertStringContainsString(__('Open work order'), $html);
        $this->assertStringContainsString('Work order :number created', str_replace(' ', ' ', $html));
    }

    public function test_tab_title_follows_the_navigation(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->get('/board')->assertSee('<title>'.__('Board'), false);
        $this->get(route('work-orders.show', $workOrder))->assertOk();
    }

    public function test_sidebar_lists_the_new_navigation_in_order_for_an_admin(): void
    {
        $html = $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()->getContent();

        $positions = [];
        foreach (['href="/board"', 'href="/plan-week"', 'href="/work-orders"', 'href="/resolver"', 'href="/overview"', 'href="/analytics"', 'href="/clients"', 'href="/contacts"', 'href="/backlog"', 'href="/trash"'] as $needle) {
            $positions[$needle] = strpos($html, $needle);
            $this->assertNotFalse($positions[$needle], $needle);
        }
        $ordered = $positions;
        asort($ordered);
        $this->assertSame(array_keys($positions), array_keys($ordered));
    }
}
