<?php

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
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'production_processed_at')) {
                $table->date('production_processed_at')->nullable()->after('manual_creation_date');
            }
            if (! Schema::hasColumn('orders', 'delivery_due_date')) {
                $table->date('delivery_due_date')->nullable()->after('production_processed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['production_processed_at', 'delivery_due_date']);
        });
    }
};
