<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('trello_card_id')->nullable()->index();
            $table->string('company_name');
            $table->string('task_name');
            $table->foreignId('designer_id')->nullable()->constrained('designers')->nullOnDelete();
            $table->string('core_status')->default('ENTRANTE')->index();
            $table->string('substatus')->nullable()->index();
            $table->string('blocking_reason')->nullable();
            $table->text('blocking_reason_other')->nullable();
            $table->date('start_date')->nullable();
            $table->date('original_due_date')->nullable();
            $table->date('current_due_date')->nullable()->index();
            $table->date('scheduled_date')->nullable();
            $table->timestamp('last_meaningful_update')->nullable();
            $table->timestamp('client_last_response')->nullable();
            $table->boolean('approved')->default(false);
            $table->boolean('measures_confirmed')->default(false);
            $table->boolean('estimate_approved')->default(false);
            $table->unsignedInteger('client_revision_count')->default(0);
            $table->unsignedInteger('internal_revision_count')->default(0);
            $table->text('pause_reason')->nullable();
            $table->boolean('done_today')->default(false);
            $table->boolean('customer_service_required')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
