<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update all existing orders company_name and task_name to UPPERCASE
        DB::table('orders')->whereNotNull('company_name')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                $updates = [];
                if ($order->company_name !== null) {
                    $updates['company_name'] = mb_strtoupper($order->company_name, 'UTF-8');
                }
                if ($order->task_name !== null) {
                    $updates['task_name'] = mb_strtoupper($order->task_name, 'UTF-8');
                }
                if (! empty($updates)) {
                    DB::table('orders')->where('id', $order->id)->update($updates);
                }
            }
        });

        // Update all existing clients name to UPPERCASE
        DB::table('clients')->whereNotNull('name')->chunkById(200, function ($clients) {
            foreach ($clients as $client) {
                if ($client->name !== null) {
                    DB::table('clients')->where('id', $client->id)->update([
                        'name' => mb_strtoupper($client->name, 'UTF-8'),
                    ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // One-way formatting conversion; cannot revert to original case
    }
};
