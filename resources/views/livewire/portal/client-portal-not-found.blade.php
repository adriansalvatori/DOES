<div class="min-h-screen flex flex-col items-center justify-between p-6 max-w-md mx-auto w-full">
    {{-- Header Logo --}}
    <div class="w-full flex justify-center pt-8">
        <img src="{{ asset('images/logo-kudos.svg') }}" alt="Kudos Print Media" class="h-16 w-auto">
    </div>

    {{-- Not Found Card --}}
    <div class="w-full bg-white rounded-panel p-7 border border-line text-center space-y-4 my-auto">
        <div class="w-12 h-12 rounded-card bg-sunken text-ink-3 flex items-center justify-center mx-auto">
            <x-lucide-qr-code class="w-6 h-6" />
        </div>

        <div class="space-y-1.5">
            <h1 class="text-section font-semibold text-ink">
                {{ __('Invalid link or QR code') }}
            </h1>
            <p class="text-body text-ink-3">
                {{ __('This QR code or portal link doesn\'t match an active client, or it has been changed.') }}
            </p>
        </div>

        <div class="pt-2 border-t border-line text-body text-ink-2 space-y-2">
            <p class="font-medium text-ink">
                {{ __('Need help with your orders?') }}
            </p>
            <p class="text-meta text-ink-3">
                {{ __('Please contact your Kudos Print Media advisor to get a new access link.') }}
            </p>
        </div>
    </div>

    {{-- Footer --}}
    <div class="w-full pb-6 text-center">
        <p class="text-meta text-ink-3">
            Kudos Print Media &bull; {{ __('Client portal') }}
        </p>
    </div>
</div>
