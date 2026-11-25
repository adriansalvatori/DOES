<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editable English and Spanish labels for the codes the workflow enums define. Seeded from lang/{en,es}/enums.php.
     */
    public function up(): void
    {
        Schema::create('workflow_labels', function (Blueprint $table) {
            $table->id();
            $table->string('kind');
            $table->string('code');
            $table->string('label_en');
            $table->string('label_es');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['kind', 'code']);
        });

        $english = require lang_path('en/enums.php');
        $spanish = require lang_path('es/enums.php');
        $now = now();

        foreach (['block_reason', 'hold_reason', 'close_reason', 'deliverable_flag'] as $kind) {
            $sort = 0;
            foreach ($english[$kind] ?? [] as $code => $label) {
                DB::table('workflow_labels')->insert([
                    'kind' => $kind,
                    'code' => $code,
                    'label_en' => $label,
                    'label_es' => $spanish[$kind][$code] ?? $label,
                    'active' => true,
                    'sort' => ++$sort,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_labels');
    }
};
