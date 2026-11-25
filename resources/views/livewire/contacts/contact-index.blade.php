<div class="h-full flex flex-col min-h-0">
    <div id="tour-contact-header">
        <x-ui.page-header :title="__('Client directory')" :count="$totalContactsCount">
            <x-slot:actions>
                <button type="button" wire:click="openContactDetail" class="ui-btn ui-btn-primary">
                    <x-lucide-plus />
                    {{ __('New contact') }}
                </button>
            </x-slot:actions>
            <x-slot:toolbar>
                <div class="relative w-72 max-w-full">
                    <x-lucide-search class="pointer-events-none absolute left-2.5 top-2 h-4 w-4 text-ink-3" />
                    <input type="text" wire:model.live.debounce.200ms="search" placeholder="{{ __('Search by name, phone, email or company...') }}" class="ui-input pl-8">
                </div>
                <select wire:model.live="channelFilter" class="ui-select w-44" aria-label="{{ __('Preferred channel') }}">
                    <option value="">{{ __('All channels') }}</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="call">{{ __('Call') }}</option>
                    <option value="email">{{ __('Email') }}</option>
                    <option value="any">{{ __('Any') }}</option>
                </select>
                <select wire:model.live="languageFilter" class="ui-select w-44" aria-label="{{ __('Language') }}">
                    <option value="">{{ __('All languages') }}</option>
                    <option value="es">ES - {{ __('Spanish') }}</option>
                    <option value="en">EN - {{ __('English') }}</option>
                    <option value="both">ES / EN - {{ __('Bilingual') }}</option>
                </select>
                <select wire:model.live="clientFilter" class="ui-select w-52" aria-label="{{ __('Company') }}">
                    <option value="">{{ __('All companies') }}</option>
                    @foreach($allClients as $cl)
                        <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                    @endforeach
                </select>
                @if(!empty($search) || !empty($channelFilter) || !empty($languageFilter) || !empty($clientFilter))
                    <button type="button" wire:click="resetFilters" class="ui-btn ui-btn-ghost ui-btn-sm">{{ __('Clear all filters') }}</button>
                @endif
            </x-slot:toolbar>
        </x-ui.page-header>
    </div>

    <div class="mt-4 flex-1 min-h-0 overflow-auto rounded-card border border-line bg-surface custom-vertical-scrollbar">
        <table class="ui-table min-w-[960px]">
            <thead>
                <tr>
                    <th>{{ __('Contact') }}</th>
                    <th>{{ __('Companies') }}</th>
                    <th>{{ __('Phone') }}</th>
                    <th>{{ __('Email') }}</th>
                    <th>{{ __('Language') }}</th>
                    <th>{{ __('Channel') }}</th>
                    <th class="is-num">{{ __('Active') }}</th>
                    <th class="is-num">{{ __('Backlog') }}</th>
                    <th class="w-10"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($contacts as $contact)
                    @php
                        $langBadge = $contact->language_badge;
                        $whatsappUrl = $contact->whatsapp_url;
                        $channelLabel = match ($contact->preferred_channel) {
                            'whatsapp' => 'WhatsApp',
                            'call' => __('Call'),
                            'email' => 'Email',
                            default => __('Any'),
                        };
                        $contactClients = $contact->clients;
                    @endphp
                    <tr wire:key="contact-row-{{ $contact->id }}" wire:click="openContactDetail({{ $contact->id }})" class="cursor-pointer">
                        <td class="max-w-0 w-[20%]">
                            <div class="min-w-0">
                                <span class="block truncate font-medium text-ink">{{ $contact->name }}</span>
                                @if($contact->role_title)
                                    <span class="block truncate text-meta text-ink-3">{{ $contact->role_title }}</span>
                                @endif
                            </div>
                        </td>
                        <td class="max-w-0 w-[22%]">
                            @if($contactClients->isEmpty())
                                <span class="text-ink-3">—</span>
                            @else
                                <span class="block truncate text-ink-2" title="{{ $contactClients->pluck('name')->join(', ') }}">
                                    {{ $contactClients->first()->name }}@if($contactClients->count() > 1) <span class="text-ink-3">+{{ $contactClients->count() - 1 }}</span>@endif
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if($contact->phone)
                                <span class="text-ink-2">{{ $contact->phone }}</span>
                                @if($whatsappUrl)
                                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" x-on:click.stop="" class="ml-1.5 text-meta text-ink-3 hover:text-ink underline-offset-2 hover:underline" title="{{ __('Open WhatsApp chat') }}">WhatsApp</a>
                                @endif
                            @else
                                <span class="text-ink-3">—</span>
                            @endif
                        </td>
                        <td class="max-w-0 w-[18%]">
                            @if($contact->email)
                                <a href="mailto:{{ $contact->email }}" x-on:click.stop="" class="block truncate text-ink-2 hover:text-ink hover:underline underline-offset-2" title="{{ $contact->email }}">{{ $contact->email }}</a>
                            @else
                                <span class="text-ink-3">—</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-ink-2" title="{{ __($langBadge['label']) }}">{{ $langBadge['code'] }}</td>
                        <td class="whitespace-nowrap text-ink-2">{{ $channelLabel }}</td>
                        <td class="is-num {{ $contact->active_orders_count > 0 ? 'text-ink font-medium' : 'text-ink-3' }}">{{ $contact->active_orders_count }}</td>
                        <td class="is-num text-ink-3">{{ $contact->backlog_orders_count }}</td>
                        <td class="text-right" x-on:click.stop="">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="xs" variant="ghost" icon="ellipsis-horizontal" aria-label="{{ __('Actions') }}" />
                                <flux:menu>
                                    <flux:menu.item
                                        variant="danger"
                                        icon="trash"
                                        wire:click="deleteContact({{ $contact->id }})"
                                        wire:confirm="{{ __('Delete :name from the directory?', ['name' => $contact->name]) }}">{{ __('Delete contact') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="!h-auto py-10">
                            <x-ui.empty icon="users" :title="__('No contacts match the selected filters.')" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
