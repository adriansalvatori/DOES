<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('orders')
            ->where('substatus', 'CAMBIOS CAMILA')
            ->update(['substatus' => 'ENVIAR A CAMILA']);

        DB::table('orders')
            ->where('origin_substatus', 'CAMBIOS CAMILA')
            ->update(['origin_substatus' => 'ENVIAR A CAMILA']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')
            ->where('substatus', 'ENVIAR A CAMILA')
            ->update(['substatus' => 'CAMBIOS CAMILA']);

        DB::table('orders')
            ->where('origin_substatus', 'ENVIAR A CAMILA')
            ->update(['origin_substatus' => 'CAMBIOS CAMILA']);
    }
};
