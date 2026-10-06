<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class InstallationType extends Model
{
    use HasFactory;

    protected $table = 'installation_types';

    protected $fillable = [
        'name',
        'color',
        'style_type',
        'bg_color',
        'text_color',
        'border_color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value !== null ? mb_strtoupper($value) : null,
            set: fn (?string $value) => $value !== null ? mb_strtoupper($value) : null,
        );
    }

    public const CACHE_KEY = 'installation_types_all';

    protected static ?Collection $memoized = null;

    protected static function booted(): void
    {
        static::saved(function (self $model) {
            static::clearCache();
            if (! empty($model->name)) {
                $existing = Supplier::all();
                $match = $existing->first(fn ($s) => mb_strtolower(trim($s->name)) === mb_strtolower(trim($model->name)));
                if (! $match) {
                    Supplier::create([
                        'name' => $model->name,
                        'is_active' => true,
                    ]);
                }
            }
        });

        static::deleted(function () {
            static::clearCache();
        });
    }

    public static function syncAllToSuppliers(): void
    {
        $existing = Supplier::all();
        foreach (static::all() as $item) {
            if (empty($item->name)) {
                continue;
            }
            $match = $existing->first(fn ($s) => mb_strtolower(trim($s->name)) === mb_strtolower(trim($item->name)));
            if (! $match) {
                Supplier::create([
                    'name' => $item->name,
                    'is_active' => true,
                ]);
            }
        }
    }

    public static function clearCache(): void
    {
        static::$memoized = null;
        try {
            Cache::forget(self::CACHE_KEY);
        } catch (\Throwable) {
            // Ignore cache forget issues
        }
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim($search ?? '');
        if ($search === '') {
            return $query;
        }

        $term = '%'.addcslashes($search, '%_\\').'%';

        return $query->where('name', 'like', $term);
    }

    /**
     * Get all active installation types cached for fast global lookup.
     * Caches as plain arrays to prevent PHP serialization / __PHP_Incomplete_Class errors.
     *
     * @return Collection<int, static>
     */
    public static function getAllCached(): Collection
    {
        if (static::$memoized !== null) {
            return static::$memoized;
        }

        try {
            $data = Cache::remember(self::CACHE_KEY, 3600, function () {
                return static::active()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (self $item) => $item->getAttributes())
                    ->all();
            });

            if (is_array($data)) {
                $collection = collect($data)->map(function ($attrs) {
                    if (isset($attrs['name']) && is_string($attrs['name'])) {
                        $attrs['name'] = mb_strtoupper($attrs['name']);
                    }
                    $model = new static;
                    $model->setRawAttributes($attrs, true);
                    $model->exists = true;

                    return $model;
                });

                return static::$memoized = $collection;
            }
        } catch (\Throwable) {
            static::clearCache();
        }

        $items = static::active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return static::$memoized = $items;
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

        // Derive coherent light pastel bg, dark readable text, and soft border using HSL
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

    /**
     * Helper to get styling for a given installation type name.
     */
    public static function getStyleFor(?string $name): array
    {
        if (! $name) {
            return [
                'inline' => '',
                'bg' => 'transparent',
                'text' => '#a8a29e',
                'border' => 'transparent',
            ];
        }

        $cached = static::getAllCached();
        $normalized = mb_strtolower(trim($name));

        $item = $cached->first(function ($it) use ($name, $normalized) {
            $itName = is_array($it) ? ($it['name'] ?? '') : ($it->name ?? '');

            return $itName === $name || mb_strtolower(trim($itName)) === $normalized;
        });

        if ($item) {
            $bgColor = is_array($item) ? ($item['bg_color'] ?? null) : $item->bg_color;
            $textColor = is_array($item) ? ($item['text_color'] ?? null) : $item->text_color;
            $borderColor = is_array($item) ? ($item['border_color'] ?? null) : $item->border_color;

            if ($bgColor && $textColor) {
                $bColor = $borderColor ?: $bgColor;

                return [
                    'inline' => "background-color: {$bgColor}; color: {$textColor}; border-color: {$bColor};",
                    'bg' => $bgColor,
                    'text' => $textColor,
                    'border' => $bColor,
                ];
            }
        }

        return [
            'inline' => 'background-color: #f5f5f4; color: #44403c; border-color: #e7e5e4;',
            'bg' => '#f5f5f4',
            'text' => '#44403c',
            'border' => '#e7e5e4',
        ];
    }
}
