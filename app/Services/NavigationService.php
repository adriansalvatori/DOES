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
                $this->item(__('Hoy'), __('Centro de Control Operativo'), '/', '/', 'sun', true),
                $this->item(__('Analytics'), __('Analytics Dashboard'), '/analytics', 'analytics*', 'bar-chart-3', ! $isDesigner),
            ],
            [
                $this->item(__('Kanban Board'), __('Kanban Board'), '/kanban', 'kanban*', 'kanban', true),
                $this->item(__('Planificador Semanal'), __('Planificador Semanal'), '/planner', 'planner*', 'calendar-days', true),
                $this->item(__('Overview'), __('Overview Operativo'), '/overview', 'overview*', 'table-properties', ! $isDesigner),
            ],
            [
                $this->item(__('Directorio de Clientes'), __('Directorio de Clientes'), '/contacts', 'contacts*', 'users', true),
                $this->item(__('Compañías'), __('Compañías'), '/clients', 'clients*', 'building-2', true),
                $this->item(__('Archivadas'), __('Órdenes Archivadas'), '/archived', 'archived*', 'archive', true),
                $this->item(__('Papelera'), __('Papelera de Reciclaje'), '/trash', 'trash*', 'trash-2', ! $isDesigner && ! $isSales),
            ],
            [
                $this->item(__('Precios & Calculadora'), __('Precios & Calculadora'), '/pricing', 'pricing*', 'badge-percent', true),
                $this->item(__('Recursos de Diseño'), __('Recursos de Diseño'), '/design-resources', 'design-resources*', 'folder-down', true),
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
            $this->item(__('Backlog'), __('Backlog de Órdenes'), '/backlog', 'backlog*', 'box', true),
            $this->item(__('Sincronización'), __('Sincronización Trello'), '/trello-sync', 'trello-sync*', 'refresh-cw', $canSync),
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
                'label' => __('Cuenta'),
                'items' => [
                    $this->item(__('Mi Perfil'), __('Mi Perfil'), '/settings/profile', 'settings/profile*', 'user-cog', true, __('Nombre, foto, contraseña y preferencias.')),
                    $this->item(__('Idioma'), __('Idioma / Language'), '/settings/language', 'settings/language*', 'languages', true, __('Español o inglés para la interfaz.')),
                ],
            ],
            [
                'key' => 'team',
                'label' => __('Equipo'),
                'items' => [
                    $this->item(__('Usuarios y Roles'), __('Gestión de Usuarios'), '/settings/users', 'settings/users*', 'users', $isAdmin, __('Quién entra y qué puede hacer.')),
                    $this->item(__('Notificaciones'), __('Configuración de Notificaciones'), '/settings/notifications', 'settings/notifications*', 'bell-ring', $isAdmin, __('Qué eventos avisan al equipo.')),
                ],
            ],
            [
                'key' => 'workflow',
                'label' => __('Flujo de trabajo'),
                'items' => [
                    $this->item(__('Subestatus'), __('Configuración de Subestatus'), '/settings/substatuses', 'settings/substatuses*', 'tags', $canConfigureWorkflow, __('Motivos y estados secundarios de una orden.')),
                    $this->item(__('Instalación'), __('Configuración de Tipos de Instalación'), '/settings/installation-types', 'settings/installation-types*', 'truck', $canConfigureWorkflow, __('Tipos de instalación disponibles.')),
                    $this->item(__('Plantillas Subtareas'), __('Plantillas de Subtareas'), '/settings/subtasks', 'settings/subtasks*', 'list-checks', $canConfigureWorkflow, __('Subtareas que se crean con cada orden.')),
                    $this->item(__('Color Coding'), __('Personalización de Colores'), '/settings/color-coding', 'settings/color-coding*', 'palette', $canConfigureWorkflow, __('Colores de las personas del equipo.')),
                ],
            ],
            [
                'key' => 'integrations',
                'label' => __('Integraciones'),
                'items' => [
                    $this->item(__('Google Drive'), __('Google Drive'), '/settings/google-drive', 'settings/google-drive*', 'hard-drive', $isAdmin, __('Cuenta de Google donde se guardan los archivos.')),
                    $this->item(__('Mapeo Listas Trello'), __('Mapeo de Listas Trello'), '/settings/trello-mapping', 'settings/trello-mapping*', 'sliders', $isAdmin, __('Qué lista de Trello corresponde a cada estado.')),
                    $this->item(__('Sincronización'), __('Sincronización Trello'), '/trello-sync', 'trello-sync*', 'refresh-cw', $canConfigureWorkflow, __('Credenciales y sincronización con Trello.')),
                ],
            ],
            [
                'key' => 'data',
                'label' => __('Datos'),
                'items' => [
                    $this->item(__('Respaldos'), __('Respaldos de Base de Datos'), '/settings/backups', 'settings/backups*', 'database', $isAdmin, __('Copias de seguridad de la base de datos.')),
                    $this->item(__('Conciliación CSV'), __('Conciliación CSV'), '/settings/csv-reconciliation', 'settings/csv-reconciliation*', 'file-spreadsheet', $isAdmin, __('Cruce de órdenes con un archivo CSV.')),
                ],
            ],
            [
                'key' => 'help',
                'label' => __('Ayuda'),
                'items' => [
                    $this->item(__('Guía de Comportamientos'), __('Guía de Comportamientos'), '/settings/documentation', 'settings/documentation*', 'book-open', $isAdmin, __('Cómo se comporta el sistema y por qué.')),
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
                $add($item, __('Ir a'));
            }
        }
        foreach ($this->bottomItems($user) as $item) {
            $add($item, __('Ir a'));
        }
        $add($this->item(__('Ajustes'), __('Ajustes'), '/settings', 'settings', 'settings', true), __('Ajustes'));
        foreach ($this->settingsGroups($user) as $group) {
            foreach ($group['items'] as $item) {
                $add($item, __('Ajustes'));
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
        $settings = ['label' => __('Ajustes'), 'href' => '/settings'];

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
