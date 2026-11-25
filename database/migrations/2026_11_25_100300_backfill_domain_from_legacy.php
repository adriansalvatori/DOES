<?php

use App\Infrastructure\Backfill\LegacyBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(LegacyBackfill::class)->run();
    }

    /**
     * The backfill only fills additive columns and new tables, which the earlier migrations drop on rollback.
     */
    public function down(): void
    {
        //
    }
};
