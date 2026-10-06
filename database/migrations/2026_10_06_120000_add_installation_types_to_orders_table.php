<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'installation_types')) {
                $table->json('installation_types')->nullable()->after('installation_type');
            }
        });

        // Backfill existing installation_type into installation_types array
        DB::table('orders')
            ->whereNotNull('installation_type')
            ->where('installation_type', '!=', '')
            ->whereNull('installation_types')
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update([
                            'installation_types' => json_encode([$order->installation_type]),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'installation_types')) {
                $table->dropColumn('installation_types');
            }
        });
    }
};
