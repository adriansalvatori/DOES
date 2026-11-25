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
        if (! Schema::hasIndex('related_tasks', ['order_id', 'status'])) {
            Schema::table('related_tasks', function (Blueprint $table) {
                $table->index(['order_id', 'status'], 'related_tasks_order_id_status_index');
            });
        }

        if (! Schema::hasIndex('order_events', ['order_id'])) {
            Schema::table('order_events', function (Blueprint $table) {
                $table->index('order_id', 'order_events_order_id_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('related_tasks', 'related_tasks_order_id_status_index')) {
            Schema::table('related_tasks', function (Blueprint $table) {
                $table->dropIndex('related_tasks_order_id_status_index');
            });
        }

        if (Schema::hasIndex('order_events', 'order_events_order_id_index')) {
            Schema::table('order_events', function (Blueprint $table) {
                $table->dropIndex('order_events_order_id_index');
            });
        }
    }
};
