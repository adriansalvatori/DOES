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
            $table->string('origin_core_status')->nullable()->after('core_status');
            $table->string('origin_substatus')->nullable()->after('substatus');
        });

        Schema::table('subtask_presets', function (Blueprint $table) {
            $table->string('category')->nullable()->default('new_design')->after('color_theme');
        });

        Schema::table('related_tasks', function (Blueprint $table) {
            $table->string('category')->nullable()->after('type');
            $table->string('return_core_status')->nullable()->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('related_tasks', function (Blueprint $table) {
            $table->dropColumn(['category', 'return_core_status']);
        });

        Schema::table('subtask_presets', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['origin_core_status', 'origin_substatus']);
        });
    }
};
