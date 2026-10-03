<?php

namespace App\Console\Commands;

use App\Models\Designer;
use App\Models\Order;
use Illuminate\Console\Command;

class AuditDesignersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'designers:audit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auditar desalineaciones entre designer_id, pivote designer_order y CoreStatus de órdenes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando auditoría de diseñadores...');

        // 1. Verificar diseñador Lead
        $leads = Designer::where('is_lead', true)->get();
        if ($leads->count() === 0) {
            $this->error('ALERTA: No hay ningún Lead Designer configurado en el sistema.');
        } elseif ($leads->count() > 1) {
            $this->warn("ADVERTENCIA: Se encontraron múltiples Lead Designers ({$leads->count()}).");
        } else {
            $this->info("Lead Designer activo: {$leads->first()->name}");
        }

        // 2. Verificar diseñadores sin slug
        $missingSlug = Designer::whereNull('slug')->get();
        if ($missingSlug->isNotEmpty()) {
            $this->warn("Se encontraron {$missingSlug->count()} diseñadores sin slug. Ejecuta 'php artisan designers:optimize'.");
        } else {
            $this->info('Todos los diseñadores tienen slug configurado.');
        }

        // 3. Auditar desalineaciones de órdenes en cola de recepción
        $designers = Designer::whereNotNull('queue_status_value')->get();
        $misalignedCount = 0;

        foreach ($designers as $designer) {
            $queueStatus = $designer->getQueueStatus();
            $wrongOrders = Order::where('core_status', $queueStatus)
                ->where(function ($q) use ($designer) {
                    $q->where('designer_id', '!=', $designer->id)
                        ->orWhereNull('designer_id');
                })
                ->count();

            if ($wrongOrders > 0) {
                $this->warn("  {$wrongOrders} órdenes en cola '{$queueStatus->value}' no están asignadas al diseñador '{$designer->name}'.");
                $misalignedCount += $wrongOrders;
            }
        }

        if ($misalignedCount === 0) {
            $this->info('Todas las órdenes en colas de recepción están correctamente alineadas con sus diseñadores.');
        }

        // 4. Auditar órdenes con diseñadores en tabla pivote pero designer_id nulo
        $missingDesignerId = Order::whereNull('designer_id')->whereHas('designers')->count();
        if ($missingDesignerId > 0) {
            $this->warn("ADVERTENCIA: {$missingDesignerId} órdenes tienen diseñador en tabla pivote pero 'designer_id' nulo.");
        } else {
            $this->info('Todas las órdenes con diseñadores en tabla pivote tienen su primary designer_id asignado.');
        }

        return Command::SUCCESS;
    }
}
