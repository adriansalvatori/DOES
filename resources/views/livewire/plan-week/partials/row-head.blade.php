{{-- A person's heading: name, the busiest day against their WIP limit (a warning, never a block) and week points. --}}
@php
    $assignee = $row['assignee'];
    $maxUsed = collect($row['cells'])->max('used') ?? 0;
    $points = collect($row['cells'])->sum('points');
    $limit = $row['limit'];
@endphp
<div class="flex items-start gap-2 px-1 pt-1">
    @if($assignee)
        <x-ui.person :person="$assignee" size="sm" />
        <div class="min-w-0">
            <p class="truncate text-body font-medium text-ink" title="{{ $assignee->name }}">{{ $assignee->name }}</p>
            <p class="text-[11px] tabular-nums text-ink-3">
                <span @class(['text-danger font-semibold' => $limit > 0 && $maxUsed > $limit, 'text-warn' => $limit > 0 && $maxUsed === $limit]) title="{{ __('Busiest day against the WIP limit') }}">{{ $maxUsed }}/{{ $limit }}</span>
                · {{ trans_choice('{0} 0 pts|{1} 1 pt|[2,*] :count pts', $points, ['count' => $points]) }}
            </p>
        </div>
    @else
        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-dashed border-line-strong text-ink-3"><x-lucide-user class="h-3.5 w-3.5" /></span>
        <p class="pt-0.5 text-body font-medium text-ink-3">{{ __('Unassigned') }}</p>
    @endif
</div>
