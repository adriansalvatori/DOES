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
        DB::table('related_tasks')
            ->whereNull('scheduled_date')
            ->whereNotNull('due_date')
            ->update([
                'scheduled_date' => DB::raw('due_date'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: backfill cannot and does not need to be reversed
    }
};
