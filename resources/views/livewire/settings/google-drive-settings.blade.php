<div class="h-full w-full max-w-4xl flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-10">
    <x-ui.page-header :title="__('Google Drive')" :subtitle="__('Google account where DOES stores order files.')" />

    @if(session()->has('success'))
        <div class="bg-ok-soft text-ok border border-line rounded-card px-3.5 py-2.5 text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session()->has('error'))
        <div class="bg-danger-soft text-danger border border-line rounded-card px-3.5 py-2.5 text-body flex items-center gap-2 shrink-0">
            <x-lucide-circle-alert class="w-4 h-4 shrink-0" />
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <section class="ui-card shrink-0">
        <div class="px-4 py-3 border-b border-line flex flex-wrap items-center justify-between gap-3">
            <h2 class="ui-section-title">{{ __('Connection status') }}</h2>
            @if($isConfigured)
                <x-ui.tag tone="ok" icon="check">{{ __('Ready') }}</x-ui.tag>
            @else
                <x-ui.tag tone="warn" icon="circle-alert">{{ __('Incomplete') }}</x-ui.tag>
            @endif
        </div>

        <dl class="divide-y divide-line">
            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <dt class="text-body font-medium text-ink">{{ __('OAuth client') }}</dt>
                    <dd class="text-meta text-ink-3">GOOGLE_DRIVE_CLIENT_ID · GOOGLE_DRIVE_CLIENT_SECRET</dd>
                </div>
                @if($hasOAuthClient)
                    <x-ui.tag tone="ok" icon="check">{{ __('Configured') }}</x-ui.tag>
                @else
                    <x-ui.tag tone="warn">{{ __('Missing in .env') }}</x-ui.tag>
                @endif
            </div>

            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <dt class="text-body font-medium text-ink">{{ __('Root folder') }}</dt>
                    <dd class="text-meta text-ink-3 font-mono truncate">{{ $rootFolderId !== '' ? $rootFolderId : 'GOOGLE_DRIVE_ROOT_FOLDER_ID' }}</dd>
                </div>
                @if($rootFolderId !== '')
                    <x-ui.tag tone="ok" icon="check">{{ __('Configured') }}</x-ui.tag>
                @else
                    <x-ui.tag tone="warn">{{ __('Missing in .env') }}</x-ui.tag>
                @endif
            </div>

            <div class="px-4 py-3 flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <dt class="text-body font-medium text-ink">{{ __('Connected account') }}</dt>
                    <dd class="text-meta text-ink-3 truncate">
                        @if($account)
                            {{ trim($account['name'].' · '.$account['email'], ' ·') }}
                            @if($account['connected_at'])
                                · {{ __('since :date', ['date' => \Illuminate\Support\Carbon::parse($account['connected_at'])->timezone(config('app.timezone'))->translatedFormat('j M Y, H:i')]) }}
                            @endif
                        @else
                            {{ __('None. Connect a Kudos account that is a Content manager of the shared drive.') }}
                        @endif
                    </dd>
                </div>
                @if($account)
                    <x-ui.tag tone="ok" icon="check">{{ __('Connected') }}</x-ui.tag>
                @else
                    <x-ui.tag>{{ __('Not connected') }}</x-ui.tag>
                @endif
            </div>
        </dl>

        <div class="px-4 py-3 border-t border-line flex flex-wrap items-center gap-2">
            @if($hasOAuthClient)
                <a href="{{ route('google-drive.connect') }}" class="ui-btn {{ $account ? 'ui-btn-secondary' : 'ui-btn-primary' }}">
                    <x-lucide-link />
                    <span>{{ $account ? __('Reconnect') : __('Connect Google Drive') }}</span>
                </a>
            @endif

            @if($isConfigured)
                <button type="button" wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection" class="ui-btn ui-btn-secondary">
                    <x-lucide-loader-2 class="animate-spin" wire:loading wire:target="testConnection" />
                    <x-lucide-plug-zap wire:loading.remove wire:target="testConnection" />
                    <span>{{ __('Test connection') }}</span>
                </button>
            @endif

            @if($account)
                <button type="button" wire:click="disconnect" wire:confirm="{{ __('Disconnect Google Drive? No one will be able to view or upload files until it is connected again.') }}" class="ui-btn ui-btn-danger ml-auto">
                    <x-lucide-unlink />
                    <span>{{ __('Disconnect') }}</span>
                </button>
            @endif
        </div>

        @if($testMessage)
            <div class="px-4 pb-3">
                <div class="{{ $testPassed ? 'bg-ok-soft text-ok' : 'bg-danger-soft text-danger' }} rounded-control px-3 py-2 text-body flex items-start gap-2">
                    @if($testPassed)
                        <x-lucide-check class="w-4 h-4 shrink-0 mt-0.5" />
                    @else
                        <x-lucide-circle-alert class="w-4 h-4 shrink-0 mt-0.5" />
                    @endif
                    <span class="min-w-0 break-words">{{ $testMessage }}</span>
                </div>
            </div>
        @endif
    </section>

    <section class="ui-card shrink-0 px-4 py-3 flex flex-col gap-2" x-data="{ copied: false }">
        <h2 class="ui-section-title">{{ __('Authorized redirect URI') }}</h2>
        <p class="text-body text-ink-2">{{ __('Add it to the OAuth client in Google Cloud (APIs & Services → Credentials). Each DOES environment has its own.') }}</p>
        <div class="flex items-center gap-2">
            <code class="flex-1 min-w-0 truncate bg-sunken rounded-control px-2.5 h-8 flex items-center text-meta font-mono text-ink">{{ $redirectUri }}</code>
            <button type="button" class="ui-btn ui-btn-secondary" @click="navigator.clipboard.writeText(@js($redirectUri)); copied = true; setTimeout(() => copied = false, 1500)">
                <x-lucide-copy />
                <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
            </button>
        </div>
    </section>
</div>
