<?php

namespace Database\Seeders;

use App\Enums\BlockingReason;
use App\Enums\CoreStatus;
use App\Enums\Substatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Designer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Core catalog data
        $this->call([
            SubstatusSeeder::class,
            SubtaskPresetSeeder::class,
            SystemTaskConfigSeeder::class,
        ]);

        $password = Hash::make('password');

        // 2. Designers (Offline / No real Trello IDs)
        $adrianDesigner = Designer::firstOrCreate(
            ['name' => 'Adrián'],
            ['trello_member_id' => null, 'active' => true]
        );

        $camilaDesigner = Designer::firstOrCreate(
            ['name' => 'Camila'],
            ['trello_member_id' => null, 'active' => true]
        );

        $cesarDesigner = Designer::firstOrCreate(
            ['name' => 'César'],
            ['trello_member_id' => null, 'active' => true]
        );

        // 3. Demo Users for the 4 roles
        // Admin
        User::updateOrCreate(
            ['email' => 'admin@kudos.com'],
            [
                'name' => 'Administrador Demo',
                'password' => $password,
                'role' => UserRole::ADMIN,
                'active' => true,
            ]
        );

        // Manager (Coordinator / PM)
        $managerUser = User::updateOrCreate(
            ['email' => 'camila@kudos.com'],
            [
                'name' => 'Camila PM (Demo)',
                'password' => $password,
                'role' => UserRole::COORDINATOR,
                'active' => true,
            ]
        );
        $camilaDesigner->update(['user_id' => $managerUser->id]);

        // Designer
        $designerUser = User::updateOrCreate(
            ['email' => 'adrian@kudos.com'],
            [
                'name' => 'Adrián Diseñador (Demo)',
                'password' => $password,
                'role' => UserRole::DESIGNER,
                'active' => true,
            ]
        );
        $adrianDesigner->update(['user_id' => $designerUser->id]);

        // Comercial (Sales)
        User::updateOrCreate(
            ['email' => 'ventas@kudos.com'],
            [
                'name' => 'Ventas Comercial (Demo)',
                'password' => $password,
                'role' => UserRole::SALES,
                'active' => true,
            ]
        );

        // 4. Demo Clients
        $clientTech = Client::firstOrCreate(
            ['name' => 'TECH SOLUTIONS CORP'],
            ['website' => 'https://techsolutions.demo']
        );

        $clientFood = Client::firstOrCreate(
            ['name' => 'RESTAURANTE EL PORTÓN'],
            ['website' => 'https://elporton.demo']
        );

        $clientDental = Client::firstOrCreate(
            ['name' => 'CLÍNICA DENTAL SONRISAS'],
            ['website' => 'https://dentalsonrisas.demo']
        );

        $clientCafe = Client::firstOrCreate(
            ['name' => 'CAFÉ GOURMET ARTESANAL'],
            ['website' => 'https://cafegourmet.demo']
        );

        // 5. Distinct Demo Orders (All completely disconnected from Trello: trello_card_id = null)
        // Order 1: Entrante - Por Asignar
        Order::create([
            'wo_number' => 'WO-DEMO-001',
            'company_name' => $clientTech->name,
            'client_id' => $clientTech->id,
            'task_name' => 'Branding Corporativo y Material POP 2026',
            'location_name' => 'Torre Financiera Piso 14',
            'designer_id' => null,
            'core_status' => CoreStatus::ENTRANTE,
            'substatus' => null,
            'trello_card_id' => null,
            'in_workspace' => true,
            'measures_confirmed' => false,
            'estimate_approved' => false,
            'start_date' => now()->toDateString(),
            'current_due_date' => now()->addDays(3)->toDateString(),
        ]);

        // Order 2: To Do Today - En proceso con diseñador
        Order::create([
            'wo_number' => 'WO-DEMO-002',
            'company_name' => $clientFood->name,
            'client_id' => $clientFood->id,
            'task_name' => 'Letrero Acrílico 3D Iluminado LED y Menú',
            'location_name' => 'Sede Principal Centro Histórico',
            'designer_id' => $adrianDesigner->id,
            'core_status' => CoreStatus::TO_DO_TODAY,
            'substatus' => Substatus::ALMOST_OVERDUE,
            'trello_card_id' => null,
            'in_workspace' => true,
            'measures_confirmed' => true,
            'estimate_approved' => true,
            'approved' => true,
            'start_date' => now()->subDays(2)->toDateString(),
            'original_due_date' => now()->addDay()->toDateString(),
            'current_due_date' => now()->addDay()->toDateString(),
            'scheduled_date' => now()->toDateString(),
        ]);

        // Order 3: Enviado a Camila - Revisión interna
        Order::create([
            'wo_number' => 'WO-DEMO-003',
            'company_name' => $clientDental->name,
            'client_id' => $clientDental->id,
            'task_name' => 'Señalética Direccional y Viniles Esmerilados',
            'location_name' => 'Consultorios Médicos Médica Sur',
            'designer_id' => $adrianDesigner->id,
            'core_status' => CoreStatus::ENVIADO_A_CAMILA,
            'substatus' => Substatus::CAMBIOS_CAMILA,
            'trello_card_id' => null,
            'in_workspace' => true,
            'measures_confirmed' => true,
            'estimate_approved' => true,
            'approved' => false,
            'start_date' => now()->subDays(3)->toDateString(),
            'current_due_date' => now()->addDays(2)->toDateString(),
        ]);

        // Order 4: Enviado al Cliente - Esperando aprobación
        Order::create([
            'wo_number' => 'WO-DEMO-004',
            'company_name' => $clientCafe->name,
            'client_id' => $clientCafe->id,
            'task_name' => 'Diseño de Empaques Biodegradables para Café',
            'location_name' => 'Planta de Tostado y Empaque',
            'designer_id' => $cesarDesigner->id,
            'core_status' => CoreStatus::ENVIADO_AL_CLIENTE,
            'substatus' => Substatus::WAITING_FOR_CLIENT,
            'trello_card_id' => null,
            'in_workspace' => true,
            'measures_confirmed' => true,
            'estimate_approved' => true,
            'approved' => false,
            'start_date' => now()->subDays(4)->toDateString(),
            'current_due_date' => now()->addDays(4)->toDateString(),
            'client_last_response' => now()->subDays(1),
        ]);

        // Order 5: On Hold - Bloqueada por falta de medidas
        Order::create([
            'wo_number' => 'WO-DEMO-005',
            'company_name' => $clientTech->name,
            'client_id' => $clientTech->id,
            'task_name' => 'Rotulación Monumental Fachada Vidrio',
            'location_name' => 'Campus Tecnológico Norte',
            'designer_id' => $adrianDesigner->id,
            'core_status' => CoreStatus::ON_HOLD,
            'substatus' => Substatus::BLOQUEADA,
            'blocking_reason' => BlockingReason::FALTAN_MEDIDAS,
            'trello_card_id' => null,
            'in_workspace' => true,
            'measures_confirmed' => false,
            'estimate_approved' => true,
            'start_date' => now()->subDays(5)->toDateString(),
            'pause_reason' => 'Esperando confirmación técnica de dimensiones de ventanales.',
        ]);

        // Order 6: En Producción - Orden aprobada
        Order::create([
            'wo_number' => 'WO-DEMO-006',
            'company_name' => $clientFood->name,
            'client_id' => $clientFood->id,
            'task_name' => 'Cajas de Luz y Tótem de Bienvenida',
            'location_name' => 'Sucursal Plaza Marina',
            'designer_id' => $adrianDesigner->id,
            'core_status' => CoreStatus::EN_PRODUCCION,
            'substatus' => null,
            'trello_card_id' => null,
            'in_workspace' => true,
            'measures_confirmed' => true,
            'estimate_approved' => true,
            'approved' => true,
            'start_date' => now()->subDays(6)->toDateString(),
            'current_due_date' => now()->addDay()->toDateString(),
        ]);
    }
}
