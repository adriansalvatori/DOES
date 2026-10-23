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
        Schema::create('order_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_stage_id')->nullable()->constrained('order_stages')->nullOnDelete();
            $table->string('name');
            $table->string('dimensions')->nullable();
            $table->string('material')->nullable();
            $table->integer('quantity')->default(1);
            $table->integer('sort_order')->default(0);
            $table->string('status')->default('draft')->index();

            // Approval fields
            $table->boolean('is_approved')->default(false)->index();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_type')->nullable(); // 'cliente', 'camila'
            $table->text('approval_note')->nullable();

            // Production & Alta fields
            $table->string('file_url')->nullable();
            $table->text('production_notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'sort_order']);
            $table->index(['order_id', 'status']);
            $table->index(['order_stage_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_elements');
    }
};
