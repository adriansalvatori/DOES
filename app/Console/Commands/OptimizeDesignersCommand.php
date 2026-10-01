<?php

namespace App\Console\Commands;

use App\Enums\CoreStatus;
use App\Models\Designer;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class OptimizeDesignersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'designers:optimize {--force : Sobrescribir datos sin solicitar confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poblar metadatos de diseñadores (slugs, alias, colores, lead) y eliminar el hardcoding en base de datos.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando optimización de metadatos de diseñadores...');

        $defaults = [
            'Euralíz' => [
                'slug' => 'euraliz',
                'color_type' => 'magenta',
                'hex_color' => '#F3A8FF',
                'is_lead' => true,
                'is_external' => false,
                'queue_status_value' => CoreStatus::EURALIZ_ORDERS_RECEIVED->value,
                'aliases' => ['euraliz', 'euralíz', 'bravo', 'euraliz bravo'],
            ],
            'César' => [
                'slug' => 'cesar',
                'color_type' => 'cyan',
                'hex_color' => '#52EAFD',
                'is_lead' => false,
                'is_external' => false,
                'queue_status_value' => CoreStatus::CESAR_ORDERS_RECEIVED->value,
                'aliases' => ['cesar', 'césar', 'guzman', 'guzmán', 'cesar guzman'],
            ],
            'Adrián' => [
                'slug' => 'adrian',
                'color_type' => 'emerald',
                'hex_color' => '#5FE9B5',
                'is_lead' => false,
                'is_external' => false,
                'queue_status_value' => CoreStatus::ADRIAN_ORDERS_RECEIVED->value,
                'aliases' => ['adrian', 'adrián', 'reinoza', 'adrian reinosa', 'adrian reinose'],
            ],
        ];

        $designers = Designer::all();

        if ($designers->isEmpty()) {
            $this->warn('No se encontraron diseñadores en la base de datos. Creando diseñadores iniciales...');
            foreach ($defaults as $name => $data) {
                Designer::create(array_merge(['name' => $name, 'active' => true], $data));
                $this->line("  + Creado diseñador: {$name}");
            }
            $designers = Designer::all();
        }

        foreach ($designers as $designer) {
            $matchKey = collect(array_keys($defaults))->first(function ($name) use ($designer) {
                return str_contains(mb_strtolower($designer->name), mb_strtolower(substr($name, 0, 4)));
            });

            if ($matchKey && isset($defaults[$matchKey])) {
                $data = $defaults[$matchKey];
                $designer->update([
                    'slug' => $data['slug'],
                    'color_type' => $data['color_type'],
                    'hex_color' => $data['hex_color'],
                    'is_lead' => $data['is_lead'],
                    'is_external' => $data['is_external'],
                    'queue_status_value' => $data['queue_status_value'],
                    'aliases' => $data['aliases'],
                ]);
                $this->line("  ✓ Actualizado diseñador: {$designer->name} [Slug: {$data['slug']}, Lead: ".($data['is_lead'] ? 'SI' : 'NO').']');
            } else {
                $slug = Str::slug($designer->name);
                $isExternal = str_contains(mb_strtolower($designer->name), 'extern') || $designer->color_type === 'yellow';

                $designer->update([
                    'slug' => $slug,
                    'color_type' => $isExternal ? 'amber' : 'indigo',
                    'hex_color' => $isExternal ? '#f59e0b' : '#6366f1',
                    'is_lead' => false,
                    'is_external' => $isExternal,
                    'queue_status_value' => null,
                    'aliases' => [$slug, mb_strtolower($designer->name)],
                ]);
                $this->line("  ✓ Configurado diseñador genérico/externo: {$designer->name}");
            }
        }

        // Asegurar que al menos 1 sea lead
        if (! Designer::where('is_lead', true)->exists()) {
            $lead = Designer::where('active', true)->first();
            if ($lead) {
                $lead->update(['is_lead' => true]);
                $this->info("✓ Marcado {$lead->name} como Lead Designer automático.");
            }
        }

        // Re-alinear órdenes en colas de recepción con su diseñador correspondiente
        foreach (Designer::whereNotNull('queue_status_value')->get() as $des) {
            $queueStatus = $des->getQueueStatus();
            $misalignedOrders = Order::where('core_status', $queueStatus)
                ->where(function ($q) use ($des) {
                    $q->where('designer_id', '!=', $des->id)
                        ->orWhereNull('designer_id');
                })->get();

            foreach ($misalignedOrders as $order) {
                $order->syncDesigners([$des->id]);
                $this->line("  ✓ Re-alineada orden #{$order->id} (WO: {$order->wo_number}) a diseñador {$des->name}");
            }
        }

        $this->info('Optimización de diseñadores completada exitosamente.');

        return Command::SUCCESS;
    }
}
