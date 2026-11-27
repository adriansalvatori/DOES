{{--
    Weekly / biweekly team report, rendered to PDF by dompdf. Tables and inline CSS only: dompdf has no flex or grid.
    Data: App\Application\Reporting\TeamReport.
--}}
@php
    /** @var \App\Application\Reporting\TeamReport $report */
    $period = $report->period;
    $team = $report->team;
    $biweekly = $period->weeks() > 1;
    $delta = $team['points_done'] - $team['previous_points_done'];
    $maxMix = max(array_map(fn ($row) => $row['points'], $report->priorityMix) ?: [0]) ?: 1;
    $dash = '—';
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ $period->title() }} · {{ $period->label() }}</title>
    <style>
        @page { margin: 28px 32px 36px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5px; color: #1c1917; }
        h1 { font-size: 18px; margin: 0; }
        h2 { font-size: 11.5px; margin: 18px 0 6px; }
        p { margin: 0; }
        .muted { color: #78716c; }
        .brand { background: #1c1917; color: #fff; padding: 12px 14px; }
        .brand .muted { color: #d6d3d1; }
        .kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 12px -6px 0; }
        .kpi { border: 1px solid #e7e5e4; border-radius: 6px; padding: 8px 10px; width: 25%; vertical-align: top; }
        .kpi .value { font-size: 17px; font-weight: bold; margin-top: 2px; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th { text-align: left; font-weight: bold; color: #57534e; border-bottom: 1px solid #d6d3d1; padding: 4px 5px; font-size: 8.5px; }
        table.grid td { border-bottom: 1px solid #f0efed; padding: 4px 5px; vertical-align: top; }
        table.grid .num { text-align: right; }
        .tag { background: #1c1917; color: #fff; font-weight: bold; padding: 1px 4px; border-radius: 3px; font-size: 8px; }
        .bar { background: #f0efed; height: 7px; width: 100%; }
        .bar span { display: block; background: #57534e; height: 7px; }
        .up { color: #166534; }
        .down { color: #b91c1c; }
        .notes { margin-top: 18px; color: #78716c; font-size: 8px; line-height: 1.45; }
    </style>
</head>
<body>
    <div class="brand">
        <p class="muted">{{ __('Kudos DOES') }}</p>
        <h1>{{ $period->title() }}</h1>
        <p>{{ $period->label() }} <span class="muted">· {{ __('Generated :date', ['date' => $report->generatedAt->translatedFormat('j M Y, H:i')]) }}</span></p>
    </div>

    <table class="kpis">
        <tr>
            <td class="kpi">
                <p class="muted">{{ __('Points done') }}</p>
                <p class="value">{{ $team['points_done'] }}</p>
                <p class="{{ $delta >= 0 ? 'up' : 'down' }}">{{ $delta >= 0 ? '+' : '' }}{{ $delta }} <span class="muted">{{ __('vs previous :points', ['points' => $team['previous_points_done']]) }} · {{ __(':count tasks done', ['count' => $team['tasks_done']]) }}</span></p>
            </td>
            <td class="kpi">
                <p class="muted">{{ __('Points approved') }}</p>
                <p class="value">{{ $team['approved_points'] }}</p>
                <p class="muted">{{ trans_choice('{1} :count deliverable|[0,*] :count deliverables', $team['approved_count'], ['count' => $team['approved_count']]) }} · {{ __('vs previous :points', ['points' => $team['previous_approved_points']]) }}</p>
            </td>
            <td class="kpi">
                <p class="muted">{{ __('Proofs sent') }}</p>
                <p class="value">{{ $team['proofs'] }}</p>
                <p class="muted">{{ __('Revisions: :count (:rework rework)', ['count' => $team['revisions'], 'rework' => $team['rework_pct'] === null ? $dash : $team['rework_pct'].'%']) }}</p>
            </td>
            <td class="kpi">
                <p class="muted">{{ __('New work in') }}</p>
                <p class="value">{{ $team['new_points'] }}</p>
                <p class="muted">{{ trans_choice('{1} :count deliverable|[0,*] :count deliverables', $team['new_count'], ['count' => $team['new_count']]) }}</p>
            </td>
        </tr>
    </table>

    <h2>{{ __('Designers in this period') }}</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>{{ __('Designer') }}</th>
                <th class="num">{{ __('Points done') }}</th>
                @if($biweekly)
                    @foreach($period->weekStarts() as $week)
                        <th class="num">{{ __('Week of :date', ['date' => $week->translatedFormat('j M')]) }}</th>
                    @endforeach
                @endif
                <th class="num">{{ __('Previous') }}</th>
                <th class="num">{{ __('Tasks done') }}</th>
                <th class="num">{{ __('Points approved') }}</th>
                <th class="num">{{ __('Approved') }}</th>
                <th class="num">{{ __('To Camila') }}</th>
                <th class="num">{{ __('To client') }}</th>
                <th class="num">{{ __('Revisions') }}</th>
                <th class="num">{{ __('Rework') }}</th>
                <th class="num">{{ __('Points closed') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report->designers as $row)
                <tr>
                    <td>{{ $row['designer']->name }}</td>
                    <td class="num"><strong>{{ $row['points_done'] }}</strong></td>
                    @if($biweekly)
                        @foreach($row['weekly_points_done'] as $weekPoints)
                            <td class="num">{{ $weekPoints }}</td>
                        @endforeach
                    @endif
                    <td class="num muted">{{ $row['previous_points_done'] }}</td>
                    <td class="num">{{ $row['tasks_done'] }}</td>
                    <td class="num">{{ $row['approved_points'] }}</td>
                    <td class="num">{{ $row['approved_count'] }}</td>
                    <td class="num">{{ $row['proofs_to_reviewer'] }}</td>
                    <td class="num">{{ $row['proofs_to_client'] }}</td>
                    <td class="num">{{ $row['revisions'] }}</td>
                    <td class="num">{{ $row['rework_pct'] === null ? $dash : $row['rework_pct'].'%' }}</td>
                    <td class="num">{{ $row['closed_points'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('Open design work now') }}</h2>
    <table class="grid">
        <thead>
            <tr>
                <th>{{ __('Designer') }}</th>
                <th class="num">{{ __('Points to do') }}</th>
                <th class="num">{{ __('Deliverables') }}</th>
                <th class="num">{{ __('Overdue') }}</th>
                <th class="num">{{ __('High or highest priority') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report->designers as $row)
                <tr>
                    <td>{{ $row['designer']->name }}</td>
                    <td class="num"><strong>{{ $row['open_points'] }}</strong></td>
                    <td class="num">{{ $row['open_count'] }}</td>
                    <td class="num">{{ $row['overdue_count'] }}</td>
                    <td class="num">{{ $row['high_priority_open'] }}</td>
                </tr>
            @endforeach
            <tr>
                <td><strong>{{ __('Team (all open design work)') }}</strong></td>
                <td class="num"><strong>{{ $team['open_points'] }}</strong></td>
                <td class="num">{{ $team['open_count'] }}</td>
                <td class="num">{{ $team['overdue_count'] }}</td>
                <td class="num"></td>
            </tr>
        </tbody>
    </table>

    <h2>{{ __('Open work by priority') }}</h2>
    <table class="grid">
        <thead>
            <tr>
                <th style="width: 18%">{{ __('Priority') }}</th>
                <th class="num" style="width: 12%">{{ __('Deliverables') }}</th>
                <th class="num" style="width: 10%">{{ __('Points to do') }}</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            @foreach($report->priorityMix as $row)
                <tr>
                    <td>{{ $row['priority']->label() }}</td>
                    <td class="num">{{ $row['count'] }}</td>
                    <td class="num">{{ $row['points'] }}</td>
                    <td><div class="bar"><span style="width: {{ $row['points'] > 0 ? max(1, round($row['points'] / $maxMix * 100)) : 0 }}%"></span></div></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>{{ __('Design work that needs attention') }}</h2>
    @if($report->attention->isEmpty())
        <p class="muted">{{ __('No design work is overdue or high priority.') }}</p>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th>{{ __('WO') }}</th>
                    <th>{{ __('Company') }}</th>
                    <th>{{ __('Deliverable') }}</th>
                    <th>{{ __('Designer') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Priority') }}</th>
                    <th class="num">{{ __('Points') }}</th>
                    <th class="num">{{ __('Due') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report->attention as $deliverable)
                    <tr>
                        <td>@if($deliverable->workOrder?->number)<span class="tag">{{ $deliverable->workOrder->number }}</span>@endif</td>
                        <td>{{ \App\Support\TitleCase::from($deliverable->workOrder?->displayName() ?: $deliverable->company_name) }}</td>
                        <td>{{ $deliverable->task_name }}</td>
                        <td>{{ $deliverable->assignee?->name ?? $dash }}</td>
                        <td>{{ $deliverable->status->label() }}</td>
                        <td>{{ ($deliverable->priority ?? \App\Domain\Delivery\Enums\Priority::MEDIUM)->label() }}</td>
                        <td class="num">{{ $deliverable->points ?? $dash }}</td>
                        <td class="num">{{ $deliverable->due_target?->translatedFormat('j M') ?? $dash }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="notes">
        <p>{{ __('Tasks carry Fibonacci points (0 for follow-ups and emails, 1 for production prep, the size of the job for the first proposal, less for each revision) and a deliverable\'s points are the sum of its tasks. Points can be changed on each task. Work is credited to the deliverable\'s designer.') }}</p>
        <p>{{ __('Points done: points of the tasks completed in the period. Points approved: deliverables the client approved in the period. Proofs: sends to Camila and to the client. Revisions: proofs of work already sent to the same reviewer before; rework = revisions ÷ proofs. Points closed: deliverables completed. Open work is a snapshot at the time of the report; work on hold or in the backlog is left out.') }}</p>
    </div>
</body>
</html>
