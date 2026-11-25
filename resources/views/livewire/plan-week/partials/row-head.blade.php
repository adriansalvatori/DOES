{{-- Designer row header: avatar and the WIP limit. Over the limit is the only colored state. --}}
@php
    $assignee = $row['assignee'];
    $maxUsed = collect($row['cells'])->max('used') ?? 0;
@endphp
<div class="flex items-start gap-2 px-1 pt-1">
    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sunken text-meta font-semibold text-ink-2" aria-hidden="true">{{ $assignee->initials }}</span>
    <div class="min-w-0">
        <p class="truncate text-body font-medium text-ink" title="{{ $assignee->name }}">{{ $assignee->name }}</p>
        <p class="text-meta tabular-nums {{ $maxUsed > $row['limit'] ? 'text-danger' : ($maxUsed >= $row['limit'] ? 'text-warn' : 'text-ink-3') }}" title="{{ __('Busiest day against the WIP limit') }}">{{ $maxUsed }}/{{ $row['limit'] }}</p>
    </div>
</div>
