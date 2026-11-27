<?php

namespace App\Application\Reporting;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the team report of a period as a PDF.
 */
class TeamReportPdf
{
    public function __construct(private TeamReportQuery $query) {}

    public function render(ReportPeriod $period): string
    {
        $report = $this->query->run($period);

        return Pdf::loadView('reports.team-report', ['report' => $report])
            ->setPaper('a4', 'landscape')
            ->output();
    }
}
