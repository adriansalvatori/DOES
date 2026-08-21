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
        Schema::create('substatuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('bg_color')->default('#F3F4F6');
            $table->string('text_color')->default('#374151');
            $table->string('border_color')->default('#E5E7EB');
            $table->boolean('is_system')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('substatuses');
    }
};
