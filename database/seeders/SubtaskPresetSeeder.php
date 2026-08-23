<?php

namespace Database\Seeders;

use App\Models\SubtaskPreset;
use Illuminate\Database\Seeder;

class SubtaskPresetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $presets = [
            [
                'title' => 'Revisiones cliente',
                'emoji' => 'message-square',
                'color_theme' => 'sky',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Ajustes Camila',
                'emoji' => 'user-check',
                'color_theme' => 'purple',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'Nueva propuesta',
                'emoji' => 'sparkles',
                'color_theme' => 'emerald',
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'Confirmar medidas',
                'emoji' => 'ruler',
                'color_theme' => 'amber',
                'sort_order' => 4,
                'is_active' => true,
            ],
        ];

        foreach ($presets as $preset) {
            SubtaskPreset::firstOrCreate(
                ['title' => $preset['title']],
                $preset
            );
        }
    }
}
