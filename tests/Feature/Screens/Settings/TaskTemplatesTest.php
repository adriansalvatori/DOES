<?php

namespace Tests\Feature\Screens\Settings;

use App\Application\Delivery\Actions\AddTask;
use App\Application\Delivery\Queries\TaskTemplateOptions;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\TaskKind;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\TaskTemplate;
use App\Livewire\Settings\TaskTemplates;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class TaskTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_coordinator_can_open_it_and_others_cannot(): void
    {
        foreach (['admin', 'coordinator'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());
            Livewire::test(TaskTemplates::class)->assertOk()->assertSee(__('Task templates'));
        }

        foreach (['designer', 'sales', 'customerService'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());
            Livewire::test(TaskTemplates::class)->assertForbidden();
        }
    }

    public function test_the_migration_backfills_templates_from_subtask_presets_and_leaves_presets_alone(): void
    {
        DB::table('subtask_presets')->insert([
            ['title' => 'Ajustes cliente', 'emoji' => 'x', 'color_theme' => 'sky', 'category' => 'client_adjustments', 'sort_order' => 1, 'is_active' => 1, 'is_work_task' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'Enviar Proof cliente', 'emoji' => 'x', 'color_theme' => 'sky', 'category' => 'client_adjustments', 'sort_order' => 2, 'is_active' => 0, 'is_work_task' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);
        Schema::drop('task_templates');

        (require database_path('migrations/2026_11_25_200100_create_task_templates_table.php'))->up();

        $work = TaskTemplate::where('name_es', 'Ajustes cliente')->first();
        $this->assertSame('Client adjustments', $work->name_en);
        $this->assertSame('work', $work->kind);
        $this->assertTrue($work->active);

        $gate = TaskTemplate::where('name_es', 'Enviar Proof cliente')->first();
        $this->assertSame('Send proof to client', $gate->name_en);
        $this->assertSame('gate', $gate->kind);
        $this->assertSame(DeliverableStatus::CLIENT_REVIEW, $gate->advances_to);
        $this->assertTrue($gate->required);
        $this->assertFalse($gate->active);

        $this->assertSame(2, DB::table('subtask_presets')->count());
    }

    public function test_a_work_template_is_created_with_both_names(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(TaskTemplates::class)
            ->call('openCreateModal')
            ->set('name_en', 'Check bleed')
            ->set('name_es', 'Revisar sangrado')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showModal', false)
            ->assertSee('Check bleed');

        $template = TaskTemplate::where('name_en', 'Check bleed')->first();
        $this->assertSame('Revisar sangrado', $template->name_es);
        $this->assertSame('work', $template->kind);
        $this->assertNull($template->advances_to);
        $this->assertFalse($template->required);
    }

    public function test_a_gate_needs_the_status_it_advances_to(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(TaskTemplates::class)
            ->call('openCreateModal')
            ->set('name_en', 'Prepare files')
            ->set('name_es', 'Preparar archivos')
            ->set('kind', 'gate')
            ->call('save')
            ->assertHasErrors(['advances_to' => 'required'])
            ->set('advances_to', DeliverableStatus::READY_FOR_PRODUCTION->value)
            ->set('required', true)
            ->call('save')
            ->assertHasNoErrors();

        $template = TaskTemplate::where('name_en', 'Prepare files')->first();
        $this->assertSame(DeliverableStatus::READY_FOR_PRODUCTION, $template->advances_to);
        $this->assertTrue($template->required);
        $this->assertSame(TaskKind::GATE_PREPARE_PRODUCTION_FILES, $template->taskKind());
    }

    public function test_switching_a_gate_back_to_work_clears_advances_to_and_required(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $template = TaskTemplate::create(['name_en' => 'Gate', 'name_es' => 'Puerta', 'kind' => 'gate', 'advances_to' => 'client_review', 'required' => true]);

        Livewire::test(TaskTemplates::class)
            ->call('openEditModal', $template->id)
            ->assertSet('advances_to', 'client_review')
            ->set('kind', 'work')
            ->call('save')
            ->assertHasNoErrors();

        $template->refresh();
        $this->assertSame('work', $template->kind);
        $this->assertNull($template->advances_to);
        $this->assertFalse($template->required);
    }

    public function test_names_are_required_and_limited(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(TaskTemplates::class)
            ->call('openCreateModal')
            ->set('name_es', str_repeat('x', 121))
            ->call('save')
            ->assertHasErrors(['name_en' => 'required', 'name_es' => 'max']);
    }

    public function test_toggle_move_and_delete(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        DB::table('task_templates')->delete();
        $first = TaskTemplate::create(['name_en' => 'A', 'name_es' => 'A', 'sort' => 1]);
        $second = TaskTemplate::create(['name_en' => 'B', 'name_es' => 'B', 'sort' => 2]);

        $component = Livewire::test(TaskTemplates::class);

        $component->call('toggleActive', $first->id);
        $this->assertFalse($first->fresh()->active);

        $component->call('move', $second->id, 'up');
        $this->assertSame(1, $second->fresh()->sort);
        $this->assertSame(2, $first->fresh()->sort);

        $component->call('move', $second->id, 'up');
        $this->assertSame(1, $second->fresh()->sort);

        $component->call('delete', $first->id);
        $this->assertNull(TaskTemplate::find($first->id));
    }

    public function test_options_service_lists_active_templates_in_the_readers_locale_and_feeds_add_task(): void
    {
        DB::table('task_templates')->delete();
        TaskTemplate::create(['name_en' => 'Second', 'name_es' => 'Segunda', 'sort' => 2]);
        $gate = TaskTemplate::create(['name_en' => 'Send proof', 'name_es' => 'Enviar prueba', 'kind' => 'gate', 'advances_to' => 'client_review', 'required' => true, 'sort' => 1]);
        TaskTemplate::create(['name_en' => 'Off', 'name_es' => 'Apagada', 'active' => false, 'sort' => 3]);

        $options = app(TaskTemplateOptions::class);

        $this->assertSame(['Send proof', 'Second'], array_column($options->asOptions('en'), 'name'));
        $this->assertSame(['Enviar prueba', 'Segunda'], array_column($options->asOptions('es'), 'name'));
        $this->assertSame('client_review', $options->asOptions('en')[0]['advances_to']);

        $manager = User::factory()->coordinator()->create();
        $deliverable = Deliverable::factory()->inStatus(DeliverableStatus::IN_DESIGN)->create();
        $task = app(AddTask::class)($deliverable, $gate->nameFor('es'), $gate->taskKind(), $gate->advances_to, actor: $manager);

        $this->assertSame(TaskKind::GATE_SEND_PROOF, $task->kind);
        $this->assertSame('Enviar prueba', $task->title);
    }

    public function test_renders_in_both_languages_with_a_fixed_query_count(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (range(1, 15) as $i) {
            TaskTemplate::create(['name_en' => 'T'.$i, 'name_es' => 'P'.$i, 'sort' => $i]);
        }

        foreach (['en', 'es'] as $locale) {
            app()->setLocale($locale);
            DB::enableQueryLog();
            $component = Livewire::test(TaskTemplates::class);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            $component->assertSee(__('Task templates'))->assertSee(__('New template'));
            $this->assertLessThanOrEqual(8, $queries);
        }
    }
}
