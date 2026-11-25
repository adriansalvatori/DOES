<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive, nullable columns on the legacy tables. The legacy columns stay until the final cleanup migration.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('work_order_id')->nullable();
            $table->unsignedBigInteger('phase_id')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->date('planned_for')->nullable();
            $table->date('due_target')->nullable();
            $table->string('size')->nullable();
            $table->string('material')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->string('files_url')->nullable();
            $table->string('trello_card_url')->nullable();
            $table->json('flag_codes')->nullable();
            $table->string('close_reason')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->unsignedInteger('carry_over_count')->default(0);
            $table->unsignedBigInteger('legacy_element_id')->nullable()->unique();

            $table->index('work_order_id');
            $table->index('status');
            $table->index(['assignee_id', 'status']);
        });

        Schema::table('related_tasks', function (Blueprint $table) {
            $table->string('kind')->nullable();
            $table->string('advances_to')->nullable();
            $table->boolean('required')->default(false);
            $table->string('title_key')->nullable();
            $table->json('title_params')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('wip_limit')->default(4);
            $table->boolean('is_reviewer')->default(false);
            $table->boolean('is_lead')->default(false);
            $table->unsignedBigInteger('designer_id')->nullable();
        });

        Schema::table('designers', function (Blueprint $table) {
            $table->unsignedBigInteger('moved_to_contact_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('designers', function (Blueprint $table) {
            $table->dropColumn('moved_to_contact_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wip_limit', 'is_reviewer', 'is_lead', 'designer_id']);
        });

        Schema::table('related_tasks', function (Blueprint $table) {
            $table->dropColumn(['kind', 'advances_to', 'required', 'title_key', 'title_params']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['work_order_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['assignee_id', 'status']);
            $table->dropUnique(['legacy_element_id']);
            $table->dropColumn([
                'work_order_id', 'phase_id', 'status', 'assignee_id', 'reviewer_id', 'planned_for', 'due_target',
                'size', 'material', 'quantity', 'files_url', 'trello_card_url', 'flag_codes', 'close_reason',
                'closed_at', 'carry_over_count', 'legacy_element_id',
            ]);
        });
    }
};
