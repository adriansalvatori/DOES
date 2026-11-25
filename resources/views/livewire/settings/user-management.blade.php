<div class="h-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 w-full max-w-5xl pb-10">

    <x-ui.page-header :title="__('Users and roles')">
        <x-slot:actions>
            <flux:button wire:click="openCreateModal" type="button" variant="primary" size="sm" icon="user-plus">{{ __('Create user') }}</flux:button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        {{-- CS WhatsApp Phone Configuration (admin only) --}}
        <div class="bg-surface border border-line rounded-card p-4 flex flex-col gap-3">
            <div>
                <h2 class="text-row font-semibold text-ink">{{ __('Customer service WhatsApp') }}</h2>
                <p class="text-meta text-ink-3 mt-0.5">
                    {{ __('This number is used in the client portal for questions to the customer service team.') }}
                </p>
            </div>

            <form wire:submit="saveCsWhatsapp" class="flex items-center gap-2 w-full">
                <div class="relative flex-1">
                    <x-lucide-phone class="w-3.5 h-3.5 text-ink-3 absolute left-3 top-1/2 -translate-y-1/2" />
                    <input
                        type="text"
                        wire:model="cs_whatsapp_phone"
                        placeholder="+16783580594"
                        class="ui-input pl-8 pr-3 font-mono"
                    />
                </div>
                <flux:button type="submit" variant="primary" size="sm" class="shrink-0">{{ __('Save') }}</flux:button>
            </form>

            @if(session('success_cs'))
                <p class="text-meta text-ok flex items-center gap-1.5"><x-lucide-check class="w-3.5 h-3.5" /> {{ session('success_cs') }}</p>
            @endif
            @error('cs_whatsapp_phone')
                <p class="text-meta text-danger">{{ $message }}</p>
            @enderror
        </div>

        {{-- Approval Manager Configuration (admin only) --}}
        <div class="bg-surface border border-line rounded-card p-4 flex flex-col gap-3">
            <div>
                <h2 class="text-row font-semibold text-ink">{{ __('Approvals Manager') }}</h2>
                <p class="text-meta text-ink-3 mt-0.5">
                    {{ __('Select the user who acts as Manager for order approvals.') }}
                </p>
            </div>

            <form wire:submit="saveManagerSettings" class="space-y-2">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div>
                        <label class="block text-meta text-ink-2 mb-1">{{ __('Manager User') }} <span class="text-danger">*</span></label>
                        <select
                            wire:model.live="manager_id"
                            class="ui-select">
                            <option value="">-- {{ __('Select user') }} --</option>
                            @foreach($allUsers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->role->label() }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-meta text-ink-2 mb-1">{{ __('Display Name in Approvals') }} <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            wire:model="manager_name"
                            placeholder="{{ __('e.g. Gabriela') }}"
                            class="ui-input"
                        />
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 pt-1">
                    <div class="text-meta text-ink-3 truncate">
                        {{ __('Option:') }} <span class="font-medium text-ink-2">"{{ __('Approved by :name', ['name' => $manager_name ?: 'Manager']) }}"</span>
                    </div>
                    <flux:button type="submit" variant="primary" size="sm" class="shrink-0">{{ __('Save') }}</flux:button>
                </div>
            </form>

            @if(session('success_manager'))
                <p class="text-meta text-ok flex items-center gap-1.5"><x-lucide-check class="w-3.5 h-3.5" /> {{ session('success_manager') }}</p>
            @endif
            @error('manager_id')
                <p class="text-meta text-danger">{{ $message }}</p>
            @enderror
            @error('manager_name')
                <p class="text-meta text-danger">{{ $message }}</p>
            @enderror
        </div>
    </div>

    @if(session('success_user'))
        <div class="px-3.5 py-2.5 bg-ok-soft text-ok border border-line rounded-card text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('success_user') }}</span>
        </div>
    @endif

    <div class="flex items-center gap-3">
        <div class="relative flex-1 max-w-xs">
            <x-lucide-search class="w-4 h-4 text-ink-3 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="{{ __('Search by name or email…') }}"
                class="ui-input pl-9 pr-3" />
        </div>
    </div>

    <div class="bg-surface border border-line rounded-card overflow-hidden">
        <table class="w-full text-left text-body text-ink-2">
            <thead class="border-b border-line text-meta font-medium text-ink-3  r">
                <tr>
                    <th class="py-2.5 px-4">{{ __('User') }}</th>
                    <th class="py-2.5 px-4">{{ __('Role') }}</th>
                    <th class="py-2.5 px-4">{{ __('Linked designer') }}</th>
                    <th class="py-2.5 px-4">{{ __('Status') }}</th>
                    <th class="py-2.5 px-4 text-right">{{ __('Actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse($users as $u)
                    <tr class="hover:bg-sunken transition-colors">
                        <td class="py-3 px-4">
                            <div class="min-w-0">
                                <p class="font-medium text-ink truncate">{{ $u->name }}</p>
                                <div class="flex items-center gap-2 text-meta text-ink-3 truncate flex-wrap">
                                    <span>{{ $u->email }}</span>
                                    @if($u->phone || $u->designer?->phone)
                                        <span class="font-mono">{{ $u->phone ?: $u->designer?->phone }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            <div class="flex flex-wrap items-center gap-1">
                                <x-ui.tag tone="neutral">{{ $u->role->label() }}</x-ui.tag>
                                @if($u->is_reviewer)
                                    <x-ui.tag tone="neutral">{{ __('Reviewer') }}</x-ui.tag>
                                @endif
                                @if($u->is_lead)
                                    <x-ui.tag tone="neutral">{{ __('Design lead') }}</x-ui.tag>
                                @endif
                                <span class="text-meta text-ink-3" title="{{ __('WIP limit') }}">{{ __('WIP :count', ['count' => $u->wip_limit]) }}</span>
                            </div>
                        </td>
                        <td class="py-3 px-4">
                            @if($u->designer)
                                <span class="text-ink">{{ $u->designer->name }}</span>
                            @else
                                <span class="text-ink-3">{{ __('Not linked') }}</span>
                            @endif
                        </td>
                        <td class="py-3 px-4">
                            <button
                                wire:click="toggleActive({{ $u->id }})"
                                type="button"
                                class="cursor-pointer">
                                @if($u->active)
                                    <x-ui.tag tone="ok">{{ __('Active') }}</x-ui.tag>
                                @else
                                    <x-ui.tag tone="neutral">{{ __('Inactive') }}</x-ui.tag>
                                @endif
                            </button>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <button
                                wire:click="openEditModal({{ $u->id }})"
                                type="button"
                                class="p-1.5 rounded-control text-ink-3 hover:text-ink hover:bg-sunken transition cursor-pointer"
                                title="{{ __('Edit') }}">
                                <x-lucide-pencil class="w-4 h-4" />
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-ink-3">{{ __('No users found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="p-3 border-t border-line">
            {{ $users->links() }}
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 bg-ink/40 flex items-center justify-center z-50 p-4">
            <div class="bg-surface rounded-panel shadow-panel border border-line w-full max-w-md p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h3 class="text-section font-semibold text-ink">
                        {{ $editingUserId ? __('Edit user') : __('Create user') }}
                    </h3>
                    <button @click="$wire.showModal = false" class="text-ink-3 hover:text-ink">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <form wire:submit="saveUser" autocomplete="off" class="space-y-3 text-body">

                    <div class="space-y-1">
                        <label class="block text-meta text-ink-2">{{ __('Name') }}</label>
                        <input wire:model="name" type="text" required class="ui-input" />
                        @error('name') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-meta text-ink-2">{{ __('Email') }}</label>
                        <input wire:model="email" type="email" required class="ui-input" />
                        @error('email') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="text-meta text-ink-2 flex items-center justify-between">
                            <span>{{ __('Contact phone / WhatsApp') }}</span>
                            <span class="text-ink-3">{{ __('Used for "Chat with your designer"') }}</span>
                        </label>
                        <input wire:model="phone" type="text" placeholder="+1 (678) 358-0594" class="ui-input font-mono" />
                        @error('phone') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-meta text-ink-2">{{ __('User role') }}</label>
                        <select wire:model="role" class="ui-select">
                            @foreach($roles as $r)
                                <option value="{{ $r->value }}">{{ $r->label() }}</option>
                            @endforeach
                        </select>
                        @error('role') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-1">
                        <label class="block text-meta text-ink-2">{{ __('Link designer (optional)') }}</label>
                        <select wire:model="designer_id" class="ui-select">
                            <option value="">-- {{ __('None') }} --</option>
                            @foreach($designers as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="is_reviewer" type="checkbox" class="rounded border-line-strong text-ink focus:ring-gold" />
                            <span class="text-ink-2">{{ __('Reviewer') }}</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input wire:model="is_lead" type="checkbox" class="rounded border-line-strong text-ink focus:ring-gold" />
                            <span class="text-ink-2">{{ __('Design lead') }}</span>
                        </label>
                    </div>

                    <div class="space-y-1">
                        <label class="block text-meta text-ink-2">{{ __('WIP limit') }}</label>
                        <input wire:model="wip_limit" type="number" min="1" max="50" class="ui-input" />
                        <p class="text-meta text-ink-3">{{ __('Cards this person can have in design at once.') }}</p>
                        @error('wip_limit') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                    </div>

                    @if($editingUserId)
                        <div class="pt-2 border-t border-line">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input wire:model.live="changePassword" type="checkbox" class="rounded border-line-strong text-ink focus:ring-gold" />
                                <span class="text-ink-2">{{ __('Change this user\'s password') }}</span>
                            </label>
                        </div>
                    @endif

                    @if(! $editingUserId || $changePassword)
                        <div class="space-y-1">
                            <label class="block text-meta text-ink-2">
                                {{ $editingUserId ? __('New password (at least 8 characters)') : __('Password (at least 8 characters)') }}
                            </label>
                            <input wire:model="password" type="password" autocomplete="new-password" required class="ui-input" />
                            @error('password') <p class="text-danger text-meta mt-0.5">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="flex items-center gap-2 pt-1">
                        <input wire:model="active" type="checkbox" id="userActive" class="rounded border-line-strong text-ink focus:ring-gold" />
                        <label for="userActive" class="text-ink-2 cursor-pointer">{{ __('Active user') }}</label>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-line">
                        <flux:button type="button" @click="$wire.showModal = false" variant="ghost" size="sm">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                    </div>

                </form>
            </div>
        </div>
    @endif

</div>
