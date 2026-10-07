<?php

namespace App\Casts;

use App\Enums\CoreStatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class CoreStatusCast implements CastsAttributes
{
    /**
     * Cast the given value from the database to CoreStatus enum.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CoreStatus
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        if ($value instanceof CoreStatus) {
            return $value;
        }

        $trimmed = trim((string) $value);
        $direct = CoreStatus::tryFrom($trimmed);
        if ($direct !== null) {
            return $direct;
        }

        $upper = mb_strtoupper($trimmed);
        $fromUpper = CoreStatus::tryFrom($upper);
        if ($fromUpper !== null) {
            return $fromUpper;
        }

        // Safe fallback for lowercase 'entrante' or variants
        return CoreStatus::ENTRANTE;
    }

    /**
     * Prepare the given value for storage in the database.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof CoreStatus) {
            return $value->value;
        }

        $trimmed = trim((string) $value);
        $upper = mb_strtoupper($trimmed);
        $enum = CoreStatus::tryFrom($trimmed) ?? CoreStatus::tryFrom($upper);

        return $enum ? $enum->value : $upper;
    }
}
