<?php

use App\Models\Client;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('portal_token', 64)->nullable()->unique()->after('notes');
        });

        // Generate tokens for existing clients
        Client::withTrashed()->each(function ($client) {
            if (empty($client->portal_token)) {
                $client->portal_token = Str::random(32);
                $client->saveQuietly();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('portal_token');
        });
    }
};
