<?php

namespace Tests\Feature\Screens\Settings;

use App\Application\Delivery\Queries\BoardLimits;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Livewire\Settings\BoardSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class BoardSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_coordinator_can_open_it_and_others_cannot(): void
    {
        foreach (['admin', 'coordinator'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());
            Livewire::test(BoardSettings::class)->assertOk()->assertSee(__('Board & WIP'));
        }

        foreach (['designer', 'sales', 'customerService'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());
            Livewire::test(BoardSettings::class)->assertForbidden();
        }
    }

    public function test_every_column_shows_its_exit_rule_in_both_languages(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $rules = BoardSettings::exitRules();
        $this->assertSame(
            array_map(fn (DeliverableStatus $status): string => $status->value, DeliverableStatus::cases()),
            array_keys($rules),
        );

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(BoardSettings::class);
            foreach (DeliverableStatus::cases() as $status) {
                $component->assertSee($status->label())->assertSee(BoardSettings::exitRules()[$status->value]);
            }
        }
    }

    public function test_defaults_are_read_when_nothing_was_saved(): void
    {
        $limits = app(BoardLimits::class);

        $this->assertSame(4, $limits->defaultWipLimit());
        $this->assertSame(25, $limits->defaultColumnCap());
        $this->assertSame(25, $limits->columnCap(DeliverableStatus::IN_DESIGN));
    }

    public function test_limits_are_saved_and_read_back(): void
    {
        $this->actingAs(User::factory()->coordinator()->create());

        Livewire::test(BoardSettings::class)
            ->set('defaultWipLimit', 6)
            ->set('defaultColumnCap', 40)
            ->set('columnCaps.done', '10')
            ->call('save')
            ->assertHasNoErrors();

        $limits = app(BoardLimits::class);
        $this->assertSame(6, $limits->defaultWipLimit());
        $this->assertSame(40, $limits->defaultColumnCap());
        $this->assertSame(10, $limits->columnCap(DeliverableStatus::DONE));
        $this->assertSame(40, $limits->columnCap(DeliverableStatus::IN_DESIGN));
        $this->assertSame(['done' => 10], $limits->columnCapOverrides());

        Livewire::test(BoardSettings::class)->assertSet('defaultWipLimit', 6)->assertSet('columnCaps.done', '10')->assertSet('columnCaps.intake', '');
    }

    public function test_invalid_limits_are_refused(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(BoardSettings::class)
            ->set('defaultWipLimit', 0)
            ->set('defaultColumnCap', 1)
            ->set('columnCaps.done', '500')
            ->call('save')
            ->assertHasErrors(['defaultWipLimit', 'defaultColumnCap', 'columnCaps.done']);

        $this->assertNull(Setting::get('default_wip_limit'));
    }

    public function test_renders_with_a_fixed_query_count(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        DB::enableQueryLog();
        Livewire::test(BoardSettings::class)->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(10, $queries);
    }
}
