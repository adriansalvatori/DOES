<?php

namespace App\Casts;

use App\Enums\Substatus;
use App\Support\CustomSubstatus;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class SubstatusCast implements CastsAttributes
{
    /**
     * Cast the given value from the database.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): Substatus|CustomSubstatus|null
    {
        if (is_null($value) || $value === '') {
            return null;
        }

        return Substatus::tryFrom($value) ?? new CustomSubstatus($value);
    }

    /**
     * Prepare the given value for storage in the database.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof Substatus || $value instanceof CustomSubstatus) {
            return $value->value;
        }

        return (string) $value;
    }
}
