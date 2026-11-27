<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A merged work order, deliverable or task goes to the trash and remembers which record it was merged into.
     */
    public function up(): void
    {
        foreach (['work_orders', 'orders', 'related_tasks'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->unsignedBigInteger('merged_into_id')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['work_orders', 'orders', 'related_tasks'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropColumn('merged_into_id');
            });
        }
    }
};
