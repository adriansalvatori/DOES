@php
    $comments = $this->comments;
    $files = $this->files;
@endphp

<div class="space-y-5">
    <section aria-label="{{ __('Comments') }}">
        <h3 class="ui-section-title">{{ __('Comments') }}</h3>
        @forelse($comments as $comment)
            <article wire:key="comment-{{ $comment->id }}" class="mt-2 rounded-card border border-line px-3 py-2">
                <div class="flex items-center justify-between gap-2 text-meta text-ink-3">
                    <span class="font-medium text-ink-2">{{ $comment->display_author_name }}</span>
                    <span>{{ $comment->created_at?->translatedFormat('j M Y, H:i') }}</span>
                </div>
                <p class="mt-1 whitespace-pre-line text-body text-ink">{{ \Illuminate\Support\Str::limit($comment->body, 1200) }}</p>
            </article>
        @empty
            <p class="mt-1 text-body text-ink-3">{{ __('No comments yet.') }}</p>
        @endforelse
    </section>

    <section aria-label="{{ __('Files') }}">
        <h3 class="ui-section-title">{{ __('Files') }}</h3>
        @forelse($files as $file)
            @php($fileUrl = $file->isLink() ? $file->external_url : $file->drive_web_url)
            <div wire:key="file-{{ $file->id }}" class="mt-2 flex items-center justify-between gap-3 rounded-card border border-line px-3 py-2 text-body">
                <span class="min-w-0 truncate text-ink">{{ $file->name }}</span>
                @if($fileUrl)
                    <a href="{{ $fileUrl }}" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-0.5 text-ink-2 underline underline-offset-2">{{ __('Open') }} <x-lucide-arrow-up-right class="h-3.5 w-3.5" /></a>
                @endif
            </div>
        @empty
            <p class="mt-1 text-body text-ink-3">{{ __('No files yet.') }}</p>
        @endforelse
        @if($deliverable->files_url)
            <a href="{{ $deliverable->files_url }}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-0.5 text-body text-ink-2 underline underline-offset-2">{{ __('Open files') }} <x-lucide-arrow-up-right class="h-3.5 w-3.5" /></a>
        @endif
    </section>

    @if($comments->isEmpty() && $deliverable->trello_card_url)
        <p class="text-body text-ink-3">
            {{ __('Older comments and attachments live on the Trello card.') }}
            <a href="{{ $deliverable->trello_card_url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-0.5 text-ink-2 underline underline-offset-2">{{ __('Open Trello card') }} <x-lucide-arrow-up-right class="h-3.5 w-3.5" /></a>
        </p>
    @endif
</div>
