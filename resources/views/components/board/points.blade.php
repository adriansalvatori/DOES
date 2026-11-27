{{--
    Fibonacci story points of a deliverable, as a small neutral pill. Nothing when the work has no estimate (backlog).
    Usage: <x-board.points :points="$deliverable->points" />; add show-zero for a task, where 0 means coordination.
--}}
@props(['points' => null, 'showZero' => false])

@if($points !== null && ($points > 0 || $showZero))
    <span {{ $attributes->class('inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full border border-line text-meta font-semibold tabular-nums text-ink-2 shrink-0') }}
          title="{{ trans_choice(':count point|:count points', $points, ['count' => $points]) }}">{{ $points }}</span>
@endif
