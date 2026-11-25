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
        Schema::table('orders', function (Blueprint $table) {
            $table->longText('description')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->timestamp('trello_migrated_at')->nullable();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('drive_folder_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['description', 'drive_folder_id', 'trello_migrated_at']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('drive_folder_id');
        });
    }
};
