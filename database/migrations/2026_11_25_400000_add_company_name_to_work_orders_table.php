<?php

use App\Infrastructure\Backfill\LegacyBackfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->string('company_name')->nullable()->after('client_id');
        });

        app(LegacyBackfill::class)->fillWorkOrderCompanyNames();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropColumn('company_name');
        });
    }
};
