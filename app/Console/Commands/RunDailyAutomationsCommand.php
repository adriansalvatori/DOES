<?php

namespace App\Console\Commands;

use App\Services\AutomationEngine;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('orders:run-daily-automations')]
#[Description('Evaluates client follow-ups, overdue delay tasks, and order status transitions')]
class RunDailyAutomationsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AutomationEngine $automationEngine)
    {
        $automationEngine->runDailyAutomations();
        $this->info('Daily order automations executed successfully.');

        return Command::SUCCESS;
    }
}
