<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A task can be planned for a different person than its deliverable (Plan week). The legacy `assignee_id` points
     * at the old designers table; the person is now a user, copied over through designers.user_id.
     */
    public function up(): void
    {
        Schema::table('related_tasks', function (Blueprint $table): void {
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['scheduled_date', 'assignee_user_id']);
        });

        DB::statement('UPDATE related_tasks SET assignee_user_id = (SELECT designers.user_id FROM designers WHERE designers.id = related_tasks.assignee_id) WHERE assignee_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::table('related_tasks', function (Blueprint $table): void {
            $table->dropIndex(['scheduled_date', 'assignee_user_id']);
            $table->dropConstrainedForeignId('assignee_user_id');
        });
    }
};
