<?php

namespace App\Services;

class OrderTitleParserService
{
    /**
     * Keywords that identify non-order administrative list headers / markers.
     */
    public static array $incompatibleHeaderKeywords = [
        'TO DO TODAY',
        'REVISAR CON GABY',
        'EN ESPERA',
        'PENDING',
        'KUDOS-YOLANDA DESK-ALTA',
        'PROCESAR POR GABY',
        'Cesar',
        'Adrián',
        'Euralíz',
        'Camila',
    ];

    /**
     * Check if a title string represents a non-order list header.
     */
    public static function isIncompatibleHeader(string $rawTitle): bool
    {
        $rawTitleClean = trim($rawTitle);
        foreach (self::$incompatibleHeaderKeywords as $kw) {
            if (strcasecmp($rawTitleClean, $kw) === 0 || (stripos($rawTitleClean, $kw) === 0 && strlen($rawTitleClean) < 30 && !preg_match('/^wo\s*\d+/i', $rawTitleClean))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Parse a raw card title into structured fields:
     * - wo_number
     * - company_name
     * - responsible_person
     * - task_name
     * - trello_title
     */
    public static function parse(string $rawTitle): array
    {
        $rawTitle = trim($rawTitle);
        $title = $rawTitle;

        // Clean out markdown images & URLs leaking into title
        $title = preg_replace('/!\[.*?\]\(.*?\)/s', '', $title);
        $title = preg_replace('/https?:\/\/\S+/s', '', $title);

        // Take only the first non-empty line if title has multiline description text
        $lines = array_filter(array_map('trim', explode("\n", $title)));
        $title = !empty($lines) ? reset($lines) : $rawTitle;

        $woNumber = null;
        $responsiblePerson = null;
        $companyName = '';
        $taskName = '';

        // 1. Extract WO Number (ONLY "WO" + digits e.g. "WO 16253", "WO 15801", or leading digits e.g. "15012 TAQUITO")
        // Any word after "WO" without numbers belongs to company_name.
        if (preg_match('/^(?:WO\s*\??\s*|wo\s*)(\d{3,6})\b/i', $title, $matches)) {
            $woNumber = "WO {$matches[1]}";
            $title = trim(substr($title, strlen($matches[0])));
        } elseif (preg_match('/^(\d{4,6})\b/i', $title, $matches)) {
            $woNumber = "WO {$matches[1]}";
            $title = trim(substr($title, strlen($matches[0])));
        } elseif (preg_match('/^WO\s+/i', $title)) {
            // Strip leading "WO " if it's followed by words (e.g. "WO SUPERMERCADOS", "WO KUDOS", "WO FUEGO")
            $woNumber = null;
            $title = preg_replace('/^WO\s+/i', '', $title);
        }

        // Clean leading symbols/dashes after removing WO
        $title = trim($title, " \t\n\r\0\x0B-:");

        // 2. Extract Responsible Person from parentheses e.g. "( MARCELA )"
        if (preg_match('/\(\s*([^)]+)\s*\)/', $title, $matches)) {
            $responsiblePerson = strtoupper(trim($matches[1]));
            
            // Split title around the parenthesis
            $parts = explode($matches[0], $title, 2);
            $beforeParen = trim($parts[0], " \t\n\r\0\x0B-:");
            $afterParen = isset($parts[1]) ? trim($parts[1], " \t\n\r\0\x0B-:") : '';

            if (!empty($beforeParen)) {
                $companyName = $beforeParen;
            }
            if (!empty($afterParen)) {
                $taskName = $afterParen;
            }
        }

        // 3. If no parenthesis was found, check for hyphens e.g. "LA CHAPINA BAKERY- VARIOS ITEMS"
        if (empty($companyName) && empty($taskName)) {
            $titleClean = trim($title, " \t\n\r\0\x0B-:");
            
            if (str_contains($titleClean, ' - ')) {
                $parts = explode(' - ', $titleClean, 2);
                $companyName = trim($parts[0]);
                $taskName = trim($parts[1]);
            } elseif (str_contains($titleClean, '-')) {
                $parts = explode('-', $titleClean, 2);
                $companyName = trim($parts[0]);
                $taskName = trim($parts[1]);
            } else {
                $companyName = $titleClean;
                $taskName = $titleClean;
            }
        }

        // Final sanitation of company and task name
        $companyName = trim(preg_replace('/[*_#]+/', '', $companyName), " \t\n\r\0\x0B-:");
        $taskName = trim(preg_replace('/[*_#]+/', '', $taskName), " \t\n\r\0\x0B-:");

        // Fallbacks if empty
        if (empty($companyName)) {
            $companyName = $rawTitle;
        }
        if (empty($taskName)) {
            $taskName = $companyName;
        }

        return [
            'wo_number' => $woNumber,
            'company_name' => $companyName,
            'responsible_person' => $responsiblePerson,
            'task_name' => $taskName,
            'trello_title' => $rawTitle,
            'is_incompatible' => self::isIncompatibleHeader($rawTitle),
        ];
    }

    /**
     * Reconstruct full Trello card title from structured fields.
     */
    public static function buildTitle(array|object $data): string
    {
        $wo = is_array($data) ? ($data['wo_number'] ?? '') : ($data->wo_number ?? '');
        $company = is_array($data) ? ($data['company_name'] ?? '') : ($data->company_name ?? '');
        $responsible = is_array($data) ? ($data['responsible_person'] ?? '') : ($data->responsible_person ?? '');
        $task = is_array($data) ? ($data['task_name'] ?? '') : ($data->task_name ?? '');

        $parts = [];
        if (!empty($wo)) {
            $parts[] = trim($wo);
        }
        if (!empty($company)) {
            $parts[] = trim($company);
        }
        if (!empty($responsible)) {
            $parts[] = '(' . trim($responsible) . ')';
        }
        if (!empty($task) && strtolower(trim($task)) !== strtolower(trim($company))) {
            $parts[] = trim($task);
        }

        return implode(' ', $parts);
    }
}
