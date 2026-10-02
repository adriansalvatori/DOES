<?php

use App\Models\Substatus;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Substatus::firstOrCreate(
            ['name' => 'EXTERNO'],
            [
                'core_status' => null,
                'is_default' => false,
                'is_global' => true,
                'bg_color' => '#FEFCE8',
                'text_color' => '#854D0E',
                'border_color' => '#FEF08A',
                'is_system' => true,
                'sort_order' => 4,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Substatus::where('name', 'EXTERNO')->delete();
    }
};
