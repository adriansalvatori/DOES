<?php

namespace App\Domain\Delivery\Enums;

enum TaskKind: string
{
    case WORK = 'work';
    case GATE_SEND_PROOF = 'gate_send_proof';
    case GATE_PREPARE_PRODUCTION_FILES = 'gate_prepare_production_files';
    case GATE_CUSTOM = 'gate_custom';

    public function label(): string
    {
        return __('enums.task_kind.'.$this->value);
    }
}
