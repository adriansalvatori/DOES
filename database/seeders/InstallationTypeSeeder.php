<?php

namespace Database\Seeders;

use App\Models\InstallationType;
use Illuminate\Database\Seeder;

class InstallationTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            ['name' => 'CLIENTE', 'color' => '#0284C7', 'style_type' => 'solid'],
            ['name' => 'DENT', 'color' => '#7C3AED', 'style_type' => 'solid'],
            ['name' => 'C-MAGNETICO', 'color' => '#475569', 'style_type' => 'solid'],
            ['name' => 'INSTALACION', 'color' => '#059669', 'style_type' => 'solid'],
            ['name' => 'TRAVIS', 'color' => '#DC2626', 'style_type' => 'solid'],
            ['name' => 'KUDOS', 'color' => '#EA580C', 'style_type' => 'solid'],
            ['name' => 'RETIRO', 'color' => '#D97706', 'style_type' => 'solid'],
            ['name' => 'ENVIO CURRIER', 'color' => '#2563EB', 'style_type' => 'solid'],
            ['name' => 'IMPRESION', 'color' => '#0D9488', 'style_type' => 'solid'],
            ['name' => 'MONTAJE', 'color' => '#9333EA', 'style_type' => 'solid'],
            ['name' => 'CORTE', 'color' => '#C026D3', 'style_type' => 'solid'],
            ['name' => 'EMBALAJE', 'color' => '#DB2777', 'style_type' => 'solid'],
            ['name' => 'ENTREGA DIRECTA', 'color' => '#4F46E5', 'style_type' => 'solid'],
            ['name' => 'REVISIÓN MEDIDAS', 'color' => '#57534E', 'style_type' => 'solid'],
            ['name' => 'PRODUCCION EXTERNA', 'color' => '#2563EB', 'style_type' => 'solid'],
        ];

        $sort = 1;
        foreach ($types as $t) {
            $palette = InstallationType::derivePaletteFromColor($t['color'], $t['style_type']);

            InstallationType::updateOrCreate(
                ['name' => $t['name']],
                [
                    'color' => $palette['color'],
                    'style_type' => $palette['style_type'],
                    'bg_color' => $palette['bg_color'],
                    'text_color' => $palette['text_color'],
                    'border_color' => $palette['border_color'],
                    'sort_order' => $sort++,
                    'is_active' => true,
                ]
            );
        }

        InstallationType::clearCache();
    }
}
