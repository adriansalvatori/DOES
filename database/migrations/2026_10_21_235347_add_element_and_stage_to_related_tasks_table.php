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
        Schema::table('related_tasks', function (Blueprint $table) {
            $table->foreignId('order_element_id')->nullable()->after('order_id')->constrained('order_elements')->nullOnDelete();
            $table->foreignId('order_stage_id')->nullable()->after('order_element_id')->constrained('order_stages')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('related_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_element_id');
            $table->dropConstrainedForeignId('order_stage_id');
        });
    }
};
