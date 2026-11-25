<?php

namespace App\Support;

/**
 * Translates the titles the application itself generates for related tasks.
 *
 * Titles are stored as created (legacy Spanish text or canonical English text) and only
 * translated when displayed, so every reader sees them in their own locale. Anything that
 * does not match a known system title (user-typed text) is returned untouched.
 */
class SystemTaskTitle
{
    /**
     * Known system titles: regex (Spanish legacy form | English canonical form) => translation key and parameter names.
     *
     * @var list<array{pattern: string, key: string, params: list<string>}>
     */
    private const PATTERNS = [
        ['pattern' => '/^(?:Enviar correo bienvenida\/orden nueva|Send welcome\/new order email)$/iu', 'key' => 'Send welcome/new order email', 'params' => []],
        ['pattern' => '/^(?:Follow Up Cliente|Client follow-up) #(\d+)$/iu', 'key' => 'Client follow-up #:number', 'params' => ['number']],
        ['pattern' => '/^(?:Follow Up Cliente|Client follow-up)$/iu', 'key' => 'Client follow-up', 'params' => []],
        ['pattern' => '/^(?:Follow Up ALTA|Print follow-up)$/iu', 'key' => 'Print follow-up', 'params' => []],
        ['pattern' => '/^(?:Follow Up Camila|Camila follow-up)$/iu', 'key' => 'Camila follow-up', 'params' => []],
        ['pattern' => '/^(?:Llamar a Camila|Call Camila)$/iu', 'key' => 'Call Camila', 'params' => []],
        ['pattern' => '/^(?:Poner en alta|Set for print)$/iu', 'key' => 'Set for print', 'params' => []],
        ['pattern' => '/^(?:Poner en alta|Set for print): (.+) \((\d+) (?:piezas|items)\)$/iu', 'key' => 'Set for print: :name (:count items)', 'params' => ['name', 'count']],
        ['pattern' => '/^(?:Poner en alta|Set for print): (.+)$/iu', 'key' => 'Set for print: :name', 'params' => ['name']],
        ['pattern' => '/^(?:enviar proof al cliente \(camila pre-approves\)|Send proof to client \(Camila pre-approves\))$/iu', 'key' => 'Send proof to client (Camila pre-approves)', 'params' => []],
        ['pattern' => '/^(?:Enviar correo de atraso preventivo|Send preventive overdue email)$/iu', 'key' => 'Send preventive overdue email', 'params' => []],
        ['pattern' => '/^(?:RESOLVER: Medidas pendientes para orden aprobada|RESOLVE: Pending measurements for approved order)$/iu', 'key' => 'RESOLVE: Pending measurements for approved order', 'params' => []],
        ['pattern' => '/^(?:Resolver bloqueo|Resolve block): (.+) - (.+)$/iu', 'key' => 'Resolve block: :company - :task', 'params' => ['company', 'task']],
    ];

    public static function translate(?string $title): ?string
    {
        if ($title === null || $title === '') {
            return $title;
        }

        $trimmed = trim($title);

        foreach (self::PATTERNS as $entry) {
            if (preg_match($entry['pattern'], $trimmed, $matches) !== 1) {
                continue;
            }

            $replacements = [];
            foreach ($entry['params'] as $index => $name) {
                $replacements[$name] = $matches[$index + 1];
            }

            return __($entry['key'], $replacements);
        }

        return $title;
    }
}
