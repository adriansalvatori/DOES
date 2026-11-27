<?php

namespace App\Domain\Delivery\Support;

use App\Domain\Delivery\Models\Deliverable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fibonacci estimates, Scrum style. A deliverable is the story and its tasks carry the points; the story's points are
 * the sum of its tasks.
 *
 * - Size of the job (1 to 13): from what the deliverable asks for. The kind of work sets a level, several pieces or an
 *   installation mock-up add one, a reprint or a sample takes one or two off. One point is roughly the design of a
 *   standard print piece.
 * - A task: the first design task (the first proposal or proof) is worth the size of the job; a revision is two
 *   Fibonacci steps lower (13 → 5, 8 → 3, 5 → 2, else 1); production prep is 1; follow-ups, emails, calls and
 *   blockers are 0. People can change any task's points afterwards.
 */
class PointsEstimator
{
    /** Sizes of a job. @var list<int> */
    public const SCALE = [1, 2, 3, 5, 8, 13];

    /** Points a task can carry; 0 is coordination. @var list<int> */
    public const TASK_SCALE = [0, 1, 2, 3, 5, 8, 13];

    public const COORDINATION = 'coordination';

    public const PRODUCTION = 'production';

    public const REVISION = 'revision';

    public const DESIGN = 'design';

    /** Legacy task types that are coordination whatever their title says. */
    private const COORDINATION_TYPES = ['FOLLOW UP CLIENTE', 'FOLLOW UP CAMILA', 'ENVIAR CORREO DE BIENVENIDA', 'ENVIAR CORREO DE ATRASO', 'SOLICITAR INFORMACION'];

    private const TASK_REVISION = 'AJUSTE|CAMBIOS? (DEL? )?CLIENTE|CAMBIOS? (DE )?CAMILA|^CAMBIOS?\b|CORRECCION|CORREGIR|REVISION|REVISAR|NUEVA VERSION|\bV[2-9]\b|SEGUNDA PROPUESTA|NUEVA PROPUESTA|MODIFICA';

    private const TASK_COORDINATION = 'FOLLOW ?UP|SEGUIMIENTO|CORREO|E-?MAIL|LLAMAR|LLAMADA|MEETING|REUNION|SOLICITAR|PEDIR |CONFIRMA|AVISAR|PREGUNTAR|COTIZ|ESTIMADO|FACTURA|INVOICE|\bPAGO\b|WHATSAPP|ENVIAR A GABY|BIENVENIDA|CONTACT|\bTEXT\b|MENSAJE';

    private const TASK_PRODUCTION = '\bALTA\b|PRODUCCION|PRODUCTION|ARCHIVOS?\b|MEDIDAS?\b|MONTAJE|IMPRESION|PRINT FILE|PROVEEDOR|SUPPLIER|4OVER|INSTALACION|INSTALL|PERMISO|PERMIT';

    private const TASK_DESIGN = 'PRIMERA PROPUESTA|FIRST PROPOSAL|PROPUESTA|PROPOSAL|PROOF|DISENO|DESIGN|BOCETO|MOCK ?UP|CONCEPT|IDEAS';

    /**
     * Level (index into SCALE), pattern and whether it is signage or space work (where an installation adds a site
     * mock-up), per kind of work. The first match wins.
     *
     * @var list<array{0: int, 1: string, 2: bool}>
     */
    private const KINDS = [
        [4, 'BRANDING|BRAND ?BOOK|BRAND &|BRAND IDENTITY|DESARROLLO DE MARCA|REFRESCAMIENTO DE MA|IDENTIDAD|STORYTELLING|REBRAND|REDISENO DE MARCA', false],
        [4, '\bLOCACION\b|NEW LOCATION|FACHADA|STOREFRONT|OFFICE DESIGN|INTERIOR DESIGN|SPACE DESIGN|ART CONCEPT|CONCEPT DESIGN|EVENT HALL', true],
        [3, 'LOGO_DESIGN', false],
        [3, '\bBOOK\b|\bLIBRO\b|CATALOGO|CATALOG|REVISTA|MAGAZINE|WEBSITE(?! - REVISION)|PERSONAJES|ANIMACION', false],
        [3, 'CHANNEL LETTER|POLE SIGN|MONUMENT|PYLON|LIGHT ?BOX|CAJA DE LUZ|NEON|TOP SIGN|SIGN TOP|OUTDOOR SIGN|EXTERIOR SIGN|SIGN EXTERIOR|LETTERS|LETRAS|\b3D\b|RENDER|MURAL|WALL DESIGN|PAREDES|FULL WRAP|WRAP FULL|WRAP.*FULL|DRIVE.?THRU|MENU BOARD|BACK BAR|DISPLAY WALL|MENU TV', true],
        [2, '\bSIGNS?\b|RECEPTION|\bMENU\b|BROCHURE|TRIFOLD|WRAP|WINDOWS?\b|VENTANAS|A-?FRAME|RACK|DISPLAY|\bTENT\b|CARPA|PANEL|PROPUESTA|EVENT|ART MODIFICATION|BOOKLET|VIDEO|CAMION|TRUCK|VEHICLE|PERMIT|SURVEY|PROYECTO|CUADROS|OFICINA', true],
    ];

    private const REPRINT = 'REPRINT|RE-PRINT|REIMPRESION|MISMO DISENO|\bALTA\b';

    private const SAMPLE = 'MUESTRA|SAMPLE|\(TICKET\)|\bTICKET\b|ADICIONAL|VECTORIZAR';

    private const MULTIPLE = '\bVARIOS\b|VARIAS|\bKIT\b|PAQUETE|PACKAGE|ELEMENTOS|CADA TIENDA|TODAS LAS LOCACIONES|ALL LOCATIONS|\b([3-9]|1\d)\s+(SIGNS|LOGOS|MODELOS|DISENOS|PROPUESTAS|PIEZAS|REFERENCIAS)';

    public function estimate(?string $task, ?string $company = null, ?string $trelloTitle = null, ?string $installation = null): int
    {
        $text = $this->taskText($task, $company, $trelloTitle);
        $level = 0;
        $isSignage = false;

        foreach (self::KINDS as [$kindLevel, $pattern, $signage]) {
            $matches = $pattern === 'LOGO_DESIGN' ? $this->isLogoDesign($text) : $this->matches($pattern, $text);
            if ($matches) {
                $level = $kindLevel;
                $isSignage = $signage;
                break;
            }
        }

        $pieces = count(array_filter(
            preg_split('~\s(?:\+|/|&|AND)\s|\+|\s/\s|,~', $text) ?: [],
            fn (string $piece): bool => strlen(trim($piece)) > 2,
        ));

        if ($this->matches(self::MULTIPLE, $text) || $pieces >= 3) {
            $level++;
        } elseif ($pieces === 2 && $level <= 3) {
            $level++;
        }

        if (str_contains($this->normalize($installation), 'INSTALACION') && $level >= 2 && $level <= 3 && $isSignage) {
            $level++;
        }

        if ($this->matches(self::REPRINT, $text)) {
            $level -= 2;
        } elseif ($this->matches(self::SAMPLE, $text)) {
            $level--;
        }

        return self::SCALE[max(0, min(count(self::SCALE) - 1, $level))];
    }

    public function estimateFor(Deliverable $deliverable): int
    {
        return $this->estimate(
            $deliverable->task_name,
            $deliverable->company_name,
            $deliverable->getAttribute('trello_title'),
            $deliverable->getAttribute('installation_type'),
        );
    }

    /**
     * What a task is: coordination, production prep, a revision or design.
     */
    public function taskNature(?string $title, ?string $legacyType = null, ?string $kind = null): string
    {
        $type = $this->normalize($legacyType);
        $text = $this->normalize($title);

        if ($kind === 'gate_prepare_production_files' || $type === 'PONER EN ALTA') {
            return self::PRODUCTION;
        }
        if (in_array($type, self::COORDINATION_TYPES, true)) {
            return self::COORDINATION;
        }

        return match (true) {
            $this->matches(self::TASK_REVISION, $text) => self::REVISION,
            $this->matches(self::TASK_COORDINATION, $text) => self::COORDINATION,
            $this->matches(self::TASK_PRODUCTION, $text) => self::PRODUCTION,
            $this->matches(self::TASK_DESIGN, $text) => self::DESIGN,
            $type === 'RESOLVER' => self::COORDINATION,
            default => self::DESIGN,
        };
    }

    /**
     * Points of a task of the given nature in a job of the given size. Only the first design task of a deliverable is
     * worth the whole job; later ones are revisions.
     */
    public function taskPoints(string $nature, int $jobSize, bool $firstDesignTask): int
    {
        return match ($nature) {
            self::COORDINATION => 0,
            self::PRODUCTION => 1,
            self::REVISION => $this->revisionPoints($jobSize),
            default => $firstDesignTask ? $jobSize : $this->revisionPoints($jobSize),
        };
    }

    public function revisionPoints(int $jobSize): int
    {
        $index = array_search($jobSize, self::SCALE, true);

        return self::SCALE[max(0, ($index === false ? 0 : $index) - 2)];
    }

    /**
     * Estimate for a task about to be added to a deliverable. Null when the deliverable still waits in the backlog.
     */
    public function estimateNewTask(int $deliverableId, ?string $title, ?string $legacyType = null, ?string $kind = null, ?int $exceptTaskId = null): ?int
    {
        $deliverable = DB::table('orders')->where('id', $deliverableId)->first(['id', 'task_name', 'company_name', 'trello_title', 'installation_type', 'in_backlog']);

        if ($deliverable === null || $deliverable->in_backlog) {
            return null;
        }

        $nature = $this->taskNature($title, $legacyType, $kind);
        $hasDesignTask = DB::table('related_tasks')->where('order_id', $deliverableId)->whereNull('deleted_at')
            ->when($exceptTaskId !== null, fn ($query) => $query->where('id', '!=', $exceptTaskId))
            ->get(['title', 'title_key', 'type', 'kind'])
            ->contains(fn (object $task): bool => in_array($this->taskNature($task->title ?: $task->title_key, $task->type, $task->kind), [self::DESIGN, self::REVISION], true));

        return $this->taskPoints($nature, $this->estimate($deliverable->task_name, $deliverable->company_name, $deliverable->trello_title, $deliverable->installation_type), ! $hasDesignTask);
    }

    /**
     * Estimates every unscored task of the given deliverables (all deliverables outside the backlog when null), oldest
     * task first so the first design task gets the size of the job, then refreshes the story totals.
     *
     * @param  list<int>|null  $deliverableIds
     * @return int How many tasks got points.
     */
    public function fillTasks(?array $deliverableIds = null): int
    {
        $filled = 0;

        DB::table('orders')
            ->whereNotNull('status')
            ->where('in_backlog', false)
            ->when($deliverableIds !== null, fn ($query) => $query->whereIn('id', $deliverableIds))
            ->select(['id', 'task_name', 'company_name', 'trello_title', 'installation_type'])
            ->chunkById(200, function ($deliverables) use (&$filled): void {
                $tasks = DB::table('related_tasks')->whereIn('order_id', $deliverables->pluck('id'))->whereNull('deleted_at')
                    ->orderBy('created_at')->orderBy('id')
                    ->get(['id', 'order_id', 'title', 'title_key', 'type', 'kind', 'points'])
                    ->groupBy('order_id');

                foreach ($deliverables as $deliverable) {
                    $jobSize = $this->estimate($deliverable->task_name, $deliverable->company_name, $deliverable->trello_title, $deliverable->installation_type);
                    $designSeen = false;

                    foreach ($tasks->get($deliverable->id, collect()) as $task) {
                        $nature = $this->taskNature($task->title ?: $task->title_key, $task->type, $task->kind);

                        if ($task->points === null) {
                            DB::table('related_tasks')->where('id', $task->id)->update(['points' => $this->taskPoints($nature, $jobSize, ! $designSeen)]);
                            $filled++;
                        }

                        $designSeen = $designSeen || in_array($nature, [self::DESIGN, self::REVISION], true);
                    }

                    if ($tasks->has($deliverable->id)) {
                        $this->syncStory($deliverable->id);
                    }
                }
            });

        return $filled;
    }

    /**
     * The story's points: the sum of its tasks' points, empty when no task has points. Called when tasks change;
     * a deliverable without tasks keeps the points set on it by hand.
     */
    public function syncStory(int $deliverableId): void
    {
        $tasks = DB::table('related_tasks')->where('order_id', $deliverableId)->whereNull('deleted_at')->whereNotNull('points');

        DB::table('orders')->where('id', $deliverableId)->update([
            'points' => (clone $tasks)->exists() ? (int) $tasks->sum('points') : null,
        ]);
    }

    /**
     * What the work asks for: the task, or the part of the Trello title after "WO - " when the task only repeats the company.
     */
    private function taskText(?string $task, ?string $company, ?string $trelloTitle): string
    {
        $text = $this->normalize($task);

        if ($text === '' || $text === $this->normalize($company)) {
            $title = $this->normalize($trelloTitle);
            $text = str_contains($title, ' - ') ? explode(' - ', $title, 2)[1] : $title;
        }

        return $text;
    }

    private function isLogoDesign(string $text): bool
    {
        return $this->matches('\bLOGOS?\b', $text)
            && ! $this->matches('(DECAL|STICKERS?|VECTORIZAR|CON|DE|TSHIRT|BORDADO|EMBROIDERY)\s+(DE\s+)?LOGO', $text);
    }

    private function matches(string $pattern, string $text): bool
    {
        return preg_match('~'.$pattern.'~', $text) === 1;
    }

    private function normalize(?string $value): string
    {
        return trim((string) preg_replace('~\s+~', ' ', strtoupper(Str::ascii((string) $value))));
    }
}
