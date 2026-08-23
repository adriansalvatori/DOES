<?php

namespace App\Console\Commands;

use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearDemoData extends Command
{
    protected $signature = 'orders:clear';

    protected $description = 'Clear all demo orders, related tasks, due date histories, and events';

    public function handle(): int
    {
        DB::transaction(function () {
            RelatedTask::query()->delete();
            DueDateHistory::query()->delete();
            OrderEvent::query()->delete();
            Order::query()->delete();
        });

        $this->info('All demo orders and related tasks cleared successfully.');

        return Command::SUCCESS;
    }
}
