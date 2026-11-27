<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every deliverable starts at medium priority. Its points are the sum of its tasks' points (filled by the next migration).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('priority')->default('medium')->index();
            $table->unsignedTinyInteger('points')->nullable();
        });

    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['priority']);
            $table->dropColumn(['priority', 'points']);
        });
    }
};
