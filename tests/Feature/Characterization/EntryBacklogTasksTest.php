<?php

namespace Tests\Feature\Characterization;

use App\Livewire\Backlog\Index as BacklogIndex;
use App\Livewire\Tasks\TaskList;
use Livewire\Livewire;

class EntryBacklogTasksTest extends EntryTestCase
{
    public function test_add_to_workspace_activates_order_clears_new_flag_and_links_client(): void
    {
        $order = $this->makeOrder(['in_workspace' => false, 'is_new_from_trello' => true, 'company_name' => 'ACME']);

        Livewire::test(BacklogIndex::class)->call('addToWorkspace', $order->id);

        $order->refresh();
        $this->assertTrue($order->in_workspace);
        $this->assertFalse($order->is_new_from_trello);
        $this->assertNotNull($order->client_id);
    }

    public function test_remove_from_workspace_sends_order_back_to_backlog(): void
    {
        $order = $this->makeOrder(['in_workspace' => true]);

        Livewire::test(BacklogIndex::class)->call('removeFromWorkspace', $order->id);

        $this->assertFalse($order->fresh()->in_workspace);
    }

    public function test_task_list_toggle_flips_status_and_only_the_observer_flags_done_today(): void
    {
        $order = $this->makeOrder();
        $task = $this->makeTask($order, ['scheduled_date' => '2026-03-05']);
        $before = $order->fresh()->only(['core_status', 'substatus']);

        $component = Livewire::test(TaskList::class)->call('toggleTaskStatus', $task->id);

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);

        $component->call('toggleTaskStatus', $task->id);
        $this->assertSame('todo', $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
        $this->assertSame($before, $order->fresh()->only(['core_status', 'substatus']));
        $this->assertTrue($order->fresh()->done_today);
    }
}
