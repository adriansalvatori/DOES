<?php

namespace Database\Seeders;

use App\Models\Substatus;
use Illuminate\Database\Seeder;

class SubstatusSeeder extends Seeder
{
    public function run(): void
    {
        $substatuses = [
            [
                'name' => 'URGENTE',
                'bg_color' => '#DC2626',
                'text_color' => '#FFFFFF',
                'border_color' => '#B91C1C',
                'is_system' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'BLOQUEADA',
                'bg_color' => '#FFF7ED',
                'text_color' => '#C2410C',
                'border_color' => '#FFEDD5',
                'is_system' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'OVERDUE',
                'bg_color' => '#FEF2F2',
                'text_color' => '#B91C1C',
                'border_color' => '#FECACA',
                'is_system' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'ALMOST OVERDUE',
                'bg_color' => '#FFFBEB',
                'text_color' => '#B45309',
                'border_color' => '#FDE68A',
                'is_system' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'CAMBIOS CAMILA',
                'bg_color' => '#FAF5FF',
                'text_color' => '#7E22CE',
                'border_color' => '#E9D5FF',
                'is_system' => false,
                'sort_order' => 5,
            ],
            [
                'name' => 'CAMBIOS CLIENTE',
                'bg_color' => '#F0F9FF',
                'text_color' => '#0369A1',
                'border_color' => '#BAE6FD',
                'is_system' => false,
                'sort_order' => 6,
            ],
            [
                'name' => 'PONER EN ALTA',
                'bg_color' => '#FDF2F8',
                'text_color' => '#BE185D',
                'border_color' => '#FBCFE8',
                'is_system' => false,
                'sort_order' => 7,
            ],
            [
                'name' => 'FALTA APROBACIÓN DE ESTIMADO',
                'bg_color' => '#FFF7ED',
                'text_color' => '#C2410C',
                'border_color' => '#FFEDD5',
                'is_system' => false,
                'sort_order' => 8,
            ],
            [
                'name' => 'NO RESPUESTA',
                'bg_color' => '#F5F5F4',
                'text_color' => '#57534E',
                'border_color' => '#E7E5E4',
                'is_system' => false,
                'sort_order' => 9,
            ],
            [
                'name' => 'PAUSADO',
                'bg_color' => '#F5F5F4',
                'text_color' => '#57534E',
                'border_color' => '#E7E5E4',
                'is_system' => false,
                'sort_order' => 10,
            ],
            [
                'name' => 'AJUSTES DE PRODUCCIÓN',
                'bg_color' => '#FDF2F8',
                'text_color' => '#BE185D',
                'border_color' => '#FBCFE8',
                'is_system' => false,
                'sort_order' => 11,
            ],
            [
                'name' => 'WAITING FOR CLIENT',
                'bg_color' => '#EFF6FF',
                'text_color' => '#1D4ED8',
                'border_color' => '#BFDBFE',
                'is_system' => false,
                'sort_order' => 12,
            ],
            [
                'name' => 'CUSTOMER SERVICE REQUIRED',
                'bg_color' => '#FDF2F8',
                'text_color' => '#BE185D',
                'border_color' => '#FBCFE8',
                'is_system' => false,
                'sort_order' => 13,
            ],
            [
                'name' => 'ENVIADO EN ALTA',
                'bg_color' => '#FDF2F8',
                'text_color' => '#BE185D',
                'border_color' => '#FBCFE8',
                'is_system' => false,
                'sort_order' => 14,
            ],
        ];

        foreach ($substatuses as $data) {
            Substatus::updateOrCreate(['name' => $data['name']], $data);
        }
    }
}
