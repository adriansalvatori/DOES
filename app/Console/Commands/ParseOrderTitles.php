<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderTitleParserService;
use Illuminate\Console\Command;

class ParseOrderTitles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:parse-titles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dissect raw Trello titles into wo_number, company_name, responsible_person, and task_name fields across all cards';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orders = Order::all();
        $this->info("Iniciando disección de títulos para {$orders->count()} órdenes...");

        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        $updatedCount = 0;

        foreach ($orders as $order) {
            // Build the original title string to parse (from trello_title or combination of company_name + task_name)
            $sourceTitle = $order->trello_title ?: ($order->company_name === $order->task_name ? $order->company_name : "{$order->company_name} - {$order->task_name}");

            $parsed = OrderTitleParserService::parse($sourceTitle);

            $order->update([
                'wo_number' => $parsed['wo_number'],
                'company_name' => $parsed['company_name'],
                'responsible_person' => $parsed['responsible_person'],
                'task_name' => $parsed['task_name'],
                'trello_title' => $parsed['trello_title'],
            ]);

            $updatedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("¡Éxito! Se actualizaron y disectaron {$updatedCount} órdenes en la base de datos.");

        return Command::SUCCESS;
    }
}
