<?php

use App\Domain\Delivery\Support\PointsEstimator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tasks carry the Fibonacci points; a deliverable's points become the sum of its tasks. Tasks of deliverables
     * outside the backlog get inferred points.
     */
    public function up(): void
    {
        Schema::table('related_tasks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('points')->nullable();
        });

        app(PointsEstimator::class)->fillTasks();
    }

    public function down(): void
    {
        Schema::table('related_tasks', function (Blueprint $table): void {
            $table->dropColumn('points');
        });
    }
};
