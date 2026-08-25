<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientLink;
use App\Models\ClientLocation;
use App\Models\Order;
use App\Services\ClientMatchingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConsolidateClientsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clients:consolidate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consolidate workspace orders into structured Clients and ClientLocations in UPPERCASE, ignoring backlog cards';

    /**
     * Execute the console command.
     */
    public function handle(ClientMatchingService $matchingService): int
    {
        $this->info('Iniciando consolidación limpia de clientes desde órdenes del Workspace...');

        DB::transaction(function () use ($matchingService, &$processed, &$linkedLocations) {
            // Reset relations on orders
            Order::query()->update([
                'client_id' => null,
                'client_location_id' => null,
            ]);

            // Wipe existing client tables to eliminate bogus backlog entries
            ClientLocation::query()->forceDelete();
            ClientContact::query()->delete();
            ClientLink::query()->delete();
            Client::query()->forceDelete();

            // Fetch ONLY active workspace orders
            $workspaceOrders = Order::inWorkspace()
                ->whereNotNull('company_name')
                ->where('company_name', '!=', '')
                ->get();

            $processed = 0;
            $linkedLocations = 0;

            foreach ($workspaceOrders as $order) {
                $match = $matchingService->matchOrCreate($order->company_name, $order->responsible_person);

                $order->updateQuietly([
                    'client_id' => $match['client']->id,
                    'client_location_id' => $match['location']?->id,
                    'company_name' => $match['client']->name,
                ]);

                $processed++;
                if ($match['location']) {
                    $linkedLocations++;
                }
            }
        });

        $this->info('Consolidación completada exitosamente.');
        $this->info("Órdenes de Workspace procesadas: {$processed}");
        $this->info("Locaciones vinculadas: {$linkedLocations}");
        $this->info('Total de Clientes únicos en Workspace: '.Client::count());

        return Command::SUCCESS;
    }
}
