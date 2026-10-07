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
        DB::table('orders')
            ->whereRaw('LOWER(core_status) = ?', ['entrante'])
            ->update(['core_status' => 'ENTRANTE']);

        DB::table('orders')
            ->whereRaw('LOWER(origin_core_status) = ?', ['entrante'])
            ->update(['origin_core_status' => 'ENTRANTE']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: do not revert to lowercase invalid values
    }
};
