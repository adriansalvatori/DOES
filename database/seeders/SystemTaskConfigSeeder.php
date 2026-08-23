<?php

namespace Database\Seeders;

use App\Enums\RelatedTaskType;
use App\Models\SystemTaskConfig;
use Illuminate\Database\Seeder;

class SystemTaskConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $configs = [
            [
                'task_type' => RelatedTaskType::BIENVENIDA->value,
                'title' => 'Correo de Bienvenida',
                'category' => 'Cliente',
                'description' => 'Notificación inicial enviada o programada al cliente para confirmar la recepción limpia de la orden e inicio del proceso.',
                'is_active' => true,
            ],
            [
                'task_type' => RelatedTaskType::SOLICITAR_INFO->value,
                'title' => 'Solicitud de Información',
                'category' => 'Cliente',
                'description' => 'Tarea de seguimiento activada cuando se requiere información, logotipos en alta resolución o textos pendientes por parte del cliente.',
                'is_active' => true,
            ],
            [
                'task_type' => RelatedTaskType::FOLLOW_UP_CLIENTE->value,
                'title' => 'Follow Up Cliente',
                'category' => 'Cliente',
                'description' => 'Recordatorios automáticos y manuales para dar seguimiento a propuestas o avances enviados al cliente pendientes de respuesta.',
                'is_active' => true,
            ],
            [
                'task_type' => RelatedTaskType::FOLLOW_UP_CAMILA->value,
                'title' => 'Revisiones Camila',
                'category' => 'Interno',
                'description' => 'Asignación de control de calidad interno y revisión de diseño realizada por Camila antes de la entrega final.',
                'is_active' => true,
            ],
            [
                'task_type' => RelatedTaskType::CORREO_ATRASO->value,
                'title' => 'Correo de Atraso',
                'category' => 'Cliente',
                'description' => 'Notificación formal enviada al cliente cuando la fecha límite (SLA) se replantea por cambios de alcance o revisiones extensas.',
                'is_active' => true,
            ],
            [
                'task_type' => RelatedTaskType::RESOLVER->value,
                'title' => 'Intervención / Resolver',
                'category' => 'Resolver',
                'description' => 'Alerta y tarea de intervención prioritaria asignada a Manager/Admin cuando una orden está bloqueada u ostenta un motivo de detención.',
                'is_active' => true,
            ],
            [
                'task_type' => RelatedTaskType::PONER_ALTA->value,
                'title' => 'Poner en ALTA',
                'category' => 'Producción',
                'description' => 'Acción operativa requerida para pasar los artes aprobados al estado de producción final en taller.',
                'is_active' => true,
            ],
            [
                'task_type' => RelatedTaskType::FOLLOW_UP_ALTA->value,
                'title' => 'Follow Up ALTA',
                'category' => 'Producción',
                'description' => 'Verificación posterior a la puesta en ALTA para monitorear el avance y entrega limpia en el taller de impresión.',
                'is_active' => true,
            ],
            [
                'task_type' => RelatedTaskType::AJUSTES_PRODUCCION->value,
                'title' => 'Ajustes de Producción',
                'category' => 'Producción',
                'description' => 'Correcciones técnicas de archivos (sangrías, perfiles de color, troqueles) solicitadas directamente por el área de producción.',
                'is_active' => true,
            ],
        ];

        foreach ($configs as $config) {
            SystemTaskConfig::firstOrCreate(
                ['task_type' => $config['task_type']],
                $config
            );
        }
    }
}
