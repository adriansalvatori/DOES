<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('related_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('title');
            $table->string('type');
            $table->string('status')->default('todo')->index(); // todo, done
            $table->foreignId('assignee_id')->nullable()->constrained('designers')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('trigger_type')->nullable();
            $table->string('priority')->default('normal'); // high, normal, low
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('related_tasks');
    }
};
