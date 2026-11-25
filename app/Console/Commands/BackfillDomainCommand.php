<?php

namespace App\Console\Commands;

use App\Infrastructure\Backfill\LegacyBackfill;
use Illuminate\Console\Command;

class BackfillDomainCommand extends Command
{
    protected $signature = 'domain:backfill
        {--dry-run : Run every step inside a transaction and roll it back}
        {--report= : Write the full JSON report to this path}';

    protected $description = 'Copy the legacy order model into work orders, deliverables, blocks, holds, approvals and alerts';

    public function handle(LegacyBackfill $backfill): int
    {
        $report = $backfill->run((bool) $this->option('dry-run'));

        $path = $this->option('report');
        if (is_string($path) && $path !== '') {
            file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->components->info("Report written to {$path}");
        }

        $this->components->twoColumnDetail('Work orders created', (string) ($report['work_orders']['created'] ?? 0));
        $this->components->twoColumnDetail('Multi-card work orders', (string) ($report['work_orders']['multi_card_groups'] ?? 0));
        $this->components->twoColumnDetail('Deliverables mapped', (string) ($report['deliverables']['mapped'] ?? 0));
        $this->components->twoColumnDetail('Blocks / holds', ($report['deliverables']['blocks_created'] ?? 0).' / '.($report['deliverables']['holds_created'] ?? 0));
        $this->components->twoColumnDetail('Approvals', (string) ($report['approvals']['created'] ?? 0));
        $this->components->twoColumnDetail('Alerts', (string) ($report['tasks']['alerts_created'] ?? 0));
        $this->components->twoColumnDetail('Unmapped rows', (string) count($report['unmapped']));

        if ($this->option('dry-run')) {
            $this->components->warn('Dry run: nothing was saved.');
        }

        return self::SUCCESS;
    }
}
