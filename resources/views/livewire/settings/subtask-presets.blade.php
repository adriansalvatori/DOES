<div class="h-full w-full flex flex-col gap-5 min-h-0 overflow-y-auto custom-vertical-scrollbar pr-1 pb-10 max-w-4xl">
    <x-ui.page-header :title="__('Subtask & System Task Management')">
        <x-slot:actions>
            <div class="relative w-56">
                <x-lucide-search class="w-4 h-4 text-ink-3 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                    wire:model.live.debounce.250ms="search"
                    type="text"
                    placeholder="{{ __('Search task or template...') }}"
                    class="ui-input pl-9 pr-3" />
            </div>

            @if($activeTab === 'presets')
                <flux:button wire:click="openCreateModal" variant="primary" size="sm" icon="plus" class="shrink-0">{{ __('New Subtask') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    <div class="flex items-center gap-1 border-b border-line shrink-0 text-body text-ink-3">
        <button wire:click="setActiveTab('presets')"
            class="px-3 py-2 border-b-2 cursor-pointer transition {{ $activeTab === 'presets' ? 'border-ink text-ink font-semibold' : 'border-transparent hover:text-ink' }}">
            {{ __('Daily Subtask Templates') }} ({{ $presets->count() }})
        </button>
        <button wire:click="setActiveTab('system')"
            class="px-3 py-2 border-b-2 cursor-pointer transition {{ $activeTab === 'system' ? 'border-ink text-ink font-semibold' : 'border-transparent hover:text-ink' }}">
            {{ __('System Related Tasks') }} ({{ $systemTaskConfigs->count() }})
        </button>
    </div>

    @if(session()->has('message'))
        <div class="bg-ok-soft text-ok border border-line px-3.5 py-2.5 rounded-card text-body flex items-center gap-2 shrink-0">
            <x-lucide-check class="w-4 h-4 shrink-0" />
            <span>{{ session('message') }}</span>
        </div>
    @endif

    <!-- TAB 1: PRESETS (Plantillas de Subtareas) -->
    @if($activeTab === 'presets')
        <div class="bg-surface border border-line rounded-card overflow-hidden flex flex-col shrink-0">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between gap-3 shrink-0">
                <div>
                    <h2 class="text-row font-semibold text-ink">{{ __('Default Subtasks (Weekly Planner)') }}</h2>
                    <p class="text-meta text-ink-3 mt-0.5">{{ __('Quick options available when clicking "+ Subtask" in daily planner.') }}</p>
                </div>
                <span class="text-meta text-ink-3 shrink-0">{{ $presets->count() }} {{ __('templates') }}</span>
            </div>

            @if($presets->isEmpty())
                <x-ui.empty icon="list-checks" :title="__('No subtasks found matching search.')" />
            @else
                <div 
                    x-data="{
                        draggedId: null,
                        handleDragStart(e, id) {
                            this.draggedId = id;
                            e.dataTransfer.effectAllowed = 'move';
                            e.dataTransfer.setData('text/plain', id);
                        },
                        handleDrop(e, targetId) {
                            e.preventDefault();
                            if (!this.draggedId || this.draggedId === targetId) return;
                            const rows = Array.from($el.querySelectorAll('[data-preset-id]'));
                            const items = rows.map(r => parseInt(r.dataset.presetId));
                            const fromIdx = items.indexOf(this.draggedId);
                            const toIdx = items.indexOf(targetId);
                            if (fromIdx !== -1 && toIdx !== -1) {
                                items.splice(fromIdx, 1);
                                items.splice(toIdx, 0, this.draggedId);
                                $wire.updateOrder(items);
                            }
                            this.draggedId = null;
                        }
                    }"
                    class="divide-y divide-line">
                    @foreach($presets as $index => $preset)
                        <div 
                            wire:key="preset-{{ $preset->id }}"
                            data-preset-id="{{ $preset->id }}"
                            draggable="true"
                            @dragstart="handleDragStart($event, {{ $preset->id }})"
                            @dragover.prevent
                            @drop="handleDrop($event, {{ $preset->id }})"
                            class="px-4 py-3 flex items-center justify-between hover:bg-sunken transition-colors gap-4 group">

                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="cursor-grab active:cursor-grabbing text-ink-3 transition p-1 shrink-0" title="{{ __('Drag to reorder') }}">
                                    <x-lucide-grip-vertical class="w-4 h-4" />
                                </div>

                                @php
                                    $safePresetIcon = (preg_match('/^[a-z0-9\-]+$/i', $preset->emoji ?? '')) ? $preset->emoji : 'tag';
                                @endphp
                                <span class="px-2.5 py-0.5 rounded-control text-body font-medium border shrink-0 inline-flex items-center gap-1.5 bg-surface text-ink border-line">
                                    <x-dynamic-component :component="'lucide-' . $safePresetIcon" class="w-3.5 h-3.5" />
                                    <span>{{ __($preset->title) }}</span>
                                </span>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                @if($preset->category)
                                    <span class="text-meta text-ink-3 hidden md:inline" title="{{ __('When completed today, send the order to: ') . $preset->category->returnActionLabel() }}">{{ $preset->category->shortReturnLabel() }}</span>
                                @endif

                                <x-ui.tag tone="neutral">{{ $preset->is_work_task ? __('Work') : __('Admin') }}</x-ui.tag>

                                <button
                                    wire:click="toggleActive({{ $preset->id }})"
                                    class="px-2 py-1 rounded-control border border-line text-meta transition cursor-pointer hover:bg-sunken {{ $preset->is_active ? 'text-ink' : 'text-ink-3' }}">
                                    {{ $preset->is_active ? __('Active') : __('Inactive') }}
                                </button>

                                <flux:button wire:click="openEditModal({{ $preset->id }})" variant="subtle" size="sm" icon="pencil">{{ __('Edit') }}</flux:button>

                                <button
                                    wire:click="delete({{ $preset->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete subtask template ":title"?', ['title' => $preset->title]) }}"
                                    class="p-1.5 rounded-control text-ink-3 hover:text-danger hover:bg-danger-soft transition cursor-pointer"
                                    title="{{ __('Delete Subtask') }}">
                                    <x-lucide-trash-2 class="w-4 h-4" />
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- TAB 2: SYSTEM RELATED TASKS (Tareas Relacionadas del Sistema con Toggle y Descripción) -->
    @if($activeTab === 'system')
        <div class="bg-surface border border-line rounded-card overflow-hidden flex flex-col shrink-0">
            <div class="px-4 py-3 border-b border-line flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-row font-semibold text-ink">{{ __('System Related Tasks') }}</h2>
                    <p class="text-meta text-ink-3 mt-0.5">{{ __('Predefined system operational actions. Enable or disable their usage and check functionality.') }}</p>
                </div>
                <span class="text-meta text-ink-3 shrink-0">{{ $systemTaskConfigs->count() }} {{ __('tasks') }}</span>
            </div>

            @if($systemTaskConfigs->isEmpty())
                <x-ui.empty icon="cog" :title="__('No system tasks found matching search filter.')" />
            @else
                <div class="divide-y divide-line">
                    @foreach($systemTaskConfigs as $sysTask)
                        <div wire:key="sys-task-{{ $sysTask->id }}" class="px-4 py-3 flex flex-col md:flex-row md:items-center justify-between gap-3 hover:bg-sunken transition-colors">
                            <div class="space-y-1 min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-row font-medium text-ink">{{ __($sysTask->title) }}</h3>
                                    <x-ui.tag tone="neutral">{{ __($sysTask->category) }}</x-ui.tag>
                                    <span class="text-meta text-ink-3 font-mono">{{ $sysTask->task_type }}</span>
                                </div>
                                <p class="text-body text-ink-2">{{ __($sysTask->description) }}</p>
                            </div>

                            <div class="shrink-0 flex items-center gap-3">
                                <span class="text-meta {{ $sysTask->is_active ? 'text-ink' : 'text-ink-3' }}">
                                    {{ $sysTask->is_active ? __('Enabled') : __('Disabled') }}
                                </span>

                                <button
                                    wire:click="toggleSystemTaskActive({{ $sysTask->id }})"
                                    type="button"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-gold {{ $sysTask->is_active ? 'bg-ink' : 'bg-line-strong' }}"
                                    role="switch"
                                    aria-checked="{{ $sysTask->is_active ? 'true' : 'false' }}">
                                    <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $sysTask->is_active ? 'translate-x-5' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    <!-- Create / Edit Subtask Preset Modal -->
    @if($showModal)
        <div class="fixed inset-0 z-[150] bg-ink/40 flex items-center justify-center p-4">
            <div 
                x-data="{
                    initialTitle: null,
                    initialIsWorkTask: null,
                    initialEmoji: null,
                    initialColorTheme: null,
                    initialCategory: null,
                    initialIsActive: null,
                    init() {
                        this.initialTitle = $wire.title || '';
                        this.initialIsWorkTask = Boolean($wire.is_work_task);
                        this.initialEmoji = $wire.emoji || '';
                        this.initialColorTheme = $wire.color_theme || '';
                        this.initialCategory = $wire.category || 'new_design';
                        this.initialIsActive = Boolean($wire.is_active);
                    },
                    isDirty() {
                        if (!$wire.showModal) return false;
                        return ($wire.title || '').trim() !== (this.initialTitle || '').trim() ||
                               Boolean($wire.is_work_task) !== this.initialIsWorkTask ||
                               ($wire.emoji || '') !== this.initialEmoji ||
                               ($wire.color_theme || '') !== this.initialColorTheme ||
                               ($wire.category || '') !== this.initialCategory ||
                               Boolean($wire.is_active) !== this.initialIsActive;
                    }
                }"
                @keydown.window.escape="$wire.closeModal()"
                class="bg-surface rounded-panel border border-line shadow-panel max-w-md w-full p-5 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b border-line pb-3">
                    <h3 class="text-section font-semibold text-ink">{{ $editingId ? __('Edit subtask') : __('New Default Subtask') }}</h3>
                    <button type="button" @click="$wire.closeModal()" class="text-ink-3 hover:text-ink cursor-pointer">
                        <x-lucide-x class="w-4 h-4" />
                    </button>
                </div>

                <!-- Form Fields -->
                <form wire:submit.prevent="save" class="space-y-4">
                    <div>
                        <label class="block text-meta text-ink-2 mb-1">{{ __('Subtask Title') }}</label>
                        <input 
                            wire:model.live.debounce.150ms="title" 
                            type="text" 
                            placeholder="{{ __('Ex: Client revisions, Camila adjustment...') }}" 
                            class="ui-input" />
                        @error('title') <span class="text-meta text-danger mt-0.5 block">{{ $message }}</span> @enderror
                    </div>

                    <!-- Tipo de Subtarea (Impacto en Kanban: Working vs Managing) -->
                    <div class="space-y-1.5">
                        <label class="block text-meta text-ink-2 mb-1">{{ __('Kanban behavior') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <button 
                                type="button" 
                                wire:click="$set('is_work_task', true)"
                                class="px-3 py-1.5 rounded-control border text-body font-medium flex items-center justify-center gap-1.5 transition cursor-pointer {{ $is_work_task ? 'bg-ink text-white border-ink' : 'bg-surface text-ink-2 border-line hover:bg-sunken' }}">
                                                                <span>{{ __('Work') }}</span>
                            </button>
                            <button 
                                type="button" 
                                wire:click="$set('is_work_task', false)"
                                class="px-3 py-1.5 rounded-control border text-body font-medium flex items-center justify-center gap-1.5 transition cursor-pointer {{ !$is_work_task ? 'bg-ink text-white border-ink' : 'bg-surface text-ink-2 border-line hover:bg-sunken' }}">
                                                                <span>{{ __('Admin') }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- When marked as done, send to... -->
                    <div class="space-y-1.5" x-data="{ 
                        open: false, 
                        selected: @entangle('category'),
                        options: {
                            'new_design': { label: '{{ __('Return to previous status') }}', dot: 'bg-ink-3' },
                            'client_adjustments': { label: '{{ __('Sent to Client') }}', dot: 'bg-ink-3' },
                            'camila_adjustments': { label: '{{ __('Sent to Camila') }}', dot: 'bg-ink-3' },
                            'production_adjustments': { label: '{{ __('In Production') }}', dot: 'bg-ink-3' },
                            'management': { label: '{{ __('Do not move order') }}', dot: 'bg-ink-3' }
                        }
                    }">
                        <label class="block text-meta text-ink-2 mb-1">
                            {{ __('When marked as ready, send order to...') }}
                        </label>
                        <div class="relative">
                            <button 
                                type="button" 
                                @click="open = !open" 
                                class="w-full bg-surface border border-line-strong rounded-control px-3 py-1.5 text-body text-ink flex items-center justify-between transition cursor-pointer">
                                <span class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="options[selected]?.dot || 'bg-ink-3'"></span>
                                    <span x-text="options[selected]?.label || '{{ __('Return to previous status') }}'"></span>
                                </span>
                                <x-lucide-chevron-down class="w-3.5 h-3.5 text-ink-3 transition-transform duration-200" ::class="{ 'rotate-180': open }" />
                            </button>
                            <div 
                                x-show="open" 
                                @click.outside="open = false" 
                                x-cloak 
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                class="absolute left-0 right-0 mt-1 z-50 bg-surface border border-line rounded-card shadow-menu p-1.5 space-y-0.5 text-left text-body">
                                <template x-for="(opt, key) in options" :key="key">
                                    <button 
                                        type="button" 
                                        @click="selected = key; open = false" 
                                        class="w-full flex items-center justify-between px-2.5 py-1.5 rounded-control text-body transition cursor-pointer hover:bg-sunken" 
                                        :class="selected === key ? 'font-semibold text-ink bg-sunken' : 'text-ink-2'">
                                        <span class="flex items-center gap-2">
                                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="opt.dot"></span>
                                            <span x-text="opt.label"></span>
                                        </span>
                                        <span x-show="selected === key">
                                            <x-lucide-check class="w-3.5 h-3.5 text-ink" />
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <p class="text-meta text-ink-3">
                            {{ __('When today\'s subtasks are completed, the order moves to this stage automatically.') }}
                        </p>
                    </div>

                    <!-- Lucide Icon Library Grid Picker -->
                    <div class="space-y-1.5">
                        <label class="block text-meta text-ink-2 mb-1">{{ __('Subtask Icon (Icon Library)') }}</label>
                        @php
                            $iconLibrary = [
                                'sparkles' => __('Proposal'),
                                'user-check' => __('Camila'),
                                'message-square' => __('Client'),
                                'ruler' => __('Measurements'),
                                'wrench' => __('Settings'),
                                'palette' => __('Design'),
                                'file-text' => __('Document'),
                                'check-circle-2' => __('Check'),
                                'tag' => __('Label'),
                                'clock' => __('Time'),
                                'send' => __('Shipping'),
                                'alert-triangle' => __('Alert'),
                                'box' => __('Box / POP'),
                                'zap' => __('Urgent'),
                            ];
                        @endphp
                        <div class="grid grid-cols-7 gap-1.5 p-2 bg-sunken rounded-control">
                            @foreach($iconLibrary as $iconKey => $iconLabel)
                                <button 
                                    type="button" 
                                    wire:click="$set('emoji', '{{ $iconKey }}')"
                                    title="{{ $iconLabel }}"
                                    class="w-8 h-8 rounded-control border transition flex items-center justify-center cursor-pointer {{ $emoji === $iconKey ? 'bg-ink text-white border-ink' : 'bg-surface text-ink-2 border-line hover:bg-sunken' }}">
                                    <x-dynamic-component :component="'lucide-' . $iconKey" class="w-4 h-4" />
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Color Theme Picker -->
                    <div class="space-y-2">
                        <label class="block text-meta text-ink-2">{{ __('Color Theme (Badge)') }}</label>
                        <div class="grid grid-cols-4 gap-2">
                            @php
                                $themes = [
                                    'sky' => ['label' => __('Sky'), 'bg' => 'bg-sky-50 text-sky-800 border-sky-200'],
                                    'purple' => ['label' => __('Purple'), 'bg' => 'bg-purple-50 text-purple-800 border-purple-200'],
                                    'emerald' => ['label' => __('Green'), 'bg' => 'bg-emerald-50 text-emerald-800 border-emerald-200'],
                                    'amber' => ['label' => __('Amber'), 'bg' => 'bg-amber-50 text-amber-800 border-amber-200'],
                                    'rose' => ['label' => __('Pink'), 'bg' => 'bg-rose-50 text-rose-800 border-rose-200'],
                                    'violet' => ['label' => __('Violet'), 'bg' => 'bg-violet-50 text-violet-800 border-violet-200'],
                                    'indigo' => ['label' => __('Indigo'), 'bg' => 'bg-indigo-50 text-indigo-800 border-indigo-200'],
                                    'stone' => ['label' => __('Gray'), 'bg' => 'bg-stone-100 text-stone-800 border-stone-200'],
                                ];
                            @endphp
                            @foreach($themes as $key => $info)
                                <button 
                                    type="button" 
                                    wire:click="$set('color_theme', '{{ $key }}')"
                                    class="p-2 rounded-control border text-center text-body font-medium transition cursor-pointer flex items-center justify-center gap-1.5 {{ $info['bg'] }} {{ $color_theme === $key ? 'ring-2 ring-ink ring-offset-1' : '' }}">
                                    <span>{{ $info['label'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Active Checkbox -->
                    <div class="flex items-center gap-2 pt-1">
                        <input 
                            wire:model="is_active" 
                            type="checkbox" 
                            id="is_active" 
                            class="w-4 h-4 rounded text-ink focus:ring-gold border-line-strong" />
                        <label for="is_active" class="text-body text-ink-2 cursor-pointer">{{ __('Active (available in planner)') }}</label>
                    </div>

                    <!-- Live Badge Preview -->
                    <div class="p-3 bg-sunken rounded-control space-y-1">
                        <span class="text-meta text-ink-3 block">{{ __('Button Preview') }}</span>
                        <div class="pt-0.5">
                            @php
                                $previewPreset = new \App\Models\SubtaskPreset(['color_theme' => $color_theme]);
                                $safePreviewIcon = (preg_match('/^[a-z0-9\-]+$/i', $emoji ?? '')) ? $emoji : 'tag';
                            @endphp
                            <span class="px-2.5 py-0.5 rounded-control text-body font-medium border inline-flex items-center gap-1.5 bg-surface text-ink border-line">
                                <x-dynamic-component :component="'lucide-' . $safePreviewIcon" class="w-3.5 h-3.5" />
                                <span>{{ $title ? __($title) : __('Example Subtask') }}</span>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-3 border-t border-line">
                        <flux:button type="button" @click="$wire.closeModal()" variant="ghost" size="sm">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary" size="sm" x-bind:disabled="!isDirty()">{{ __('Save Subtask') }}</flux:button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
