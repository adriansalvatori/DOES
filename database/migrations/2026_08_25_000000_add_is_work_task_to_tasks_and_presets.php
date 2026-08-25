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
            $table->boolean('is_work_task')->default(true)->after('priority');
        });

        Schema::table('subtask_presets', function (Blueprint $table) {
            $table->boolean('is_work_task')->default(true)->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('related_tasks', function (Blueprint $table) {
            $table->dropColumn('is_work_task');
        });

        Schema::table('subtask_presets', function (Blueprint $table) {
            $table->dropColumn('is_work_task');
        });
    }
};
