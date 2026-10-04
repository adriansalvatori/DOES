<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('installation_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 20)->nullable();
            $table->string('style_type', 20)->default('light'); // 'light' or 'solid'
            $table->string('bg_color', 100)->nullable();
            $table->string('text_color', 100)->nullable();
            $table->string('border_color', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $derivePalette = function (string $hex, string $styleType = 'light'): array {
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

            return [
                'color' => '#'.$hex,
                'style_type' => 'light',
                'bg_color' => "hsl({$h}, ".max($s, 70).'%, 96%)',
                'text_color' => "hsl({$h}, ".max($s, 80).'%, 30%)',
                'border_color' => "hsl({$h}, ".max($s, 70).'%, 86%)',
            ];
        };

        $initialTypes = [
            ['name' => 'ENVIAR CURRIER', 'color' => '#0284C7'],
            ['name' => 'INSTALACION', 'color' => '#059669'],
            ['name' => 'BUSCAR PICASSO', 'color' => '#6366F1'],
            ['name' => '4OVER', 'color' => '#0891B2'],
            ['name' => 'INTERPRINT', 'color' => '#2563EB'],
            ['name' => '4IMPRINT', 'color' => '#9333EA'],
            ['name' => 'PRIMA', 'color' => '#C026D3'],
            ['name' => 'DENT', 'color' => '#7C3AED'],
            ['name' => 'TRAVIS', 'color' => '#E11D48'],
            ['name' => 'C-MAGNETIC', 'color' => '#475569'],
            ['name' => 'FALTA ALGO', 'color' => '#EA580C'],
            ['name' => 'ENTREGA PARCIAL', 'color' => '#CA8A04'],
            ['name' => 'JHONAL', 'color' => '#0D9488'],
            ['name' => 'BUSCAR FEDEX-ALPHA', 'color' => '#D97706'],
        ];

        foreach ($initialTypes as $index => $item) {
            $palette = $derivePalette($item['color'], 'light');
            DB::table('installation_types')->insert([
                'name' => $item['name'],
                'color' => $palette['color'],
                'style_type' => $palette['style_type'],
                'bg_color' => $palette['bg_color'],
                'text_color' => $palette['text_color'],
                'border_color' => $palette['border_color'],
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installation_types');
    }
};
