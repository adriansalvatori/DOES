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
        Schema::create('trello_list_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('core_status')->unique();
            $table->string('trello_list_id')->nullable();
            $table->string('trello_list_name')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trello_list_mappings');
    }
};
