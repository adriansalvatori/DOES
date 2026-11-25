<?php

namespace Tests\Feature;

use App\Enums\CoreStatus;
use App\Livewire\Overview\OverviewIndex;
use App\Models\Designer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class OverviewRedesignTest extends TestCase
{
    use RefreshDatabase;

    private function renderOverview(): Testable
    {
        $designer = Designer::create(['name' => 'Adrián Salvatori', 'active' => true, 'hex_color' => '#2563eb']);

        Order::create([
            'wo_number' => 'WO 20001',
            'task_name' => 'Rotulo frontal',
            'company_name' => 'Acme Signs',
            'location_name' => 'Brickell',
            'core_status' => CoreStatus::EN_PRODUCCION,
            'substatus' => 'OVERDUE',
            'designer_id' => $designer->id,
        ]);
        Order::create([
            'wo_number' => 'WO 20002',
            'task_name' => 'Menu board',
            'company_name' => 'Beta Foods',
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => 'TICKET',
        ]);

        return Livewire::actingAs(User::factory()->create())->test(OverviewIndex::class);
    }

    public function test_rows_show_wo_tag_location_pin_and_person_avatar(): void
    {
        $this->renderOverview()
            ->assertSee('WO 20001')
            ->assertSee('bg-wo', false)
            ->assertSee('text-pin', false)
            ->assertSee('Brickell')
            ->assertSee('Adrián Salvatori')
            ->assertSee('background-color: #2563eb', false);
    }

    public function test_health_substatus_is_toned_and_other_substatuses_are_neutral(): void
    {
        $this->assertSame('danger', OverviewIndex::substatusTone('OVERDUE'));
        $this->assertSame('danger', OverviewIndex::substatusTone('URGENTE'));
        $this->assertSame('warn', OverviewIndex::substatusTone('BLOQUEADA'));
        $this->assertSame('info', OverviewIndex::substatusTone('WAITING FOR CLIENT'));
        $this->assertSame('neutral', OverviewIndex::substatusTone('TICKET'));
        $this->assertSame('neutral', OverviewIndex::substatusTone('MI SUBESTATUS CUSTOM'));

        $html = $this->renderOverview()->html();

        $this->assertMatchesRegularExpression('/bg-danger-soft text-danger[^"]*"[^>]*>\s*OVERDUE/', $html);
        $this->assertDoesNotMatchRegularExpression('/bg-(danger|warn|info)-soft[^"]*"[^>]*>\s*TICKET/', $html);
    }

    public function test_no_stage_row_tints_and_no_tiny_text(): void
    {
        $html = $this->renderOverview()->html();

        // Avatar initials (x-ui.person) are exempt from the 12px floor.
        foreach (['bg-orange-200', 'bg-cyan-100', 'bg-pink-100', 'text-[8px]'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, $needle);
        }

        // Only the avatar initials may be 9px.
        $this->assertSame(substr_count($html, 'w-5 h-5 text-[9px]'), substr_count($html, 'text-[9px]'));
    }

    public function test_view_tabs_are_one_neutral_segmented_control(): void
    {
        $html = $this->renderOverview()->html();

        $this->assertStringContainsString('role="tablist"', $html);
        foreach (['bg-emerald-100', 'bg-pink-100', 'bg-cyan-100', 'bg-amber-100'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, $needle);
        }
    }

    public function test_record_data_inline_edits_ask_for_confirmation(): void
    {
        $html = $this->renderOverview()->html();

        $this->assertStringContainsString('confirmEstimate($el,', $html);
        $this->assertStringContainsString('KudosConfirmChange', $html);
    }
}
