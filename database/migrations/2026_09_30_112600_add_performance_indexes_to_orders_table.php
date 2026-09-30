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
            $table->index(['in_workspace', 'core_status'], 'orders_workspace_status_idx');
            $table->index('created_at', 'orders_created_at_idx');
            $table->index('client_id', 'orders_client_id_idx');
            $table->index('designer_id', 'orders_designer_id_idx');
            $table->index('review_status', 'orders_review_status_idx');
            $table->index('installation_type', 'orders_installation_type_idx');
            $table->index('done_today', 'orders_done_today_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_workspace_status_idx');
            $table->dropIndex('orders_created_at_idx');
            $table->dropIndex('orders_client_id_idx');
            $table->dropIndex('orders_designer_id_idx');
            $table->dropIndex('orders_review_status_idx');
            $table->dropIndex('orders_installation_type_idx');
            $table->dropIndex('orders_done_today_idx');
        });
    }
};
