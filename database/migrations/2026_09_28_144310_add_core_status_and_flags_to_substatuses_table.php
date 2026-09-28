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
        Schema::table('substatuses', function (Blueprint $table) {
            $table->string('core_status')->nullable()->after('name')->index();
            $table->boolean('is_default')->default(false)->after('core_status');
            $table->boolean('is_global')->default(false)->after('is_default');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('substatuses', function (Blueprint $table) {
            $table->dropColumn(['core_status', 'is_default', 'is_global']);
        });
    }
};
