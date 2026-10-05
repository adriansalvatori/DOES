<?php

use App\Models\InstallationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure common installation types exist and have solid, vibrant custom badge styles
        $installationTypesData = [
            ['name' => 'CLIENTE', 'color' => '#0284C7', 'style_type' => 'solid'],
            ['name' => 'KUDOS', 'color' => '#059669', 'style_type' => 'solid'],
            ['name' => 'ENVIAR CURRIER', 'color' => '#0284C7', 'style_type' => 'solid'],
            ['name' => 'INSTALACION', 'color' => '#059669', 'style_type' => 'solid'],
            ['name' => 'BUSCAR PICASSO', 'color' => '#6366F1', 'style_type' => 'solid'],
            ['name' => '4OVER', 'color' => '#0891B2', 'style_type' => 'solid'],
            ['name' => 'INTERPRINT', 'color' => '#2563EB', 'style_type' => 'solid'],
            ['name' => '4IMPRINT', 'color' => '#9333EA', 'style_type' => 'solid'],
            ['name' => 'PRIMA', 'color' => '#C026D3', 'style_type' => 'solid'],
            ['name' => 'DENT', 'color' => '#7C3AED', 'style_type' => 'solid'],
            ['name' => 'TRAVIS', 'color' => '#E11D48', 'style_type' => 'solid'],
            ['name' => 'C-MAGNETIC', 'color' => '#475569', 'style_type' => 'solid'],
            ['name' => 'FALTA ALGO', 'color' => '#EA580C', 'style_type' => 'solid'],
            ['name' => 'ENTREGA PARCIAL', 'color' => '#CA8A04', 'style_type' => 'solid'],
            ['name' => 'JHONAL', 'color' => '#0D9488', 'style_type' => 'solid'],
            ['name' => 'BUSCAR FEDEX-ALPHA', 'color' => '#D97706', 'style_type' => 'solid'],
        ];

        foreach ($installationTypesData as $index => $item) {
            $palette = InstallationType::derivePaletteFromColor($item['color'], $item['style_type']);

            DB::table('installation_types')->updateOrInsert(
                ['name' => $item['name']],
                [
                    'color' => $palette['color'],
                    'style_type' => $palette['style_type'],
                    'bg_color' => $palette['bg_color'],
                    'text_color' => $palette['text_color'],
                    'border_color' => $palette['border_color'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'updated_at' => now(),
                ]
            );
        }

        InstallationType::clearCache();

        // 2. Correct categories and settings for subtask presets so actions trigger properly on check
        $subtaskPresetsCategoryMapping = [
            'Ajustes cliente' => ['category' => 'client_adjustments', 'color_theme' => 'sky', 'is_work_task' => true],
            'Ajustes Camila' => ['category' => 'camila_adjustments', 'color_theme' => 'purple', 'is_work_task' => true],
            'Poner en ALTA' => ['category' => 'production_adjustments', 'color_theme' => 'purple', 'is_work_task' => true],
            'Enviar Proof cliente' => ['category' => 'client_adjustments', 'color_theme' => 'sky', 'is_work_task' => false],
            'Solicitar información al cliente' => ['category' => 'client_adjustments', 'color_theme' => 'sky', 'is_work_task' => false],
            'Follow Up Camila' => ['category' => 'management', 'color_theme' => 'purple', 'is_work_task' => false],
            'Follow Up Cliente' => ['category' => 'management', 'color_theme' => 'sky', 'is_work_task' => false],
            'Confirmar medidas' => ['category' => 'management', 'color_theme' => 'amber', 'is_work_task' => false],
            'Revisar Medidas Recibidas' => ['category' => 'management', 'color_theme' => 'amber', 'is_work_task' => true],
            'Solicitar Medidas Survey' => ['category' => 'management', 'color_theme' => 'amber', 'is_work_task' => true],
            'Responder Correo' => ['category' => 'management', 'color_theme' => 'sky', 'is_work_task' => false],
            'Nueva propuesta' => ['category' => 'new_design', 'color_theme' => 'emerald', 'is_work_task' => true],
            'First Proposal' => ['category' => 'new_design', 'color_theme' => 'emerald', 'is_work_task' => true],
            'KV Proposal' => ['category' => 'new_design', 'color_theme' => 'emerald', 'is_work_task' => true],
            'Digitalización' => ['category' => 'new_design', 'color_theme' => 'emerald', 'is_work_task' => true],
            'Revisar File de cliente' => ['category' => 'new_design', 'color_theme' => 'amber', 'is_work_task' => true],
        ];

        foreach ($subtaskPresetsCategoryMapping as $title => $data) {
            DB::table('subtask_presets')
                ->where('title', $title)
                ->update([
                    'category' => $data['category'],
                    'color_theme' => $data['color_theme'],
                    'is_work_task' => $data['is_work_task'],
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert style types if needed
    }
};
