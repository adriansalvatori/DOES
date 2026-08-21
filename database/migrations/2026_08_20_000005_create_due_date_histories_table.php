<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('due_date_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->date('previous_due_date')->nullable();
            $table->date('new_due_date');
            $table->string('reason')->nullable();
            $table->string('trigger_event')->nullable();
            $table->string('created_by')->default('system');
            $table->date('client_promised_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('due_date_histories');
    }
};
