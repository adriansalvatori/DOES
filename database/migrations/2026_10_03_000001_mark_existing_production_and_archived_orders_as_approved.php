<?php

use App\Enums\CoreStatus;
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
            ->whereIn('core_status', [
                CoreStatus::EN_PRODUCCION->value,
                CoreStatus::ARCHIVED->value,
                'EN PRODUCCIÓN',
                'ARCHIVED',
            ])
            ->where('approved', false)
            ->update([
                'approved' => true,
                'approved_at' => DB::raw('COALESCE(approved_at, created_at, CURRENT_TIMESTAMP)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: Backfilled approvals do not need reversal.
    }
};
