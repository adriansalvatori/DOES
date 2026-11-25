<?php

namespace App\Console\Commands;

use App\Services\TrelloSyncService;
use App\Support\TrelloCutover;
use Illuminate\Console\Command;

class TrelloCutoverCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'trello:cutover
        {--dry-run : Read the board and report what would be imported, without pausing, writing or completing the cutover}
        {--without-files : Import descriptions, comments and links only (no Drive uploads)}';

    /**
     * @var string
     */
    protected $description = 'Retire Trello: pause the sync, import every card into the app, run a final delta pass and mark the cutover complete';

    public function handle(TrelloSyncService $trello): int
    {
        $importOptions = $this->option('without-files') ? ['--without-files' => true] : [];

        if ($this->option('dry-run')) {
            $this->info('Simulación: no se pausa la sincronización ni se escribe nada.');

            return $this->call('trello:migrate-to-native', ['--dry-run' => true] + $importOptions);
        }

        if (TrelloCutover::isComplete()) {
            $this->warn('El corte de Trello ya se completó el '.TrelloCutover::completedAt()?->toDateTimeString().'.');

            return self::SUCCESS;
        }

        $this->info('1/4 Pausando la sincronización con Trello...');
        $trello->setPaused(true);

        $this->info('2/4 Importando todas las órdenes (descripciones, comentarios, archivos a Drive)...');
        if ($this->runImport($importOptions) !== self::SUCCESS) {
            $this->error('La importación tuvo errores. El corte NO se marcó como completo y la sincronización sigue pausada; corrige y vuelve a ejecutar el comando (es seguro repetirlo).');

            return self::FAILURE;
        }

        $this->info('3/4 Pasada final: trayendo lo que cambió en Trello durante la importación...');
        if ($this->runImport($importOptions) !== self::SUCCESS) {
            $this->error('La pasada final tuvo errores. El corte NO se marcó como completo; vuelve a ejecutar el comando.');

            return self::FAILURE;
        }

        $this->info('4/4 Marcando el corte como completo...');
        TrelloCutover::markComplete();

        $this->info('Corte de Trello completo. Ya no se lee ni se escribe en Trello; las tarjetas quedan como enlace de referencia.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, bool>  $options
     */
    private function runImport(array $options): int
    {
        try {
            return $this->call('trello:migrate-to-native', $options);
        } catch (\Throwable $exception) {
            $this->error('No se pudo leer Trello: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
