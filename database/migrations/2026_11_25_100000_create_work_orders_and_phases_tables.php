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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_location_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('estimate_invoice_number')->nullable();
            $table->date('promised_date')->nullable();
            $table->string('fulfillment_method')->nullable();
            $table->json('installation_types')->nullable();
            $table->text('notes')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->boolean('in_backlog')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->date('promised_date')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phases');
        Schema::dropIfExists('work_orders');
    }
};
