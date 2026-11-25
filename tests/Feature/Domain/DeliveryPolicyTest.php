<?php

namespace Tests\Feature\Domain;

use App\Domain\Delivery\Enums\DeliverableStatus;
use App\Domain\Delivery\Models\Deliverable;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;

class DeliveryPolicyTest extends DeliveryTestCase
{
    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function abilities(): array
    {
        return [
            'manager closes' => ['close', 'manager', true],
            'designer cannot close' => ['close', 'designer', false],
            'customer service cannot close' => ['close', 'customerService', false],
            'manager reopens' => ['reopen', 'manager', true],
            'designer cannot reopen' => ['reopen', 'designer', false],
            'manager trashes' => ['trash', 'manager', true],
            'sales cannot trash' => ['trash', 'sales', false],
            'reviewer approves' => ['approveByReviewer', 'reviewer', true],
            'manager approves for the reviewer' => ['approveByReviewer', 'manager', true],
            'designer cannot approve as reviewer' => ['approveByReviewer', 'designer', false],
            'customer service holds' => ['putOnHold', 'customerService', true],
            'manager holds' => ['putOnHold', 'manager', true],
            'designer cannot hold' => ['putOnHold', 'designer', false],
            'manager unplans' => ['unplan', 'manager', true],
            'designer cannot unplan' => ['unplan', 'designer', false],
            'sales creates work orders' => ['createWorkOrder', 'sales', true],
            'designer blocks' => ['block', 'designer', true],
            'customer service resolves blocks' => ['resolveBlock', 'customerService', true],
            'sales does not log receipts' => ['logReceipt', 'sales', false],
            'designer logs receipts' => ['logReceipt', 'designer', true],
            'sales does not move production' => ['moveInProduction', 'sales', false],
        ];
    }

    #[DataProvider('abilities')]
    public function test_each_ability_follows_the_role(string $ability, string $who, bool $expected): void
    {
        $user = match ($who) {
            'manager' => $this->manager(),
            'designer' => $this->designer(),
            'reviewer' => $this->reviewer(),
            'customerService' => $this->customerService(),
            'sales' => User::factory()->sales()->create(),
        };
        $subject = $ability === 'createWorkOrder' ? Deliverable::class : $this->deliverable(DeliverableStatus::IN_DESIGN);

        $this->assertSame($expected, Gate::forUser($user)->allows($ability, $subject));
    }

    public function test_a_lead_designer_counts_as_a_manager(): void
    {
        $lead = User::factory()->designer()->create(['is_lead' => true]);

        $this->assertTrue(Gate::forUser($lead)->allows('close', $this->deliverable()));
    }

    public function test_inactive_users_can_do_nothing(): void
    {
        $user = User::factory()->create(['active' => false]);

        $this->assertFalse(Gate::forUser($user)->allows('block', $this->deliverable()));
        $this->assertFalse(Gate::forUser($user)->allows('createWorkOrder', Deliverable::class));
    }

    public function test_a_designer_only_moves_their_own_or_unassigned_work(): void
    {
        $adrian = $this->designer('Adrián');
        $cesar = $this->designer('César');
        $mine = $this->deliverable(DeliverableStatus::READY, ['assignee_id' => $adrian->id]);
        $unassigned = $this->deliverable(DeliverableStatus::READY);

        $this->assertTrue(Gate::forUser($adrian)->allows('move', $mine));
        $this->assertTrue(Gate::forUser($adrian)->allows('move', $unassigned));
        $this->assertFalse(Gate::forUser($cesar)->allows('move', $mine));
    }
}
