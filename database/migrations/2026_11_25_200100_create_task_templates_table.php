<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task templates replace the subtask presets. The presets table stays untouched.
     */
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name_en');
            $table->string('name_es');
            $table->string('kind')->default('work');
            $table->string('advances_to')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });

        if (! Schema::hasTable('subtask_presets')) {
            return;
        }

        $english = [
            'Ajustes cliente' => 'Client adjustments',
            'Ajustes Camila' => 'Reviewer adjustments',
            'Nueva propuesta' => 'New proposal',
            'Revisar Medidas Recibidas' => 'Review received measurements',
            'Poner en ALTA' => 'Prepare production files',
            'Confirmar medidas' => 'Confirm measurements',
            'Follow Up Camila' => 'Follow up with reviewer',
            'Responder Correo' => 'Reply to email',
            'Solicitar Medidas Survey' => 'Request survey measurements',
            'Follow Up Cliente' => 'Follow up with client',
            'Solicitar información al cliente' => 'Request information from client',
            'Digitalización' => 'Digitization',
            'Revisar File de cliente' => 'Review client file',
            'Enviar Proof cliente' => 'Send proof to client',
            'Storytelling: Formulario personalizado' => 'Storytelling: custom form',
            'Storytelling: Primera Etapa (investigación)' => 'Storytelling: first phase (research)',
        ];
        $gates = [
            'Poner en ALTA' => 'ready_for_production',
            'Enviar Proof cliente' => 'client_review',
        ];
        $now = now();

        foreach (DB::table('subtask_presets')->orderBy('sort_order')->orderBy('id')->get() as $index => $preset) {
            $advancesTo = $gates[$preset->title] ?? null;

            DB::table('task_templates')->insert([
                'name_en' => $english[$preset->title] ?? $preset->title,
                'name_es' => $preset->title,
                'kind' => $advancesTo === null ? 'work' : 'gate',
                'advances_to' => $advancesTo,
                'required' => $advancesTo !== null,
                'active' => (bool) $preset->is_active,
                'sort' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};
