<?php

namespace App\Http\Controllers;

use App\Application\Reporting\ReportPeriod;
use App\Application\Reporting\TeamReportPdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Downloads the weekly or biweekly team report as a PDF. Designers do not see reports, like Analytics.
 */
class TeamReportController extends Controller
{
    public function __invoke(Request $request, string $kind, TeamReportPdf $pdf): Response
    {
        $user = $request->user();
        abort_unless($user !== null && ! $user->isDesigner(), 403);

        $validated = $request->validate(['start' => ['nullable', 'date_format:Y-m-d']]);
        $period = isset($validated['start'])
            ? ReportPeriod::containing($kind, CarbonImmutable::parse($validated['start']))
            : ReportPeriod::lastCompleted($kind, now());

        return response($pdf->render($period), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$period->filename().'"',
        ]);
    }
}
