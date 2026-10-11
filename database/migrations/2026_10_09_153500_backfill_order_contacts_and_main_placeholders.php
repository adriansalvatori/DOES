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
        // 1. Assign contact_id and responsible_person for orders whose client has a primary contact
        $ordersWithPrimary = DB::select('
            SELECT o.id, cc.contact_id, c.name as contact_name
            FROM orders o
            JOIN client_contact cc ON cc.client_id = o.client_id AND cc.is_primary = 1
            JOIN contacts c ON c.id = cc.contact_id
            WHERE o.contact_id IS NULL
        ');

        foreach ($ordersWithPrimary as $row) {
            DB::table('orders')
                ->where('id', $row->id)
                ->update([
                    'contact_id' => $row->contact_id,
                    'responsible_person' => DB::raw("CASE WHEN responsible_person IS NULL OR responsible_person = '' OR responsible_person = 'MAIN' THEN ".DB::getPdo()->quote($row->contact_name).' ELSE responsible_person END'),
                ]);
        }

        // 2. Set 'MAIN' as placeholder for any remaining orders without responsible person
        DB::table('orders')
            ->where(function ($q) {
                $q->whereNull('responsible_person')
                    ->orWhere('responsible_person', '');
            })
            ->update([
                'responsible_person' => 'MAIN',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-destructive rollback
    }
};
