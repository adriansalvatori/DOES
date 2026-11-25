<?php

namespace Tests\Feature\Screens\Settings;

use App\Application\Delivery\Queries\WorkflowLabels;
use App\Domain\Delivery\Enums\BlockReason;
use App\Domain\Delivery\Enums\CloseReason;
use App\Domain\Delivery\Enums\DeliverableFlag;
use App\Domain\Delivery\Enums\HoldReason;
use App\Domain\Delivery\Models\WorkflowLabel;
use App\Livewire\Settings\ReasonsAndFlags;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ReasonsAndFlagsTest extends TestCase
{
    use RefreshDatabase;

    public function test_labels_are_seeded_from_every_enum_code(): void
    {
        foreach ([
            'block_reason' => BlockReason::cases(),
            'hold_reason' => HoldReason::cases(),
            'close_reason' => CloseReason::cases(),
            'deliverable_flag' => DeliverableFlag::cases(),
        ] as $kind => $cases) {
            $codes = collect($cases)->map(fn ($case) => $case->value)->sort()->values()->all();
            $stored = WorkflowLabel::where('kind', $kind)->pluck('code')->sort()->values()->all();
            $this->assertSame($codes, $stored, $kind);
        }

        $row = WorkflowLabel::where(['kind' => 'block_reason', 'code' => 'missing_logo'])->first();
        $this->assertSame(__('enums.block_reason.missing_logo', [], 'en'), $row->label_en);
        $this->assertSame(__('enums.block_reason.missing_logo', [], 'es'), $row->label_es);
    }

    public function test_admin_and_coordinator_can_open_it_and_others_cannot(): void
    {
        foreach (['admin', 'coordinator'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());
            Livewire::test(ReasonsAndFlags::class)->assertOk()->assertSee(__('Reasons & flags'))->assertSee('missing_logo');
        }

        foreach (['designer', 'sales', 'customerService'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());
            Livewire::test(ReasonsAndFlags::class)->assertForbidden();
        }
    }

    public function test_each_tab_lists_its_own_codes(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ReasonsAndFlags::class)
            ->assertSee('awaiting_permit')->assertDontSee('cancelled_by_client')
            ->set('kind', 'close_reason')->assertSee('cancelled_by_client')->assertDontSee('missing_logo')
            ->set('kind', 'deliverable_flag')->assertSee('balance_due')
            ->set('kind', 'hold_reason')->assertSee('permit_in_progress')
            ->set('kind', 'bogus')->assertSet('kind', 'block_reason');
    }

    public function test_a_label_can_be_edited_in_both_languages(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());
        $row = WorkflowLabel::where(['kind' => 'block_reason', 'code' => 'missing_logo'])->first();

        Livewire::test(ReasonsAndFlags::class)
            ->call('edit', $row->id)
            ->assertSet('label_en', $row->label_en)
            ->set('label_en', 'No logo file')
            ->set('label_es', 'Sin archivo de logo')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('editingId', null)
            ->assertSee('No logo file')
            ->assertSee('Sin archivo de logo');

        $row->refresh();
        $this->assertSame('No logo file', $row->label_en);
        $this->assertSame('Sin archivo de logo', $row->label_es);

        $labels = app(WorkflowLabels::class);
        $this->assertSame('No logo file', $labels->label('block_reason', 'missing_logo', 'en'));
        $this->assertSame('Sin archivo de logo', $labels->label('block_reason', 'missing_logo', 'es'));
        $this->assertSame('missing_logo', $labels->codeForLabel('block_reason', 'sin archivo de logo'));
    }

    public function test_both_labels_are_required(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $row = WorkflowLabel::where('kind', 'block_reason')->first();

        Livewire::test(ReasonsAndFlags::class)
            ->call('edit', $row->id)
            ->set('label_en', '')
            ->set('label_es', str_repeat('x', 101))
            ->call('save')
            ->assertHasErrors(['label_en' => 'required', 'label_es' => 'max']);
    }

    public function test_a_label_can_be_switched_off_and_on(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $row = WorkflowLabel::where('kind', 'deliverable_flag')->first();

        Livewire::test(ReasonsAndFlags::class)->set('kind', 'deliverable_flag')->call('toggleActive', $row->id);
        $this->assertFalse($row->fresh()->active);

        Livewire::test(ReasonsAndFlags::class)->set('kind', 'deliverable_flag')->call('toggleActive', $row->id);
        $this->assertTrue($row->fresh()->active);
    }

    public function test_codes_cannot_be_added_or_deleted_from_the_screen(): void
    {
        $this->assertFalse(method_exists(ReasonsAndFlags::class, 'delete'));
        $this->assertFalse(method_exists(ReasonsAndFlags::class, 'create'));
    }

    public function test_a_label_of_another_tab_cannot_be_edited(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $flag = WorkflowLabel::where('kind', 'deliverable_flag')->first();

        $this->expectException(ModelNotFoundException::class);
        Livewire::test(ReasonsAndFlags::class)->set('kind', 'block_reason')->call('edit', $flag->id);
    }

    public function test_renders_in_both_languages_without_missing_keys_and_with_a_fixed_query_count(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach (['en', 'es'] as $locale) {
            App::setLocale($locale);
            DB::enableQueryLog();
            $component = Livewire::test(ReasonsAndFlags::class);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            $component->assertSee(__('Reasons & flags'))->assertSee(__('Block reasons'));
            $this->assertLessThanOrEqual(8, $queries);
        }
    }
}
