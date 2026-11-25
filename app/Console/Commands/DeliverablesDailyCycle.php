<?php

namespace App\Console\Commands;

use App\Application\Delivery\Actions\RunDailyCycle;
use Illuminate\Console\Command;

class DeliverablesDailyCycle extends Command
{
    /**
     * @var string
     */
    protected $signature = 'deliverables:daily-cycle {--no-notify : Skip the due today and overdue notifications (for a second run in the day)}';

    /**
     * @var string
     */
    protected $description = 'Carry over unfinished work, start planned work, follow up with clients and open delay notices';

    public function handle(RunDailyCycle $cycle): int
    {
        $summary = $cycle(notify: ! $this->option('no-notify'));

        $this->table(['Step', 'Count'], collect($summary)->map(fn (int $count, string $step): array => [str_replace('_', ' ', $step), $count])->values()->all());

        return self::SUCCESS;
    }
}
