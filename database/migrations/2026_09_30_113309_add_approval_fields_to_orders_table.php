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
            $table->string('approval_type')->nullable()->after('estimate_approved');
            $table->text('approval_note')->nullable()->after('approval_type');
            $table->string('approval_image_path')->nullable()->after('approval_note');
            $table->timestamp('approved_at')->nullable()->after('approval_image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['approval_type', 'approval_note', 'approval_image_path', 'approved_at']);
        });
    }
};
