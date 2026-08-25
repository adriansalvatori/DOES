<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('company_name')->constrained('clients')->nullOnDelete();
            $table->foreignId('client_location_id')->nullable()->after('client_id')->constrained('client_locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['client_location_id']);
            $table->dropForeign(['client_id']);
            $table->dropColumn(['client_id', 'client_location_id']);
        });
    }
};
