<?php

namespace Tests\Unit\Domain;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Workflow\DeliverableWorkflow;
use App\Domain\Delivery\Workflow\TransitionReason;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowMatrixTest extends TestCase
{
    /**
     * Every status pair with the move the matrix must give it.
     *
     * @return array<string, array{0: DeliverableStatus, 1: DeliverableStatus, 2: string|null}>
     */
    public static function everyPair(): array
    {
        $backward = [
            'internal_review>in_design',
            'client_review>in_design',
            'ready_for_production>production_files',
            'production_queue>ready_for_production',
        ];

        $cases = [];
        foreach (DeliverableStatus::cases() as $from) {
            foreach (DeliverableStatus::cases() as $to) {
                $pair = $from->value.'>'.$to->value;

                $expected = match (true) {
                    $from === $to => null,
                    $from === DeliverableStatus::DONE => 'reopen',
                    $to === DeliverableStatus::DONE => 'close',
                    $to->position() === $from->position() + 1 => 'forward',
                    in_array($pair, $backward, true) => 'back',
                    $to === DeliverableStatus::READY => 'manager',
                    default => null,
                };

                $cases[$pair] = [$from, $to, $expected];
            }
        }

        return $cases;
    }

    #[DataProvider('everyPair')]
    public function test_the_matrix_gives_every_pair_the_expected_move(DeliverableStatus $from, DeliverableStatus $to, ?string $expected): void
    {
        $this->assertSame($expected, DeliverableWorkflow::ruleFor($from, $to));
    }

    public function test_only_managers_make_manager_and_reopen_moves(): void
    {
        $workflow = app(DeliverableWorkflow::class);
        $designer = User::factory()->designer()->make();
        $coordinator = User::factory()->coordinator()->make();

        $this->assertFalse($workflow->isAllowedByMatrix(DeliverableStatus::IN_DESIGN, DeliverableStatus::READY, $designer));
        $this->assertTrue($workflow->isAllowedByMatrix(DeliverableStatus::IN_DESIGN, DeliverableStatus::READY, $coordinator));
        $this->assertTrue($workflow->isAllowedByMatrix(DeliverableStatus::IN_DESIGN, DeliverableStatus::READY, null));
        $this->assertFalse($workflow->isAllowedByMatrix(DeliverableStatus::DONE, DeliverableStatus::FULFILLMENT, $designer));
        $this->assertTrue($workflow->isAllowedByMatrix(DeliverableStatus::INTAKE, DeliverableStatus::READY, $designer));
    }

    public function test_a_task_reversal_may_step_back_one_status(): void
    {
        $this->assertNull(DeliverableWorkflow::ruleFor(DeliverableStatus::PRODUCED, DeliverableStatus::IN_PRODUCTION));
        $this->assertSame('back', DeliverableWorkflow::ruleFor(DeliverableStatus::PRODUCED, DeliverableStatus::IN_PRODUCTION, TransitionReason::TASK_COMPLETED, ['reversal' => true]));
    }

    public function test_status_neighbours(): void
    {
        $this->assertSame(DeliverableStatus::READY, DeliverableStatus::INTAKE->next());
        $this->assertSame(DeliverableStatus::DONE, DeliverableStatus::FULFILLMENT->next());
        $this->assertNull(DeliverableStatus::DONE->next());
        $this->assertNull(DeliverableStatus::INTAKE->previous());
        $this->assertSame(DeliverableStatus::PRODUCTION_FILES, DeliverableStatus::READY_FOR_PRODUCTION->previous());
    }
}
