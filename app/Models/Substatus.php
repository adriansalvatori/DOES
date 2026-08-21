<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Substatus extends Model
{
    use HasFactory;

    protected $table = 'substatuses';

    protected $fillable = [
        'name',
        'color',
        'style_type',
        'bg_color',
        'text_color',
        'border_color',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function getInlineBadgeStyleAttribute(): string
    {
        return "background-color: {$this->bg_color}; color: {$this->text_color}; border-color: {$this->border_color};";
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

        $sub = static::where('name', $name)->first();
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
