<?php

namespace App\Services;

use App\Enums\Substatus as SubstatusEnum;
use App\Models\Designer;
use App\Models\Setting;
use App\Models\Substatus;
use Illuminate\Support\Facades\Cache;

class ColorCodingService
{
    public const SETTING_KEY = 'color_coding_palette';

    public const CACHE_KEY = 'kudos_color_coding_palette_v1';

    /**
     * Default system color configuration with categories, affected entities, and curated presets.
     *
     * @return array<string, array{
     *     category: string,
     *     name: string,
     *     description: string,
     *     default_hex: string,
     *     icon: string,
     *     affects: array<string>,
     *     presets: array<string>
     * }>
     */
    public static function defaultDefinitions(): array
    {
        return [
            'camila' => [
                'category' => 'supervision',
                'category_name' => __('Supervision and QA'),
                'name' => __('Camila (QA & Revisiones)'),
                'description' => __('Affects the "Internal review" column, the "Camila changes" substatus, the Dashboard tracking panel and quality control notifications.'),
                'default_hex' => '#a855f7', // Purple
                'icon' => 'user-check',
                'affects' => [
                    __('Columna Kanban: ENVIADO A CAMILA'),
                    __('Subestatus: CAMBIOS CAMILA'),
                    __('Dashboard: Camila\'s tab, counter and tasks'),
                    __('Camila\'s review modal and notes'),
                ],
                'presets' => ['#a855f7', '#8b5cf6', '#9333ea', '#7c3aed', '#6366f1', '#ec4899', '#f43f5e'],
            ],
            'production' => [
                'category' => 'production',
                'category_name' => __('Production & workshop'),
                'name' => __('Production (production files & workshop)'),
                'description' => __('Affects the "In production" column, orders ready for production files, production adjustments and the print hand-off substatus.'),
                'default_hex' => '#ec4899', // Pink / Magenta
                'icon' => 'printer',
                'affects' => [
                    __('Kanban column: IN PRODUCTION'),
                    __('Subestatus: PONER EN ALTA'),
                    __('Subestatus: ENVIADO EN ALTA'),
                    __('Substatus: PRODUCTION ADJUSTMENTS'),
                    __('Dashboard: Ready for production files and Production files forecast tabs'),
                ],
                'presets' => ['#ec4899', '#db2777', '#f43f5e', '#e11d48', '#d946ef', '#c026d3', '#0d9488'],
            ],
            'designer_euraliz' => [
                'category' => 'designers',
                'category_name' => __('Team designers'),
                'name' => __('Euralíz (Lead designer)'),
                'description' => __('Euralíz Bravo\'s identification color, her Kanban work queue ("Euralíz Orders Received") and Planner labels.'),
                'default_hex' => '#F3A8FF', // Soft Magenta
                'icon' => 'crown',
                'affects' => [
                    __('Columna Kanban: EURALIZ ORDERS RECEIVED'),
                    __('Designer: Euralíz (dot, badge and initials)'),
                    __('Weekly Planner: Euralíz\'s column and cards'),
                ],
                'presets' => ['#F3A8FF', '#ec4899', '#f472b6', '#d946ef', '#c084fc', '#a855f7'],
            ],
            'designer_adrian' => [
                'category' => 'designers',
                'category_name' => __('Team designers'),
                'name' => __('Adrián Reinoza'),
                'description' => __('Adrián\'s identification color, his Kanban work queue ("Adrián Orders Received") and Planner labels.'),
                'default_hex' => '#5FE9B5', // Mint / Emerald
                'icon' => 'palette',
                'affects' => [
                    __('Columna Kanban: ADRIAN ORDERS RECEIVED'),
                    __('Designer: Adrián (dot, badge and initials)'),
                    __('Weekly Planner: Adrián\'s column and cards'),
                ],
                'presets' => ['#5FE9B5', '#10b981', '#059669', '#14b8a6', '#06b6d4', '#22c55e'],
            ],
            'designer_cesar' => [
                'category' => 'designers',
                'category_name' => __('Team designers'),
                'name' => __('César Guzmán'),
                'description' => __('César\'s identification color, his Kanban work queue ("César Orders Received") and Planner labels.'),
                'default_hex' => '#52EAFD', // Cyan / Sky
                'icon' => 'palette',
                'affects' => [
                    __('Columna Kanban: CESAR ORDERS RECEIVED'),
                    __('Designer: César (dot, badge and initials)'),
                    __('Weekly Planner: César\'s column and cards'),
                ],
                'presets' => ['#52EAFD', '#06b6d4', '#0ea5e9', '#38bdf8', '#3b82f6', '#2dd4bf'],
            ],
            'designer_external' => [
                'category' => 'designers',
                'category_name' => __('Team designers'),
                'name' => __('External designer / Freelance'),
                'description' => __('Visual identification of external support and freelance designers assigned to orders.'),
                'default_hex' => '#f59e0b', // Amber / Gold
                'icon' => 'external-link',
                'affects' => [
                    __('Designer: External designer (dot and badge)'),
                    __('Orders assigned to third parties without a fixed user'),
                ],
                'presets' => ['#f59e0b', '#d97706', '#eab308', '#ca8a04', '#f97316', '#ea580c'],
            ],
            'todo_today' => [
                'category' => 'workflow',
                'category_name' => __('Workflow'),
                'name' => __('For today / Actively in progress'),
                'description' => __('Affects the active "To Do Today" column, today\'s cards and jobs with immediate execution.'),
                'default_hex' => '#22c55e', // Green
                'icon' => 'calendar-check',
                'affects' => [
                    __('Columna Kanban: TO DO TODAY'),
                    __('Badge for work active today'),
                    __('Weekly Planner: Tasks scheduled for the day'),
                ],
                'presets' => ['#22c55e', '#16a34a', '#10b981', '#84cc16', '#eab308', '#3b82f6'],
            ],
            'blocked' => [
                'category' => 'workflow',
                'category_name' => __('Workflow'),
                'name' => __('Blocked / Missing information'),
                'description' => __('Affects blocked incoming orders, missing estimate approval and pending requirements.'),
                'default_hex' => '#f97316', // Orange
                'icon' => 'alert-triangle',
                'affects' => [
                    __('Columna Kanban: ENTRANTE (Bloqueada)'),
                    __('Subestatus: BLOQUEADA'),
                    __('Substatus: MISSING ESTIMATE APPROVAL'),
                    __('Substatus: MISSING INFORMATION'),
                ],
                'presets' => ['#f97316', '#ea580c', '#c2410c', '#ef4444', '#dc2626', '#f59e0b'],
            ],
            'client' => [
                'category' => 'workflow',
                'category_name' => __('Workflow'),
                'name' => __('Client (sent & awaiting feedback)'),
                'description' => __('Affects the "Sent to client" column, the client-requested changes substatus and the paused SLA clock.'),
                'default_hex' => '#3b82f6', // Blue
                'icon' => 'message-square',
                'affects' => [
                    __('Kanban column: SENT TO CLIENT'),
                    __('Subestatus: WAITING FOR CLIENT'),
                    __('Substatus: CLIENT CHANGES'),
                    __('Subestatus: ESPERANDO RESPUESTA'),
                ],
                'presets' => ['#3b82f6', '#2563eb', '#1d4ed8', '#0ea5e9', '#0284c7', '#6366f1'],
            ],
            'urgent' => [
                'category' => 'workflow',
                'category_name' => __('Workflow'),
                'name' => __('Urgente & Atraso Preventivo'),
                'description' => __('Affects automatic delay subtasks, priority alerts and tasks marked as urgent.'),
                'default_hex' => '#ef4444', // Red
                'icon' => 'alert-circle',
                'affects' => [
                    __('Automatic subtasks: Delay notice and delay prevention'),
                    __('Weekly Planner: Tasks and subtasks with urgent priority'),
                    __('Order detail: Critical alerts and urgent actions'),
                ],
                'presets' => ['#ef4444', '#dc2626', '#b91c1c', '#f43f5e', '#e11d48', '#ea580c'],
            ],
            'cs_hold' => [
                'category' => 'workflow',
                'category_name' => __('Workflow'),
                'name' => __('Customer service (CS) & hold'),
                'description' => __('Affects orders on hold (On Hold) due to no client response or the need for sales intervention.'),
                'default_hex' => '#64748b', // Slate
                'icon' => 'headphones',
                'affects' => [
                    __('Columna Kanban: ON HOLD'),
                    __('Subestatus: CUSTOMER SERVICE REQUIRED'),
                    __('Subestatus: NO RESPUESTA'),
                    __('Subestatus: PAUSADO'),
                ],
                'presets' => ['#64748b', '#475569', '#334155', '#71717a', '#78716c', '#ea580c'],
            ],
        ];
    }

    /**
     * Retrieve all configured color keys with their definitions and derived palettes.
     *
     * @return array<string, array>
     */
    public function getAll(): array
    {
        $defs = self::defaultDefinitions();
        $stored = $this->getStoredPalette();

        $result = [];
        foreach ($defs as $key => $def) {
            $currentHex = $stored[$key] ?? $def['default_hex'];
            $palette = $this->derivePalette($currentHex);

            $result[$key] = array_merge($def, [
                'key' => $key,
                'hex' => $currentHex,
                'palette' => $palette,
                'is_custom' => strcasecmp($currentHex, $def['default_hex']) !== 0,
            ]);
        }

        return $result;
    }

    /**
     * Get the active hex color for a given key.
     */
    public function getHex(string $key, ?string $default = null): string
    {
        $all = $this->getStoredPalette();
        if (isset($all[$key])) {
            return $all[$key];
        }

        $defs = self::defaultDefinitions();

        return $defs[$key]['default_hex'] ?? ($default ?? '#3b82f6');
    }

    /**
     * Alias for getHex.
     */
    public function getColorHex(string $key, ?string $default = null): string
    {
        return $this->getHex($key, $default);
    }

    /**
     * Get the full derived palette for a given key.
     */
    public function getPalette(string $key): array
    {
        $hex = $this->getHex($key);

        return $this->derivePalette($hex);
    }

    /**
     * Derive a harmonious, accessible color palette from any hex color.
     * Generates:
     * - Light background (subtle tint for pill/badge)
     * - Very subtle background (for card backgrounds)
     * - Dark high-contrast text color
     * - Soft border color
     * - Solid color
     * - High-contrast text on solid color (white or dark)
     */
    public function derivePalette(string $hex): array
    {
        $cleanHex = ltrim($hex, '#');
        if (strlen($cleanHex) === 3) {
            $cleanHex = $cleanHex[0].$cleanHex[0].$cleanHex[1].$cleanHex[1].$cleanHex[2].$cleanHex[2];
        }
        if (strlen($cleanHex) !== 6) {
            $cleanHex = '3B82F6';
        }

        $r255 = hexdec(substr($cleanHex, 0, 2));
        $g255 = hexdec(substr($cleanHex, 2, 2));
        $b255 = hexdec(substr($cleanHex, 4, 2));

        // YIQ luminance for solid badge text contrast
        $yiq = (($r255 * 299) + ($g255 * 587) + ($b255 * 114)) / 1000;
        $textOnSolid = ($yiq >= 165) ? '#09090b' : '#ffffff';

        // Convert to HSL for precise harmonious shades
        $r = $r255 / 255;
        $g = $g255 / 255;
        $b = $b255 / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            switch ($max) {
                case $r: $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
                    break;
                case $g: $h = ($b - $r) / $d + 2;
                    break;
                case $b: $h = ($r - $g) / $d + 4;
                    break;
                default: $h = 0;
                    break;
            }
            $h /= 6;
        }

        $hDeg = round($h * 360);
        $sSat = max(round($s * 100), 45); // Keep vibrant even with soft pastels

        $bgLight = "hsl({$hDeg}, {$sSat}%, 95%)";
        $bgSubtle = "hsl({$hDeg}, {$sSat}%, 98%)";
        $textDark = "hsl({$hDeg}, ".min($sSat + 25, 95).'%, 24%)';
        $border = "hsl({$hDeg}, {$sSat}%, 82%)";
        $hoverBorder = "hsl({$hDeg}, {$sSat}%, 70%)";

        $fullHex = '#'.strtoupper($cleanHex);

        return [
            'hex' => $fullHex,
            'solid' => $fullHex,
            'text_on_solid' => $textOnSolid,
            'bg_light' => $bgLight,
            'bg_subtle' => $bgSubtle,
            'text_dark' => $textDark,
            'border' => $border,
            'hover_border' => $hoverBorder,
            'badge_style' => "background-color: {$bgLight}; color: {$textDark}; border-color: {$border};",
            'solid_badge_style' => "background-color: {$fullHex}; color: {$textOnSolid}; border-color: {$fullHex};",
            'dot_style' => "background-color: {$fullHex};",
            'card_style' => "background-color: {$bgSubtle}; border-color: {$border};",
            'card_accent_style' => "border-left: 3px solid {$fullHex}; background-color: {$bgSubtle}; border-color: {$border};",
            'header_title_style' => "color: {$textDark};",
            'button_style' => "background-color: {$fullHex}; color: {$textOnSolid};",
        ];
    }

    /**
     * Save updated color for a key and sync to all related database entities app-wide.
     */
    public function updateColor(string $key, string $hex): void
    {
        $cleanHex = '#'.ltrim(trim($hex), '#');
        if (! preg_match('/^#[a-fA-F0-9]{3,6}$/', $cleanHex)) {
            return;
        }

        $stored = $this->getStoredPalette();
        $stored[$key] = $cleanHex;

        Setting::set(self::SETTING_KEY, json_encode($stored));
        Cache::forget(self::CACHE_KEY);

        // Sync directly to related database models so all queries immediately reflect the color
        $this->syncDatabaseEntities($key, $cleanHex);
    }

    /**
     * Reset a single key or all keys back to factory defaults.
     */
    public function resetToDefaults(?string $key = null): void
    {
        $defs = self::defaultDefinitions();

        if ($key !== null) {
            if (isset($defs[$key])) {
                $this->updateColor($key, $defs[$key]['default_hex']);
            }

            return;
        }

        // Reset all
        $clean = [];
        foreach ($defs as $k => $d) {
            $clean[$k] = $d['default_hex'];
            $this->syncDatabaseEntities($k, $d['default_hex']);
        }

        Setting::set(self::SETTING_KEY, json_encode($clean));
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Sync updated color to database models: Substatuses, Designers, etc.
     */
    protected function syncDatabaseEntities(string $key, string $hex): void
    {
        $palette = $this->derivePalette($hex);

        try {
            switch ($key) {
                case 'camila':
                    Substatus::where('name', SubstatusEnum::CAMBIOS_CAMILA->value)
                        ->orWhere('name', 'like', '%CAMILA%')
                        ->update([
                            'color' => $palette['hex'],
                            'bg_color' => $palette['bg_light'],
                            'text_color' => $palette['text_dark'],
                            'border_color' => $palette['border'],
                        ]);
                    break;

                case 'production':
                    Substatus::whereIn('name', [
                        SubstatusEnum::PONER_EN_ALTA->value,
                        SubstatusEnum::ENVIADO_EN_ALTA->value,
                        SubstatusEnum::AJUSTES_PRODUCCION->value,
                    ])->update([
                        'color' => $palette['hex'],
                        'bg_color' => $palette['bg_light'],
                        'text_color' => $palette['text_dark'],
                        'border_color' => $palette['border'],
                    ]);
                    break;

                case 'designer_euraliz':
                    Designer::where(function ($q) {
                        $q->where('slug', 'euraliz')
                            ->orWhere('name', 'like', '%Eural%');
                    })->update([
                        'hex_color' => $palette['hex'],
                    ]);
                    break;

                case 'designer_adrian':
                    Designer::where(function ($q) {
                        $q->where('slug', 'adrian')
                            ->orWhere('name', 'like', '%Adri%');
                    })->update([
                        'hex_color' => $palette['hex'],
                    ]);
                    break;

                case 'designer_cesar':
                    Designer::where(function ($q) {
                        $q->where('slug', 'cesar')
                            ->orWhere('name', 'like', '%Cés%')
                            ->orWhere('name', 'like', '%Cesar%');
                    })->update([
                        'hex_color' => $palette['hex'],
                    ]);
                    break;

                case 'designer_external':
                    Designer::where(function ($q) {
                        $q->where('slug', 'disenador-externo')
                            ->orWhere('is_external', true)
                            ->orWhere('name', 'like', '%Extern%');
                    })->update([
                        'hex_color' => $palette['hex'],
                    ]);
                    break;

                case 'blocked':
                    Substatus::whereIn('name', [
                        SubstatusEnum::BLOQUEADA->value,
                        SubstatusEnum::FALTA_APROBACION_ESTIMADO->value,
                        SubstatusEnum::FALTA_INFORMACION->value,
                    ])->update([
                        'color' => $palette['hex'],
                        'bg_color' => $palette['bg_light'],
                        'text_color' => $palette['text_dark'],
                        'border_color' => $palette['border'],
                    ]);
                    break;

                case 'client':
                    Substatus::whereIn('name', [
                        SubstatusEnum::WAITING_FOR_CLIENT->value,
                        SubstatusEnum::CAMBIOS_CLIENTE->value,
                        SubstatusEnum::ESPERANDO_RESPUESTA->value,
                    ])->update([
                        'color' => $palette['hex'],
                        'bg_color' => $palette['bg_light'],
                        'text_color' => $palette['text_dark'],
                        'border_color' => $palette['border'],
                    ]);
                    break;

                case 'cs_hold':
                    Substatus::whereIn('name', [
                        SubstatusEnum::CUSTOMER_SERVICE_REQUIRED->value,
                        SubstatusEnum::NO_RESPUESTA->value,
                        SubstatusEnum::PAUSADO->value,
                    ])->update([
                        'color' => $palette['hex'],
                        'bg_color' => $palette['bg_light'],
                        'text_color' => $palette['text_dark'],
                        'border_color' => $palette['border'],
                    ]);
                    break;
            }
        } catch (\Throwable $e) {
            // Silently handle if database is not migrated yet in testing
        }
    }

    /**
     * Generate standard CSS custom properties (:root variables) for global injection in views.
     */
    public function generateCssVariables(): string
    {
        $items = $this->getAll();
        $lines = [':root {'];

        foreach ($items as $key => $item) {
            $p = $item['palette'];
            $varPrefix = '--cc-'.str_replace('_', '-', $key);
            $lines[] = "    {$varPrefix}-solid: {$p['solid']};";
            $lines[] = "    {$varPrefix}-text-on-solid: {$p['text_on_solid']};";
            $lines[] = "    {$varPrefix}-bg-light: {$p['bg_light']};";
            $lines[] = "    {$varPrefix}-bg-subtle: {$p['bg_subtle']};";
            $lines[] = "    {$varPrefix}-text-dark: {$p['text_dark']};";
            $lines[] = "    {$varPrefix}-border: {$p['border']};";

            if (str_contains($key, '_')) {
                $varPrefixUnder = '--cc-'.$key;
                $lines[] = "    {$varPrefixUnder}-solid: {$p['solid']};";
                $lines[] = "    {$varPrefixUnder}-text-on-solid: {$p['text_on_solid']};";
                $lines[] = "    {$varPrefixUnder}-bg-light: {$p['bg_light']};";
                $lines[] = "    {$varPrefixUnder}-bg-subtle: {$p['bg_subtle']};";
                $lines[] = "    {$varPrefixUnder}-text-dark: {$p['text_dark']};";
                $lines[] = "    {$varPrefixUnder}-border: {$p['border']};";
            }
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    /**
     * Get stored palette json from Setting table.
     *
     * @return array<string, string>
     */
    protected function getStoredPalette(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            $raw = Setting::get(self::SETTING_KEY);
            if (empty($raw)) {
                return [];
            }

            if (is_array($raw)) {
                return $raw;
            }

            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        });
    }
}
