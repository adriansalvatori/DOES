<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designer_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('designer_id')->constrained('designers')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['order_id', 'designer_id']);
        });

        // Backfill existing single designer_id assignments into pivot table
        $orders = DB::table('orders')->whereNotNull('designer_id')->get(['id', 'designer_id', 'created_at', 'updated_at']);
        $now = now();
        $records = [];
        foreach ($orders as $o) {
            $records[] = [
                'order_id' => $o->id,
                'designer_id' => $o->designer_id,
                'created_at' => $o->created_at ?? $now,
                'updated_at' => $o->updated_at ?? $now,
            ];
        }

        if (! empty($records)) {
            DB::table('designer_order')->insertOrIgnore($records);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('designer_order');
    }
};
