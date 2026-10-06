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
        });
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
     * Get all substatuses belonging to ARCHIVED status, with default first and sorted.
     *
     * @return Collection<int, self>
     */
    public static function getArchivedSubstatuses(): Collection
    {
        $db = static::archived()
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name')
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

    public static function getStyleFor(?string $name): array
    {
        if (! $name) {
            return [
                'inline' => 'background-color: #f3f4f6; color: #374151; border-color: #e5e7eb;',
                'bg' => '#f3f4f6',
                'text' => '#374151',
                'border' => '#e5e7eb',
            ];
        }

        $upperName = mb_strtoupper($name);
        $sub = static::where('name', $upperName)->orWhere('name', $name)->first();
        if ($sub) {
            return [
                'inline' => "background-color: {$sub->bg_color}; color: {$sub->text_color}; border-color: {$sub->border_color};",
                'bg' => $sub->bg_color,
                'text' => $sub->text_color,
                'border' => $sub->border_color,
            ];
        }

        return [
            'inline' => 'background-color: #f3f4f6; color: #374151; border-color: #e5e7eb;',
            'bg' => '#f3f4f6',
            'text' => '#374151',
            'border' => '#e5e7eb',
        ];
    }
}
