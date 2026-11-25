<div class="h-screen h-[100dvh] max-h-[100dvh] w-full flex flex-col justify-center items-center bg-canvas overflow-hidden relative">

    <!-- Centered Card Container (Overlaps Footer, Middle of the Screen) -->
    <main class="relative z-10 w-full max-w-md mx-auto px-4 sm:px-6 space-y-5 my-auto">
        
        <!-- Brand / Header -->
        <div class="text-center space-y-2.5">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-card bg-gold text-white mb-0.5">
                <img src="{{ asset('images/kudos-hand-white.svg') }}" alt="{{ config('app.name') }}" class="w-8 h-8 object-contain">
            </div>
            <h2 class="text-title font-semibold text-ink">
                {{ config('app.name') }}
            </h2>
            <p class="text-body text-ink-3 max-w-xs mx-auto">
                {{ __('Sign in to manage orders and workflows.') }}
            </p>
        </div>

        <!-- Login Form Card -->
        <div class="bg-surface border border-line shadow-panel rounded-panel p-6 sm:p-7 space-y-5">
            
            <form wire:submit="login" class="space-y-4" x-data="{ showPassword: false }">
                
                <!-- Email Input -->
                <div class="">
                    <label for="email" class="ui-label">
                        {{ __('Email') }}
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-3">
                            <x-lucide-mail class="w-4 h-4" />
                        </div>
                        <input 
                            wire:model="email" 
                            type="email" 
                            id="email" 
                            required 
                            autofocus 
                            placeholder="{{ __('your.email@kudos.com') }}"
                            class="ui-input pl-9 pr-3" />
                    </div>
                    @error('email')
                        <p class="text-meta text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Input with Show/Hide Toggle -->
                <div class="">
                    <label for="password" class="ui-label">
                        {{ __('Password') }}
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-ink-3">
                            <x-lucide-lock class="w-4 h-4" />
                        </div>
                        <input 
                            wire:model="password" 
                            :type="showPassword ? 'text' : 'password'" 
                            id="password" 
                            required 
                            placeholder="••••••••"
                            class="ui-input pl-9 pr-10" />
                        
                        <!-- Toggle Button Inside Input -->
                        <button 
                            type="button" 
                            @click="showPassword = !showPassword"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-ink-3 hover:text-ink transition cursor-pointer select-none focus:outline-none"
                            :title="showPassword ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                            :aria-label="showPassword ? '{{ __('Hide password') }}' : '{{ __('Show password') }}'"
                            :aria-pressed="showPassword.toString()">
                            <x-lucide-eye x-show="!showPassword" class="w-4 h-4" />
                            <x-lucide-eye-off x-show="showPassword" class="w-4 h-4" style="display: none;" />
                        </button>
                    </div>
                    @error('password')
                        <p class="text-meta text-danger mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="flex items-center pt-1">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input
                            wire:model="remember"
                            type="checkbox"
                            class="w-4 h-4 rounded border-line-strong text-ink focus:ring-ink/20 transition cursor-pointer">
                        <span class="text-body text-ink-2 select-none">{{ __('Remember me') }}</span>
                    </label>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    wire:loading.attr="disabled"
                    class="ui-btn ui-btn-primary w-full h-9 disabled:opacity-50">
                    <x-lucide-log-in class="w-4 h-4" wire:loading.remove />
                    <x-lucide-loader-2 class="w-4 h-4 animate-spin" wire:loading />
                    <span>{{ __('Sign in') }}</span>
                </button>
            </form>

            <!-- Quick Demo Accounts (Helper for quick testing) -->
            <div class="border-t border-line pt-4 space-y-2">
                <div class="flex items-center justify-between">
                    <p class="text-meta font-medium text-ink-3">
                        {{ __('Quick demo accounts') }}
                    </p>
                    @if($isDemo)
                        <x-ui.tag tone="neutral">{{ __('Demo database active') }}</x-ui.tag>
                    @endif
                </div>
                <div class="grid grid-cols-2 gap-2">
                    @foreach(['admin' => __('Admin'), 'manager' => __('Manager'), 'designer' => __('Designer'), 'comercial' => __('Sales')] as $demoRole => $demoLabel)
                        <a href="{{ route('demo.direct-login', ['role' => $demoRole]) }}" class="ui-btn ui-btn-secondary justify-center">{{ $demoLabel }}</a>
                    @endforeach
                </div>
            </div>

        </div>

    </main>

    <!-- Full-Width Footer Anchored at Bottom (Overlapped by Login Card) -->
    <footer class="absolute bottom-0 inset-x-0 w-full z-0 pointer-events-none select-none flex flex-col items-center">
        <div class="w-full overflow-hidden leading-none">
            <img src="{{ asset('images/footer-decor.svg') }}" alt="Kudos Decor" class="w-full h-auto block">
        </div>
        <div class="w-full py-2.5 text-center bg-transparent pointer-events-auto">
            <p class="text-meta text-ink-3 px-4">
                {{ config('app.name') }} &copy; {{ date('Y') }}
            </p>
        </div>
    </footer>

</div>
