@php($events = $this->events)

@if($events->isEmpty())
    <p class="text-body text-ink-3">{{ __('No activity yet.') }}</p>
@else
    <ol class="space-y-3" data-timeline>
        @foreach($events as $event)
            @php($note = \App\Livewire\Deliverables\TimelineDescriber::note($event))
            <li wire:key="event-{{ $event->id }}" class="flex gap-3">
                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-ink-3" aria-hidden="true"></span>
                <div class="min-w-0">
                    <p class="text-body text-ink">{{ \App\Livewire\Deliverables\TimelineDescriber::describe($event) }}</p>
                    @if($note)
                        <p class="text-body text-ink-2">{{ $note }}</p>
                    @endif
                    <p class="text-meta text-ink-3">{{ $event->actor === 'system' ? __('System') : $event->actor }} · {{ $event->created_at?->translatedFormat('j M Y, H:i') }}</p>
                </div>
            </li>
        @endforeach
    </ol>
@endif
