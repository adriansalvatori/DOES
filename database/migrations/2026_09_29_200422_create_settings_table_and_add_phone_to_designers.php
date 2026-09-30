<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('designers', 'phone')) {
            Schema::table('designers', function (Blueprint $table) {
                $table->string('phone')->nullable()->after('queue_status_value');
            });
        }

        // Initialize default CS WhatsApp phone
        DB::table('settings')->updateOrInsert(
            ['key' => 'cs_whatsapp_phone'],
            ['value' => '+16783580594', 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('designers', 'phone')) {
            Schema::table('designers', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }

        Schema::dropIfExists('settings');
    }
};
