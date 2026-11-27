<?php

namespace Tests\Feature\Domain;

use App\Application\Delivery\Actions\AddDeliverable;
use App\Application\Delivery\Actions\AddTask;
use App\Application\Delivery\Actions\MoveWorkOrderOutOfBacklog;
use App\Application\Delivery\Actions\SetTaskPoints;
use App\Application\Delivery\Actions\UpdateDeliverableDetails;
use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Enums\Priority;
use App\Domain\Delivery\Exceptions\WorkflowException;
use App\Domain\Delivery\Models\Deliverable;
use App\Domain\Delivery\Models\Task;
use App\Domain\Delivery\Models\WorkOrder;
use App\Domain\Delivery\Support\PointsEstimator;
use App\Livewire\Board\BoardPage;
use App\Livewire\Deliverables\DeliverablePanel;
use App\Models\RelatedTask;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;

class PrioritiesAndPointsTest extends DeliveryTestCase
{
    /**
     * @return array<string, array{0: string, 1: int, 2?: string|null}>
     */
    public static function jobSizes(): array
    {
        return [
            'standard print piece' => ['BUSINESS CARDS', 1],
            'interior sign' => ['SIGN DE 48" X 36"', 3],
            'sample of a sign' => ['MUESTRA DE SIGN', 2],
            'exterior signage' => ['CHANNEL LETTERS', 5],
            'exterior signage with installation' => ['CHANNEL LETTERS', 8, 'INSTALACION'],
            'new location' => ['NUEVA LOCACIÓN', 8],
            'branding with several pieces' => ['BRANDING + LOGO + WEBSITE', 13],
            'reprint' => ['REPRINT FLYERS', 1],
            'logo design' => ['LOGO', 5],
            'logo on a decal is a print piece' => ['DECAL DE LOGO', 1],
        ];
    }

    #[DataProvider('jobSizes')]
    public function test_the_size_of_a_job_is_on_the_fibonacci_scale(string $task, int $points, ?string $installation = null): void
    {
        $this->assertSame($points, app(PointsEstimator::class)->estimate($task, 'ACME', null, $installation));
    }

    public function test_a_job_that_only_repeats_the_company_is_read_from_the_trello_title(): void
    {
        $this->assertSame(8, app(PointsEstimator::class)->estimate('ACME', 'ACME', 'WO 15000 - NUEVA LOCACION'));
    }

    /**
     * @return array<string, array{0: string, 1: ?string, 2: ?string, 3: string}>
     */
    public static function taskNatures(): array
    {
        return [
            'first proposal' => ['First Proposal', 'SUBTAREA', 'work', 'design'],
            'proof gate' => ['Enviar Proof cliente', 'SUBTAREA', 'gate_send_proof', 'design'],
            'client changes' => ['Ajustes cliente: cambiar color', 'SUBTAREA', 'work', 'revision'],
            'production prep' => ['Poner en alta', 'PONER EN ALTA', 'gate_prepare_production_files', 'production'],
            'production by title' => ['Carnitas decal alta', 'SUBTAREA', 'work', 'production'],
            'follow-up' => ['Follow Up Cliente', 'FOLLOW UP CLIENTE', 'work', 'coordination'],
            'welcome email' => ['Enviar correo bienvenida/orden nueva', 'ENVIAR CORREO DE BIENVENIDA', 'work', 'coordination'],
            'contact by text' => ['CONTACT CLIENT VIA TEXT', 'SUBTAREA', 'work', 'coordination'],
            'blocker without design words' => ['Esperar logo', 'RESOLVER', 'work', 'coordination'],
        ];
    }

    #[DataProvider('taskNatures')]
    public function test_tasks_are_read_as_design_revision_production_or_coordination(string $title, ?string $type, ?string $kind, string $nature): void
    {
        $this->assertSame($nature, app(PointsEstimator::class)->taskNature($title, $type, $kind));
    }

    public function test_task_points_follow_the_job_size_and_the_story_is_their_sum(): void
    {
        $estimator = app(PointsEstimator::class);

        $this->assertSame(8, $estimator->taskPoints(PointsEstimator::DESIGN, 8, true));
        $this->assertSame(3, $estimator->taskPoints(PointsEstimator::DESIGN, 8, false), 'A second design task is a revision.');
        $this->assertSame(5, $estimator->taskPoints(PointsEstimator::REVISION, 13, true));
        $this->assertSame(1, $estimator->taskPoints(PointsEstimator::REVISION, 3, true));
        $this->assertSame(1, $estimator->taskPoints(PointsEstimator::PRODUCTION, 13, true));
        $this->assertSame(0, $estimator->taskPoints(PointsEstimator::COORDINATION, 13, true));

        $deliverable = app(AddDeliverable::class)(WorkOrder::factory()->create(), 'Nueva locacion', actor: $this->manager());
        $this->assertNull($deliverable->fresh()->points, 'A story without tasks has no points yet.');

        $manager = $this->manager();
        $welcome = app(AddTask::class)($deliverable, 'Enviar correo de bienvenida', actor: $manager);
        $proposal = app(AddTask::class)($deliverable, 'First Proposal', actor: $manager);
        $changes = app(AddTask::class)($deliverable, 'Ajustes cliente', actor: $manager);

        $this->assertSame([0, 8, 3], [$welcome->points, $proposal->points, $changes->points]);
        $this->assertSame(11, $deliverable->fresh()->points);

        $changes->delete();
        $this->assertSame(8, $deliverable->fresh()->points);
    }

    public function test_legacy_tasks_count_toward_the_story_too(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'CHANNEL LETTERS']);

        RelatedTask::create(['order_id' => $deliverable->id, 'title' => 'First Proposal', 'type' => 'SUBTAREA', 'status' => 'pending']);

        $this->assertSame(5, $deliverable->fresh()->points);
    }

    public function test_backlog_tasks_stay_unscored_until_the_work_enters_the_workspace(): void
    {
        $manager = $this->manager();
        $waiting = WorkOrder::factory()->create();
        $card = Deliverable::factory()->inBacklog()->for($waiting, 'workOrder')->create(['task_name' => 'CHANNEL LETTERS']);
        $proposal = Task::factory()->for($card, 'deliverable')->create(['title' => 'First Proposal']);
        $alta = Task::factory()->for($card, 'deliverable')->create(['title' => 'Poner en alta']);

        $this->assertNull($proposal->points);
        $this->assertNull($card->fresh()->points);

        app(MoveWorkOrderOutOfBacklog::class)($waiting, $manager);

        $this->assertSame([5, 1], [$proposal->fresh()->points, $alta->fresh()->points]);
        $this->assertSame(6, $card->fresh()->points);
    }

    public function test_filling_tasks_scores_them_in_order_and_keeps_manual_points(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'NUEVA LOCACION']);
        $rows = [
            ['title' => 'Enviar correo bienvenida', 'type' => 'ENVIAR CORREO DE BIENVENIDA', 'points' => null],
            ['title' => 'Primera propuesta', 'type' => 'SUBTAREA', 'points' => null],
            ['title' => 'Otra version', 'type' => 'SUBTAREA', 'points' => null],
            ['title' => 'Poner en alta', 'type' => 'PONER EN ALTA', 'points' => null],
            ['title' => 'Ajustes cliente', 'type' => 'SUBTAREA', 'points' => 13],
        ];
        foreach ($rows as $index => $row) {
            DB::table('related_tasks')->insert($row + ['order_id' => $deliverable->id, 'status' => 'done', 'created_at' => "2026-09-0{$index} 10:00:00", 'updated_at' => now()]);
        }
        $backlog = $this->deliverable(DeliverableStatus::READY, ['in_backlog' => true]);
        DB::table('related_tasks')->insert(['order_id' => $backlog->id, 'title' => 'First Proposal', 'status' => 'todo', 'created_at' => now(), 'updated_at' => now()]);

        $this->assertSame(4, app(PointsEstimator::class)->fillTasks());

        $this->assertSame([0, 8, 3, 1, 13], DB::table('related_tasks')->where('order_id', $deliverable->id)->orderBy('id')->pluck('points')->map(fn ($points) => (int) $points)->all());
        $this->assertSame(25, $deliverable->fresh()->points);
        $this->assertNull($backlog->fresh()->points);
    }

    public function test_every_deliverable_starts_at_medium_priority_and_priority_is_edited_with_history(): void
    {
        $deliverable = app(AddDeliverable::class)(WorkOrder::factory()->create(), 'Flyers', actor: $this->manager());

        $this->assertSame(Priority::MEDIUM, $deliverable->priority);
        $this->assertSame('medium', DB::table('orders')->where('id', $deliverable->id)->value('priority'));

        $diff = app(UpdateDeliverableDetails::class)($deliverable, ['priority' => Priority::HIGHEST], $this->manager());

        $this->assertSame(['old' => 'medium', 'new' => 'highest'], $diff['priority']);
        $this->assertSame(Priority::HIGHEST, $deliverable->fresh()->priority);
        $this->assertSame(1, $this->eventCount($deliverable, 'details_updated'));
    }

    public function test_task_points_are_set_by_hand_on_the_fibonacci_scale_and_logged(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $task = Task::factory()->for($deliverable, 'deliverable')->create(['title' => 'First Proposal', 'points' => 3]);
        $set = app(SetTaskPoints::class);

        $this->assertTrue($set($task, 8, $this->manager()));
        $this->assertSame(8, $deliverable->fresh()->points);
        $this->assertSame(1, $this->eventCount($deliverable, 'task_points_changed'));
        $this->assertFalse($set($task->fresh(), 8, $this->manager()));

        $this->expectException(WorkflowException::class);
        $set($task->fresh(), 4, $this->manager());
    }

    public function test_the_panel_changes_priority_and_task_points_and_shows_the_story_total(): void
    {
        $deliverable = $this->deliverable(DeliverableStatus::READY);
        $task = Task::factory()->for($deliverable, 'deliverable')->create(['title' => 'First Proposal', 'points' => 2]);

        Livewire::actingAs($this->manager())->test(DeliverablePanel::class)
            ->dispatch('open-deliverable', deliverableId: $deliverable->id)
            ->assertSee(__('sum of its tasks'))
            ->assertSeeHtml('data-task-points="'.$task->id.'"')
            ->call('changePriority', 'high')
            ->call('changeTaskPoints', $task->id, '13');

        $this->assertSame(Priority::HIGH, $deliverable->fresh()->priority);
        $this->assertSame(13, $task->fresh()->points);
        $this->assertSame(13, $deliverable->fresh()->points);
    }

    public function test_board_cards_show_points_priority_and_a_dropdown_of_their_tasks(): void
    {
        $card = $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Urgent wrap', 'priority' => 'highest']);
        Task::factory()->for($card, 'deliverable')->done()->create(['title' => 'First Proposal', 'points' => 5]);
        Task::factory()->for($card, 'deliverable')->create(['title' => 'Ajustes cliente', 'points' => 2]);
        $this->deliverable(DeliverableStatus::READY, ['task_name' => 'Plain flyer', 'points' => 1, 'priority' => 'medium']);

        $html = Livewire::actingAs($this->manager())->test(BoardPage::class)->html();

        $this->assertStringContainsString('title="7 points"', $html);
        $this->assertStringContainsString('title="1 point"', $html);
        $this->assertSame(1, substr_count($html, 'title="Priority: '), 'Medium, the default, shows no marker.');
        $this->assertStringContainsString('title="Priority: Highest"', $html);
        $this->assertStringContainsString('1/2 tasks', $html);
        $this->assertStringContainsString('Ajustes cliente', $html);
        $this->assertSame(1, substr_count($html, 'data-card-tasks'), 'Only cards with tasks get the dropdown.');
    }
}
