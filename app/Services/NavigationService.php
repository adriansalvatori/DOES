<?php

namespace App\Services;

use App\Models\User;

/**
 * The app's navigation, defined once. The sidebar, the settings hub and sub-navigation,
 * and the ⌘K command palette all read from here, so adding a destination is one line.
 *
 * Every item is an array{label: string, title: string, href: string, pattern: string, icon: string, description?: string},
 * where `pattern` is matched with request()->is() and `icon` is a Lucide icon name.
 */
class NavigationService
{
    /**
     * Primary sidebar destinations, grouped. Groups are separated by a divider; empty groups are dropped.
     *
     * @return array<int, array<int, array{label: string, title: string, href: string, pattern: string, icon: string}>>
     */
    public function primaryGroups(?User $user): array
    {
        $isDesigner = (bool) $user?->isDesigner();
        $isSales = (bool) $user?->isSales();

        return $this->filterGroups([
            [
                $this->item(__('Today'), __('Operational Control Center'), '/', '/', 'sun', true),
                $this->item(__('Analytics'), __('Analytics Dashboard'), '/analytics', 'analytics*', 'bar-chart-3', ! $isDesigner),
            ],
            [
                $this->item(__('Kanban Board'), __('Kanban Board'), '/kanban', 'kanban*', 'kanban', true),
                $this->item(__('Weekly Planner'), __('Weekly Planner'), '/planner', 'planner*', 'calendar-days', true),
                $this->item(__('Overview'), __('Operations overview'), '/overview', 'overview*', 'table-properties', ! $isDesigner),
            ],
            [
                $this->item(__('Client directory'), __('Client directory'), '/contacts', 'contacts*', 'users', true),
                $this->item(__('Companies'), __('Companies'), '/clients', 'clients*', 'building-2', true),
                $this->item(__('Archived'), __('Archived Orders'), '/archived', 'archived*', 'archive', true),
                $this->item(__('Trash'), __('Recycling Bin'), '/trash', 'trash*', 'trash-2', ! $isDesigner && ! $isSales),
            ],
            [
                $this->item(__('Pricing & Calculator'), __('Pricing & Calculator'), '/pricing', 'pricing*', 'badge-percent', true),
                $this->item(__('Design Resources'), __('Design Resources'), '/design-resources', 'design-resources*', 'folder-down', true),
            ],
        ]);
    }

    /**
     * Links docked at the bottom of the sidebar, above the account menu.
     *
     * @return array<int, array{label: string, title: string, href: string, pattern: string, icon: string}>
     */
    public function bottomItems(?User $user): array
    {
        $canSync = (bool) ($user?->isAdmin() || $user?->isCoordinator());

        return $this->filterItems([
            $this->item(__('Backlog'), __('Order Backlog'), '/backlog', 'backlog*', 'box', true),
            $this->item(__('Sync'), __('Trello Sync'), '/trello-sync', 'trello-sync*', 'refresh-cw', $canSync),
        ]);
    }

    /**
     * Settings pages grouped by purpose, for the settings hub and its sub-navigation.
     *
     * @return array<int, array{key: string, label: string, items: array<int, array{label: string, title: string, href: string, pattern: string, icon: string, description: string}>}>
     */
    public function settingsGroups(?User $user): array
    {
        $isAdmin = (bool) $user?->isAdmin();
        $canConfigureWorkflow = $isAdmin || (bool) $user?->isCoordinator();

        $groups = [
            [
                'key' => 'account',
                'label' => __('Account'),
                'items' => [
                    $this->item(__('My profile'), __('My profile'), '/settings/profile', 'settings/profile*', 'user-cog', true, __('Name, photo, password and preferences.')),
                    $this->item(__('Language'), __('Idioma / Language'), '/settings/language', 'settings/language*', 'languages', true, __('Spanish or English for the interface.')),
                ],
            ],
            [
                'key' => 'team',
                'label' => __('Team'),
                'items' => [
                    $this->item(__('Users and roles'), __('User management'), '/settings/users', 'settings/users*', 'users', $isAdmin, __('Who can sign in and what they can do.')),
                    $this->item(__('Notifications'), __('Notification settings'), '/settings/notifications', 'settings/notifications*', 'bell-ring', $isAdmin, __('Which events notify the team.')),
                ],
            ],
            [
                'key' => 'workflow',
                'label' => __('Workflow'),
                'items' => [
                    $this->item(__('Substatuses'), __('Substatus Configuration'), '/settings/substatuses', 'settings/substatuses*', 'tags', $canConfigureWorkflow, __('Reasons and secondary states for an order.')),
                    $this->item(__('Installation'), __('Installation type settings'), '/settings/installation-types', 'settings/installation-types*', 'truck', $canConfigureWorkflow, __('Available installation types.')),
                    $this->item(__('Subtask Templates'), __('Subtask Templates'), '/settings/subtasks', 'settings/subtasks*', 'list-checks', $canConfigureWorkflow, __('Subtasks created with every order.')),
                    $this->item(__('Color coding'), __('Color customization'), '/settings/color-coding', 'settings/color-coding*', 'palette', $canConfigureWorkflow, __('Colors for the people on the team.')),
                ],
            ],
            [
                'key' => 'integrations',
                'label' => __('Integrations'),
                'items' => [
                    $this->item(__('Google Drive'), __('Google Drive'), '/settings/google-drive', 'settings/google-drive*', 'hard-drive', $isAdmin, __('Google account where files are stored.')),
                    $this->item(__('Trello List Mapping'), __('Trello List Mapping'), '/settings/trello-mapping', 'settings/trello-mapping*', 'sliders', $isAdmin, __('Which Trello list matches each status.')),
                    $this->item(__('Sync'), __('Trello Sync'), '/trello-sync', 'trello-sync*', 'refresh-cw', $canConfigureWorkflow, __('Trello credentials and sync.')),
                ],
            ],
            [
                'key' => 'data',
                'label' => __('Data'),
                'items' => [
                    $this->item(__('Backups'), __('Database backups'), '/settings/backups', 'settings/backups*', 'database', $isAdmin, __('Database backups.')),
                    $this->item(__('CSV reconciliation'), __('CSV reconciliation'), '/settings/csv-reconciliation', 'settings/csv-reconciliation*', 'file-spreadsheet', $isAdmin, __('Match orders against a CSV file.')),
                ],
            ],
            [
                'key' => 'help',
                'label' => __('Help'),
                'items' => [
                    $this->item(__('Behavior guide'), __('Behavior guide'), '/settings/documentation', 'settings/documentation*', 'book-open', $isAdmin, __('How the system behaves and why.')),
                ],
            ],
        ];

        $visible = [];
        foreach ($groups as $group) {
            $group['items'] = $this->filterItems($group['items']);
            if ($group['items'] !== []) {
                $visible[] = $group;
            }
        }

        return $visible;
    }

    /**
     * Every destination the user can reach, flattened with its section name, for the command palette.
     *
     * @return array<int, array{label: string, title: string, href: string, pattern: string, icon: string, section: string}>
     */
    public function destinations(?User $user): array
    {
        $destinations = [];
        $seen = [];

        $add = function (array $item, string $section) use (&$destinations, &$seen): void {
            if (isset($seen[$item['href']])) {
                return;
            }
            $seen[$item['href']] = true;
            $destinations[] = [...$item, 'section' => $section];
        };

        foreach ($this->primaryGroups($user) as $group) {
            foreach ($group as $item) {
                $add($item, __('Go to'));
            }
        }
        foreach ($this->bottomItems($user) as $item) {
            $add($item, __('Go to'));
        }
        $add($this->item(__('Settings'), __('Settings'), '/settings', 'settings', 'settings', true), __('Settings'));
        foreach ($this->settingsGroups($user) as $group) {
            foreach ($group['items'] as $item) {
                $add($item, __('Settings'));
            }
        }

        return $destinations;
    }

    /**
     * The trail the top bar shows for the current page, e.g. Ajustes › Respaldos. Empty when the page is not in the navigation.
     *
     * @return array<int, array{label: string, href: string}>
     */
    public function breadcrumb(?User $user): array
    {
        $settings = ['label' => __('Settings'), 'href' => '/settings'];

        foreach ($this->settingsGroups($user) as $group) {
            foreach ($group['items'] as $item) {
                if (request()->is($item['pattern'])) {
                    return [$settings, ['label' => $item['label'], 'href' => $item['href']]];
                }
            }
        }

        if (request()->is('settings', 'settings/*')) {
            return [$settings];
        }

        foreach ([...array_merge(...$this->primaryGroups($user)), ...$this->bottomItems($user)] as $item) {
            if (request()->is($item['pattern'])) {
                return [['label' => $item['label'], 'href' => $item['href']]];
            }
        }

        return [];
    }

    /**
     * @return array{label: string, title: string, href: string, pattern: string, icon: string, description?: string, show: bool}
     */
    private function item(string $label, string $title, string $href, string $pattern, string $icon, bool $show, ?string $description = null): array
    {
        $item = [
            'label' => $label,
            'title' => $title,
            'href' => $href,
            'pattern' => $pattern,
            'icon' => $icon,
            'show' => $show,
        ];

        if ($description !== null) {
            $item['description'] = $description;
        }

        return $item;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function filterItems(array $items): array
    {
        $visible = [];
        foreach ($items as $item) {
            if ($item['show']) {
                unset($item['show']);
                $visible[] = $item;
            }
        }

        return $visible;
    }

    /**
     * @param  array<int, array<int, array<string, mixed>>>  $groups
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function filterGroups(array $groups): array
    {
        $visible = [];
        foreach ($groups as $group) {
            $items = $this->filterItems($group);
            if ($items !== []) {
                $visible[] = $items;
            }
        }

        return $visible;
    }
}
