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
        Schema::table('designers', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable()->after('name');
            $table->string('color_type')->default('cyan')->after('slug');
            $table->string('hex_color')->nullable()->after('color_type');
            $table->boolean('is_lead')->default(false)->after('active');
            $table->boolean('is_external')->default(false)->after('is_lead');
            $table->string('queue_status_value')->nullable()->after('is_external');
            $table->json('aliases')->nullable()->after('queue_status_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('designers', function (Blueprint $table) {
            $table->dropColumn(['slug', 'color_type', 'hex_color', 'is_lead', 'is_external', 'queue_status_value', 'aliases']);
        });
    }
};
