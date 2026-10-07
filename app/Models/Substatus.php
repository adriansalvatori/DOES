<?php

namespace App\Models;

use App\Enums\CoreStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Substatus extends Model
{
    use HasFactory;

    protected $table = 'substatuses';

    protected $fillable = [
        'name',
        'core_status',
        'is_default',
        'is_global',
        'color',
        'style_type',
        'bg_color',
        'text_color',
        'border_color',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'core_status' => CoreStatus::class,
        'is_default' => 'boolean',
        'is_global' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? mb_strtoupper($value) : null,
            set: fn (?string $value) => $value !== null ? mb_strtoupper($value) : null,
        );
    }

    public function scopeForCoreStatus(Builder $query, CoreStatus|string $coreStatus): Builder
    {
        $statusValue = $coreStatus instanceof CoreStatus ? $coreStatus->value : $coreStatus;

        return $query->where(function ($q) use ($statusValue) {
            $q->where('core_status', $statusValue)
                ->orWhere('is_global', true);
        })
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('is_global', true);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('core_status', CoreStatus::ARCHIVED->value)
            ->where('is_global', false);
    }

    /**
     * Cached list of uppercase names for all archived substatuses.
     */
    protected static ?array $cachedArchivedNames = null;

    /**
     * Get all uppercase names of substatuses that belong to ARCHIVED.
     *
     * @return array<int, string>
     */
    public static function getArchivedNames(): array
    {
        if (static::$cachedArchivedNames !== null) {
            return static::$cachedArchivedNames;
        }

        try {
            $dbNames = static::archived()
                ->pluck('name')
                ->map(fn ($n) => mb_strtoupper(trim((string) $n)))
                ->all();
        } catch (\Throwable $e) {
            $dbNames = [];
        }

        $enumNames = array_map(
            fn ($case) => mb_strtoupper(trim($case->value)),
            CoreStatus::ARCHIVED->validSubstatuses()
        );

        $commonVariants = [
            'ORDEN LISTA',
            'ORDEN LISTA !',
            'ORDEN LISTA - ENTREGADA',
            'FINALIZADA',
            'FINALIZADA !',
        ];

        return static::$cachedArchivedNames = array_values(array_unique(array_merge($dbNames, $enumNames, $commonVariants)));
    }

    /**
     * Check if a given substatus (string, Enum, or Model) represents an archived substatus.
     */
    public static function isArchivedSubstatus(mixed $substatus): bool
    {
        if (empty($substatus)) {
            return false;
        }

        if ($substatus instanceof self) {
            return $substatus->core_status === CoreStatus::ARCHIVED
                || in_array(mb_strtoupper(trim((string) $substatus->name)), static::getArchivedNames(), true);
        }

        if ($substatus instanceof \App\Enums\Substatus) {
            return in_array(mb_strtoupper(trim($substatus->value)), static::getArchivedNames(), true);
        }

        $clean = mb_strtoupper(trim((string) $substatus));

        return in_array($clean, static::getArchivedNames(), true);
    }

    /**
     * Get all substatuses belonging to ARCHIVED status, ordered by manual sort_order.
     *
     * @return Collection<int, self>
     */
    public static function getArchivedSubstatuses(): Collection
    {
        $db = static::archived()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($db->isNotEmpty()) {
            return $db;
        }

        return collect(CoreStatus::ARCHIVED->validSubstatuses())->map(function ($enum) {
            return new static([
                'name' => $enum->value,
                'core_status' => CoreStatus::ARCHIVED,
                'is_default' => $enum === CoreStatus::ARCHIVED->defaultSubstatus(),
            ]);
        });
    }

    /**
     * Get the name of default substatus for ARCHIVED status.
     */
    public static function getDefaultArchivedSubstatus(): string
    {
        $default = static::archived()
            ->where('is_default', true)
            ->value('name');

        if ($default) {
            return $default;
        }

        return \App\Enums\Substatus::FINALIZADA->value;
    }

    public function getInlineBadgeStyle(): string
    {
        if ($this->bg_color && $this->text_color) {
            $borderColor = $this->border_color ?: $this->bg_color;

            return "background-color: {$this->bg_color}; color: {$this->text_color}; border-color: {$borderColor};";
        }

        return '';
    }

    public function getInlineBadgeStyleAttribute(): string
    {
        return $this->getInlineBadgeStyle();
    }

    public function getValueAttribute(): string
    {
        return $this->name;
    }

    public function label(): string
    {
        $enum = \App\Enums\Substatus::tryFrom($this->name);
        if ($enum) {
            return mb_strtoupper($enum->label());
        }

        return mb_strtoupper($this->name);
    }

    public function badgeStyle(): string
    {
        $enum = \App\Enums\Substatus::tryFrom($this->name);
        if ($enum) {
            return $enum->badgeStyle();
        }

        return "background-color: {$this->bg_color}; color: {$this->text_color}; border-color: {$this->border_color}; font-weight: 500;";
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return $query;
        }

        $words = array_values(array_filter(preg_split('/\s+/', $search), fn ($w) => $w !== ''));
        if (empty($words)) {
            return $query;
        }

        return $query->where(function ($q) use ($words) {
            foreach ($words as $word) {
                $term = '%'.addcslashes($word, '%_\\').'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('name', 'like', $term);
                });
            }
        });
    }

    /**
     * Derive a coherent background, text, and border palette from a single main hex color and style type (light vs solid).
     */
    public static function derivePaletteFromColor(string $hex, string $styleType = 'light'): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6) {
            $hex = '6B7280';
        }

        if ($styleType === 'solid') {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
            $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
            $textColor = ($yiq >= 160) ? '#111827' : '#FFFFFF';

            return [
                'color' => '#'.$hex,
                'style_type' => 'solid',
                'bg_color' => '#'.$hex,
                'text_color' => $textColor,
                'border_color' => '#'.$hex,
            ];
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

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
            }
            $h /= 6;
        }

        $h = round($h * 360);
        $s = round($s * 100);

        // Derive coherent light bg, text, border using HSL
        $bg = "hsl({$h}, ".max($s, 70).'%, 96%)';
        $text = "hsl({$h}, ".max($s, 80).'%, 30%)';
        $border = "hsl({$h}, ".max($s, 70).'%, 86%)';

        return [
            'color' => '#'.$hex,
            'style_type' => 'light',
            'bg_color' => $bg,
            'text_color' => $text,
            'border_color' => $border,
        ];
    }

    /**
     * Resolve the full color palette (bg, text, border, solid) for any substatus name,
     * prioritizing user-defined settings in the DB, then enum/seeder fallbacks.
     *
     * @return array{color: string, style_type: string, bg_color: string, text_color: string, border_color: string, solid_bg: string, solid_text: string, inline: string}
     */
    public static function resolvePalette(?string $name): array
    {
        if (! $name) {
            return [
                'color' => '#6B7280',
                'style_type' => 'light',
                'bg_color' => '#F5F5F4',
                'text_color' => '#57534E',
                'border_color' => '#E7E5E4',
                'solid_bg' => '#78716C',
                'solid_text' => '#FFFFFF',
                'inline' => 'background-color: #F5F5F4; color: #57534E; border-color: #E7E5E4;',
            ];
        }

        static $cache = null;
        if ($cache === null) {
            try {
                $cache = static::all()->keyBy(fn ($s) => mb_strtoupper(trim((string) $s->name)));
            } catch (\Throwable $e) {
                $cache = collect();
            }
        }

        $upper = mb_strtoupper(trim($name));

        // 1. Exact match in DB
        $sub = $cache->get($upper);

        // 2. Name variation in DB
        if (! $sub) {
            if ($upper === 'FINALIZADA' || $upper === 'FINALIZADA !') {
                $sub = $cache->get('FINALIZADA !') ?? $cache->get('FINALIZADA');
            } elseif ($upper === 'CLIENTE NO RESPONDIO' || $upper === 'CLIENTE NO RESPONSIVE') {
                $sub = $cache->get('CLIENTE NO RESPONDIO') ?? $cache->get('CLIENTE NO RESPONSIVE');
            } elseif (str_starts_with($upper, 'ORDEN LISTA')) {
                $sub = $cache->get('ORDEN LISTA - ENTREGADA') ?? $cache->get('ORDEN LISTA !');
            }
        }

        if ($sub && $sub->bg_color && $sub->text_color) {
            $solidBg = $sub->color ?: ($sub->style_type === 'solid' ? $sub->bg_color : '#0E7490');
            $solidText = ($sub->style_type === 'solid' && $sub->text_color) ? $sub->text_color : '#FFFFFF';
            $borderColor = $sub->border_color ?: $sub->bg_color;

            return [
                'color' => $sub->color ?: $sub->bg_color,
                'style_type' => $sub->style_type ?: 'light',
                'bg_color' => $sub->bg_color,
                'text_color' => $sub->text_color,
                'border_color' => $borderColor,
                'solid_bg' => $solidBg,
                'solid_text' => $solidText,
                'inline' => "background-color: {$sub->bg_color}; color: {$sub->text_color}; border-color: {$borderColor};",
            ];
        }

        // 3. Known system defaults (match Seeder & Enum standards)
        $cleanKey = rtrim($upper, ' !');
        if (in_array($cleanKey, ['CANCELADA', 'CANCELADA POR CLIENTE', 'CANCELADA POR CAMILA'], true)) {
            $pal = static::derivePaletteFromColor('#EF4444', 'light');

            return array_merge($pal, [
                'solid_bg' => '#EF4444',
                'solid_text' => '#FFFFFF',
                'inline' => "background-color: {$pal['bg_color']}; color: {$pal['text_color']}; border-color: {$pal['border_color']};",
            ]);
        }

        if (in_array($cleanKey, ['FINALIZADA', 'ORDEN LISTA', 'ORDEN LISTA - ENTREGADA'], true)) {
            $pal = static::derivePaletteFromColor('#10B981', 'light');

            return array_merge($pal, [
                'solid_bg' => '#10B981',
                'solid_text' => '#FFFFFF',
                'inline' => "background-color: {$pal['bg_color']}; color: {$pal['text_color']}; border-color: {$pal['border_color']};",
            ]);
        }

        if (in_array($cleanKey, ['CLIENTE NO RESPONDIO', 'CLIENTE NO RESPONSIVE'], true)) {
            return [
                'color' => '#F59E0B',
                'style_type' => 'light',
                'bg_color' => '#FEF3C7',
                'text_color' => '#78350F',
                'border_color' => '#FDE68A',
                'solid_bg' => '#F59E0B',
                'solid_text' => '#FFFFFF',
                'inline' => 'background-color: #FEF3C7; color: #78350F; border-color: #FDE68A;',
            ];
        }

        if (in_array($cleanKey, ['NO REALIZADA / TRANSFERIDA', 'NO REALIZADA', 'PAUSADO', 'NO RESPUESTA'], true)) {
            return [
                'color' => '#78716C',
                'style_type' => 'light',
                'bg_color' => '#F5F5F4',
                'text_color' => '#57534E',
                'border_color' => '#E7E5E4',
                'solid_bg' => '#78716C',
                'solid_text' => '#FFFFFF',
                'inline' => 'background-color: #F5F5F4; color: #57534E; border-color: #E7E5E4;',
            ];
        }

        if (in_array($cleanKey, ['ENVIADO EN ALTA', 'AJUSTES DE PRODUCCIÓN'], true)) {
            $pal = static::derivePaletteFromColor('#FFAA00', 'light');

            return array_merge($pal, [
                'solid_bg' => '#FFAA00',
                'solid_text' => '#FFFFFF',
                'inline' => "background-color: {$pal['bg_color']}; color: {$pal['text_color']}; border-color: {$pal['border_color']};",
            ]);
        }

        // 4. Default fallback
        $pal = static::derivePaletteFromColor('#6B7280', 'light');

        return array_merge($pal, [
            'solid_bg' => '#6B7280',
            'solid_text' => '#FFFFFF',
            'inline' => "background-color: {$pal['bg_color']}; color: {$pal['text_color']}; border-color: {$pal['border_color']};",
        ]);
    }

    public static function getStyleFor(?string $name): array
    {
        $pal = static::resolvePalette($name);

        return [
            'inline' => $pal['inline'],
            'bg' => $pal['bg_color'],
            'text' => $pal['text_color'],
            'border' => $pal['border_color'],
        ];
    }
}
