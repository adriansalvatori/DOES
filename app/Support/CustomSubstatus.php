<?php

namespace App\Support;

use App\Enums\CoreStatus;
use App\Models\Substatus as SubstatusModel;
use Stringable;

class CustomSubstatus implements Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function label(): string
    {
        return $this->value;
    }

    public function customBadgeStyle(): ?string
    {
        static $dbCache = null;
        if ($dbCache === null) {
            try {
                $dbCache = SubstatusModel::all()->keyBy('name');
            } catch (\Throwable $e) {
                $dbCache = collect();
            }
        }

        $subModel = $dbCache->get($this->value);
        if ($subModel && $subModel->bg_color && $subModel->text_color) {
            return "background-color: {$subModel->bg_color}; color: {$subModel->text_color}; border-color: {$subModel->border_color};";
        }

        return null;
    }

    public function getInlineBadgeStyle(): string
    {
        return $this->customBadgeStyle() ?? '';
    }

    public function getInlineBadgeStyleAttribute(): string
    {
        return $this->customBadgeStyle() ?? '';
    }

    public function badgeStyle(): string
    {
        if ($custom = $this->customBadgeStyle()) {
            return $custom;
        }

        return 'bg-stone-100 text-stone-700 border-stone-200 font-medium';
    }

    public function isGlobal(): bool
    {
        return false;
    }

    public function defaultCoreStatus(): ?CoreStatus
    {
        return null;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
