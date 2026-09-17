<?php

namespace App\Support;

class AppBehaviorsDocs
{
    /**
     * Devuelve las categorías y artículos de la documentación de comportamientos de la app.
     * Esta estructura modular permite actualizar fácilmente las explicaciones cuando hay cambios en el sistema.
     *
     * @return array<string, mixed>
     */
    public static function getCategories(): array
    {
        return [
            'lifecycle' => [
                'id' => 'lifecycle',
                'title' => __('Flujo de Vida de las Tarjetas'),
                'icon' => 'git-commit',
                'description' => __('Aprende cómo avanza una orden desde que entra al sistema hasta que es archivada.'),
                'badge' => 'Core Flow',
                'articles' => [
                    [
                        'id' => 'card-flow-steps',
                        'title' => __('Etapas del Flujo de una Orden (Core Status)'),
                        'summary' => __('El camino principal que recorre cada tarjeta en el panel Kanban y Trello.'),
                        'steps' => [
                            [
                                'name' => __('1. Entrante (BLOCKED)'),
                                'badge' => 'ENTRANTE',
                                'badge_style' => 'bg-orange-50 text-orange-700 border-orange-200',
                                'desc' => __('Tarjetas nuevas que ingresan al sistema. Suelen requerir revisión inicial antes de asignarse a la cola de un diseñador.'),
                            ],
                            [
                                'name' => __('2. Asignada a Diseñador (Orders Received)'),
                                'badge' => 'RECIBIDAS',
                                'badge_style' => 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
                                'desc' => __('Listas equivalentes a PENDIENTE por diseñador (Euralíz, Adrián o César). La tarjeta ya tiene responsable pero está en cola.'),
                            ],
                            [
                                'name' => __('3. Trabajando Hoy (To Do Today)'),
                                'badge' => 'TO DO TODAY',
                                'badge_style' => 'bg-amber-50 text-amber-800 border-amber-300',
                                'desc' => __('Tarjetas seleccionadas activamente para trabajarse durante la jornada de hoy. Se destacan visualmente.'),
                            ],
                            [
                                'name' => __('4. En Producción'),
                                'badge' => 'EN PRODUCCIÓN',
                                'badge_style' => 'bg-pink-50 text-pink-700 border-pink-200',
                                'desc' => __('La orden ha pasado a la fase física/final de producción. El workspace de la tarjeta se mantiene activo.'),
                            ],
                            [
                                'name' => __('5. Revisión Interna / Cliente'),
                                'badge' => 'REVISIÓN',
                                'badge_style' => 'bg-purple-50 text-purple-700 border-purple-200',
                                'desc' => __('Enviado a Camila (supervisión interna) o Enviado al Cliente. Puede recibir subestatus de Cambios.'),
                            ],
                            [
                                'name' => __('6. Archivada'),
                                'badge' => 'ARCHIVED',
                                'badge_style' => 'bg-stone-100 text-stone-600 border-stone-200',
                                'desc' => __('Orden completada con éxito. Sale de las vistas operativas activas y se guarda en el archivo histórico.'),
                            ],
                        ],
                        'tip' => __('Las tarjetas en Trello y Kudos se mantienen 100% sincronizadas. Mover una tarjeta de columna en Trello actualiza su Core Status automáticamente.'),
                    ],
                ],
            ],
            'subtasks' => [
                'id' => 'subtasks',
                'title' => __('Subtareas y Planificador Semanal'),
                'icon' => 'list-checks',
                'description' => __('Comportamiento de las subtareas al agendarlas para hoy y su impacto en el avance.'),
                'badge' => 'Planificación',
                'articles' => [
                    [
                        'id' => 'subtasks-today',
                        'title' => __('¿Qué ocurre al poner una Subtarea para "Hoy"?'),
                        'summary' => __('Acción del usuario al marcar o agendar una subtarea con la fecha del día actual.'),
                        'points' => [
                            __('Visibilidad en el Planificador Semanal: La subtarea aparece inmediatamente en la columna de HOY dentro del módulo Planificador Semanal.'),
                            __('Indicador en la Tarjeta: La orden mostrará la cantidad de subtareas programadas para hoy directamente en sus tarjetas del Kanban y Backlog.'),
                            __('Priorización Automática: Eleva la prioridad visual de la orden para que el diseñador la tenga en su radar de ejecuciones del día.'),
                            __('Cálculo de Progreso: Al marcar la subtarea como completada, se actualiza el porcentaje de progreso general de la orden (ej: 3/5 completadas = 60%).'),
                        ],
                        'tip' => __('Puedes utilizar Plantillas de Subtareas desde Configuración > Plantillas Subtareas para precargar listas estándar de tareas según el tipo de trabajo.'),
                    ],
                    [
                        'id' => 'subtask-weekly-limits',
                        'title' => __('Límites y Organización Semanal'),
                        'summary' => __('Cómo se distribuyen y controlan las subtareas entre los diseñadores.'),
                        'points' => [
                            __('Asignación por Diseñador: Cada subtarea queda asociada a la orden y al diseñador asignado a la misma.'),
                            __('Subtareas en Espera: Si una orden está pausada o en revisión de cliente, las subtareas se mantienen pero no cuentan como pendientes activas de hoy.'),
                        ],
                    ],
                ],
            ],
            'substatuses' => [
                'id' => 'substatuses',
                'title' => __('Subestatus y Condición Cruzada'),
                'icon' => 'tags',
                'description' => __('Comportamiento de las alertas, banderas de urgencia y tiempos de entrega.'),
                'badge' => 'Estados',
                'articles' => [
                    [
                        'id' => 'substatus-behavior',
                        'title' => __('Diferencia entre Estado Principal y Subestatus'),
                        'summary' => __('Los subestatus son condiciones transversales que pueden aplicarse independientemente del estado principal.'),
                        'examples' => [
                            [
                                'substatus' => 'OVERDUE',
                                'badge' => 'OVERDUE',
                                'badge_style' => 'bg-red-50 text-red-700 border-red-200',
                                'desc' => __('Se activa automáticamente cuando la fecha límite (Due Date) de la orden ya expiró y aún no ha sido completada/archivada.'),
                            ],
                            [
                                'substatus' => 'ALMOST OVERDUE',
                                'badge' => 'Casi Vencida',
                                'badge_style' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'desc' => __('Se activa automáticamente cuando faltan menos de 24 horas para la fecha de entrega.'),
                            ],
                            [
                                'substatus' => 'URGENTE',
                                'badge' => 'URGENTE',
                                'badge_style' => 'bg-red-600 text-white border-red-700 font-extrabold animate-pulse',
                                'desc' => __('Indicador de alta prioridad visual. Parpadea en rojo para alertar a todo el equipo.'),
                            ],
                            [
                                'substatus' => 'BLOQUEADA / NO RESPUESTA',
                                'badge' => 'Bloqueada',
                                'badge_style' => 'bg-orange-50 text-orange-700 border-orange-200',
                                'desc' => __('La orden no puede avanzar porque falta información del cliente, aprobación de estimado o medidas.'),
                            ],
                            [
                                'substatus' => 'CAMBIOS CLIENTE / CAMBIOS CAMILA',
                                'badge' => 'Cambios Cliente',
                                'badge_style' => 'bg-sky-50 text-sky-700 border-sky-200',
                                'desc' => __('Se aplica cuando la tarjeta fue devuelta desde revisión con observaciones que deben corregirse.'),
                            ],
                        ],
                        'tip' => __('Puedes administrar y crear nuevos subestatus personalizados en el menú Configuración > Subestatus.'),
                    ],
                ],
            ],
            'trello_sync' => [
                'id' => 'trello_sync',
                'title' => __('Sincronización con Trello y Mapeos'),
                'icon' => 'refresh-cw',
                'description' => __('Funcionamiento de la sincronización bidireccional y control de pausas.'),
                'badge' => 'Integración',
                'articles' => [
                    [
                        'id' => 'sync-rules',
                        'title' => __('Reglas de Sincronización Trello <-> Kudos'),
                        'summary' => __('Cómo se comunican Trello y la aplicación para mantener la información actualizada.'),
                        'points' => [
                            __('Movimiento de Listas: Mover una tarjeta en Trello actualiza inmediatamente el Core Status en Kudos según el Mapeo de Listas.'),
                            __('Edición de Datos: Cambios en el título, diseñador o fecha límite se reflejan automáticamente.'),
                            __('Checklists: Los ítems de checklist en Trello se sincronizan como subtareas dentro de la orden.'),
                            __('Pausa Global de Sync: El interruptor en el sidebar inferior permite congelar la sincronización cuando se realizan limpiezas o cambios masivos.'),
                        ],
                        'tip' => __('Si cambias los nombres de tus listas en Trello, asegúrate de actualizar la correspondencia en Configuración > Mapeo Listas Trello.'),
                    ],
                ],
            ],
            'resolver' => [
                'id' => 'resolver',
                'title' => __('Sección Action Required (Resolver)'),
                'icon' => 'alert-triangle',
                'description' => __('Manejo de tarjetas que requieren atención manual o corrección de datos.'),
                'badge' => 'Alertas',
                'articles' => [
                    [
                        'id' => 'action-required-trigger',
                        'title' => __('¿Cuándo entra una orden a "Action Required"?'),
                        'summary' => __('Situaciones excepcionales donde el sistema solicita intervención humana.'),
                        'points' => [
                            __('Lista Trello Desconocida: Si una tarjeta se mueve a una lista que no está mapeada en Configuración.'),
                            __('Sin Diseñador Asignado en fase activa: Cuando una orden entra a trabajo diario sin tener un responsable definido.'),
                            __('Discrepancia de Estatus: Conflictos de sincronización entre Trello y la base de datos local.'),
                        ],
                        'tip' => __('El número en el indicador naranja del menú lateral te mostrará cuántas órdenes requieren atención en todo momento.'),
                    ],
                ],
            ],
            'trash' => [
                'id' => 'trash',
                'title' => __('Papelera y Respaldos'),
                'icon' => 'database',
                'description' => __('Seguridad de datos, recuperación de órdenes eliminadas y respaldos.'),
                'badge' => 'Sistema',
                'articles' => [
                    [
                        'id' => 'trash-backup-behavior',
                        'title' => __('Recuperación de Órdenes y Respaldos'),
                        'summary' => __('Protección contra pérdidas accidental de información.'),
                        'points' => [
                            __('Papelera de Reciclaje: Eliminar una orden no la borra de forma definitiva. Se mueve a /trash donde puede ser restaurada.'),
                            __('Eliminación Permanente: Solo la acción explícita dentro de la Papelera elimina los datos definitivamente de la base de datos.'),
                            __('Respaldos Automáticos: En Configuración > Respaldos puedes generar snapshots completos de la base de datos de la aplicación.'),
                        ],
                    ],
                ],
            ],
        ];
    }
}
