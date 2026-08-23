<?php

namespace Database\Seeders;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\RelatedTaskType;
use App\Enums\Substatus;
use App\Models\Designer;
use App\Models\DueDateHistory;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\RelatedTask;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DesignerSeeder::class);

        $euraliz = Designer::where('name', 'Euralíz')->first();
        $adrian = Designer::where('name', 'Adrián')->first();
        $cesar = Designer::where('name', 'César')->first();

        // 1. OVERDUE order in TO DO TODAY (Client delay workflow pending)
        $order1 = Order::create([
            'trello_card_id' => 'card_101',
            'company_name' => 'Restaurante El Fuego',
            'task_name' => 'Menú principal y letrero de neón',
            'designer_id' => $adrian->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::OVERDUE,
            'start_date' => now()->subDays(6)->toDateString(),
            'original_due_date' => now()->subDays(2)->toDateString(),
            'current_due_date' => now()->subDays(2)->toDateString(),
            'scheduled_date' => now()->toDateString(),
            'approved' => false,
            'done_today' => false,
        ]);

        RelatedTask::create([
            'order_id' => $order1->id,
            'title' => 'ENVIAR CORREO DE ATRASO',
            'type' => RelatedTaskType::CORREO_ATRASO,
            'status' => 'todo',
            'assignee_id' => $adrian->id,
            'due_date' => now()->toDateString(),
            'priority' => 'high',
        ]);

        OrderEvent::create([
            'order_id' => $order1->id,
            'event_type' => 'ORDER_CREATED',
            'actor' => 'TrelloSync',
            'new_value' => 'Restaurante El Fuego - Menú principal',
        ]);

        DueDateHistory::create([
            'order_id' => $order1->id,
            'previous_due_date' => null,
            'new_due_date' => now()->subDays(2)->toDateString(),
            'reason' => 'SLA Inicial de 3 días',
            'trigger_event' => 'Card Created',
        ]);

        // 2. ENTRANTE + BLOQUEADA (Missing Measures) -> RESOLVER view
        $order2 = Order::create([
            'trello_card_id' => 'card_102',
            'company_name' => 'Boutique Glamour',
            'task_name' => 'Diseño de aparador y vinil publicitario',
            'designer_id' => $euraliz->id,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => Substatus::BLOQUEADA,
            'blocking_reason' => BlockingReason::FALTAN_MEDIDAS,
            'start_date' => now()->subDays(1)->toDateString(),
            'current_due_date' => now()->addDay()->toDateString(),
            'approved' => true,
            'measures_confirmed' => false,
            'estimate_approved' => true,
        ]);

        RelatedTask::create([
            'order_id' => $order2->id,
            'title' => 'RESOLVER: Medidas pendientes para aparador',
            'type' => RelatedTaskType::RESOLVER,
            'status' => 'todo',
            'assignee_id' => $euraliz->id,
            'due_date' => now()->addDay()->toDateString(),
            'priority' => 'high',
        ]);

        // 3. EURALIZ ORDERS RECEIVED + PONER EN ALTA
        $order3 = Order::create([
            'trello_card_id' => 'card_103',
            'company_name' => 'Café Barista Central',
            'task_name' => 'Branding completo de empaques de café',
            'designer_id' => $euraliz->id,
            'core_status' => CoreStatus::EURALIZ_ORDERS_RECEIVED,
            'substatus' => Substatus::PONER_EN_ALTA,
            'start_date' => now()->subDays(1)->toDateString(),
            'original_due_date' => now()->addDays(2)->toDateString(),
            'current_due_date' => now()->addDays(2)->toDateString(),
            'approved' => true,
            'measures_confirmed' => true,
            'estimate_approved' => true,
        ]);

        // 4. CESAR ORDERS RECEIVED + ALMOST OVERDUE
        $order4 = Order::create([
            'trello_card_id' => 'card_104',
            'company_name' => 'Logística Global Corp',
            'task_name' => 'Rotulación de flotilla vehicular',
            'designer_id' => $cesar->id,
            'core_status' => CoreStatus::CESAR_ORDERS_RECEIVED,
            'substatus' => Substatus::ALMOST_OVERDUE,
            'start_date' => now()->subDays(2)->toDateString(),
            'original_due_date' => now()->addHours(6)->toDateString(),
            'current_due_date' => now()->addHours(6)->toDateString(),
            'approved' => true,
            'measures_confirmed' => true,
            'estimate_approved' => true,
        ]);

        // 5. ENVIADO A CAMILA + FOLLOW UP CAMILA task
        $order5 = Order::create([
            'trello_card_id' => 'card_105',
            'company_name' => 'Clínica Dental Sonrisas',
            'task_name' => 'Acrílico receptor y señalética interna',
            'designer_id' => $adrian->id,
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'substatus' => Substatus::CAMBIOS_CAMILA,
            'start_date' => now()->subDays(3)->toDateString(),
            'current_due_date' => now()->addDay()->toDateString(),
            'approved' => false,
        ]);

        RelatedTask::create([
            'order_id' => $order5->id,
            'title' => 'Follow Up Camila - Revisión de propuesta',
            'type' => RelatedTaskType::FOLLOW_UP_CAMILA,
            'status' => 'todo',
            'assignee_id' => $adrian->id,
            'due_date' => now()->toDateString(),
            'priority' => 'normal',
        ]);

        // 6. ENVIADO AL CLIENTE (Waiting for client response)
        $order6 = Order::create([
            'trello_card_id' => 'card_106',
            'company_name' => 'Inmobiliaria Horizontes',
            'task_name' => 'Lona gigante para torre residencial',
            'designer_id' => $cesar->id,
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'substatus' => Substatus::WAITING_FOR_CLIENT,
            'start_date' => now()->subDays(4)->toDateString(),
            'client_last_response' => now()->subDays(4),
            'approved' => false,
        ]);

        RelatedTask::create([
            'order_id' => $order6->id,
            'title' => 'Follow Up Cliente #1',
            'type' => RelatedTaskType::FOLLOW_UP_CLIENTE,
            'status' => 'todo',
            'assignee_id' => $cesar->id,
            'due_date' => now()->toDateString(),
            'priority' => 'normal',
        ]);

        // 7. ON HOLD + CUSTOMER SERVICE REQUIRED (9+ days no response)
        $order7 = Order::create([
            'trello_card_id' => 'card_107',
            'company_name' => 'Hotel Plaza Marina',
            'task_name' => 'Directorio de huéspedes y tótem exterior',
            'designer_id' => $euraliz->id,
            'core_status' => CoreStatus::ON_HOLD,
            'substatus' => Substatus::NO_RESPUESTA,
            'customer_service_required' => true,
            'start_date' => now()->subDays(12)->toDateString(),
            'pause_reason' => '10 días sin respuesta del cliente tras 3 follow-ups',
        ]);

        // 8. EN PRODUCCIÓN
        $order8 = Order::create([
            'trello_card_id' => 'card_108',
            'company_name' => 'Gimnasio Titan Fitness',
            'task_name' => 'Mural vinílico motivacional 15x3m',
            'designer_id' => $adrian->id,
            'core_status' => CoreStatus::EN_PRODUCCION,
            'substatus' => null,
            'start_date' => now()->subDays(8)->toDateString(),
            'approved' => true,
            'measures_confirmed' => true,
            'estimate_approved' => true,
        ]);
    }
}
