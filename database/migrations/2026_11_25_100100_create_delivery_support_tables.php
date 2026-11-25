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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained('orders')->cascadeOnDelete();
            $table->string('source');
            $table->boolean('measurements_confirmed')->default(false);
            $table->boolean('estimate_approved')->default(false);
            $table->text('note')->nullable();
            $table->string('evidence_path')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('reset_at')->nullable();
            $table->timestamps();

            $table->index(['deliverable_id', 'source']);
        });

        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained('orders')->cascadeOnDelete();
            $table->string('reason');
            $table->text('note')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('raised_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['deliverable_id', 'resolved_at']);
        });

        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained('orders')->cascadeOnDelete();
            $table->string('reason');
            $table->text('note')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->timestamps();

            $table->index(['deliverable_id', 'ended_at']);
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->constrained('orders')->cascadeOnDelete();
            $table->string('method');
            $table->foreignId('logged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('received_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('deliverable_id');
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deliverable_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->date('due_on')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->unsignedBigInteger('legacy_task_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'resolved_at']);
            $table->index(['deliverable_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('holds');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('approvals');
    }
};
