<div class="min-h-screen flex flex-col items-center justify-between p-6 max-w-md mx-auto w-full">
    {{-- Header Logo --}}
    <div class="w-full flex justify-center pt-8">
        <img src="{{ asset('images/logo-kudos.svg') }}" alt="Kudos Print Media" class="h-16 w-auto">
    </div>

    {{-- Not Found Card --}}
    <div class="w-full bg-white rounded-2xl p-7 shadow-xs border border-zinc-200/80 text-center space-y-4 my-auto">
        <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center mx-auto border border-amber-200/60 shadow-2xs">
            <x-lucide-qr-code class="w-7 h-7" />
        </div>

        <div class="space-y-1.5">
            <h1 class="text-base font-bold text-zinc-900 tracking-tight">
                {{ __('Enlace o Código QR No Válido') }}
            </h1>
            <p class="text-xs text-zinc-500 leading-relaxed">
                {{ __('El código QR o enlace al portal no corresponde a un cliente activo o ha sido modificado.') }}
            </p>
        </div>

        <div class="pt-2 border-t border-zinc-100 text-xs text-zinc-600 space-y-2">
            <p class="font-medium text-zinc-700">
                {{ __('¿Necesitas ayuda con tus pedidos?') }}
            </p>
            <p class="text-[11px] text-zinc-500">
                {{ __('Por favor comunícate directamente con tu asesor de Kudos Print Media para recibir un nuevo enlace de acceso.') }}
            </p>
        </div>
    </div>

    {{-- Footer --}}
    <div class="w-full pb-6 text-center">
        <p class="text-[11px] text-zinc-400 font-medium">
            Kudos Print Media &bull; Portal de Clientes
        </p>
    </div>
</div>
