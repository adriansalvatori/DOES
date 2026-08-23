<?php

namespace App\Console\Commands;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PopulateCreationDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:populate-creation-dates';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Populate trello_created_at timestamp from Trello Card ObjectID timestamps across all orders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orders = Order::all();
        $this->info("Iniciando extracción de fechas de creación de Trello para {$orders->count()} órdenes...");

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        $updatedCount = 0;

        foreach ($orders as $order) {
            $trelloCreatedAt = null;

            if ($order->trello_card_id && strlen($order->trello_card_id) >= 8) {
                try {
                    $hex = substr($order->trello_card_id, 0, 8);
                    $timestamp = hexdec($hex);
                    if ($timestamp > 0) {
                        $trelloCreatedAt = Carbon::createFromTimestamp($timestamp);
                    }
                } catch (\Exception $e) {
                    $trelloCreatedAt = $order->created_at;
                }
            }

            if (! $trelloCreatedAt) {
                $trelloCreatedAt = $order->created_at;
            }

            $order->update(['trello_created_at' => $trelloCreatedAt]);
            $updatedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("¡Éxito! Se popularon las fechas de creación para {$updatedCount} órdenes.");

        return Command::SUCCESS;
    }
}
