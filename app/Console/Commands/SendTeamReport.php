<?php

namespace App\Console\Commands;

use App\Application\Reporting\ReportPeriod;
use App\Application\Reporting\ReportRecipients;
use App\Application\Reporting\TeamReportPdf;
use App\Mail\TeamReportMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTeamReport extends Command
{
    /**
     * @var string
     */
    protected $signature = 'reports:send
        {kind : weekly or biweekly}
        {--force : Send the biweekly report even when no biweekly window starts today}';

    /**
     * @var string
     */
    protected $description = 'Email the PDF team report of the last finished week or two weeks';

    public function handle(TeamReportPdf $pdf, ReportRecipients $recipients): int
    {
        $kind = (string) $this->argument('kind');

        if (! in_array($kind, ReportPeriod::KINDS, true)) {
            $this->components->error('The kind must be weekly or biweekly.');

            return self::INVALID;
        }

        if ($kind === ReportPeriod::BIWEEKLY && ! $this->option('force') && ! ReportPeriod::startsBiweeklyWindow(now())) {
            $this->components->info('No biweekly window starts today; nothing sent.');

            return self::SUCCESS;
        }

        $emails = $recipients->emails();

        if ($emails === []) {
            $this->components->warn('No recipients: save them on the Analytics page or activate an admin.');

            return self::SUCCESS;
        }

        $period = ReportPeriod::lastCompleted($kind, now());
        Mail::to($emails)->send(new TeamReportMail($period, $pdf->render($period)));

        $this->components->info(sprintf('%s (%s) sent to %s.', $period->title(), $period->label(), implode(', ', $emails)));

        return self::SUCCESS;
    }
}
