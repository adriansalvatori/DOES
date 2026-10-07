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
        DB::table('related_tasks')
            ->where('title', 'Enviar correo de bienvenida')
            ->update(['title' => 'Enviar correo bienvenida/orden nueva']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('related_tasks')
            ->where('title', 'Enviar correo bienvenida/orden nueva')
            ->update(['title' => 'Enviar correo de bienvenida']);
    }
};
