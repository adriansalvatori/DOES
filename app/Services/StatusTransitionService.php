<?php

namespace App\Services;

use App\Enums\CoreStatus;
use App\Enums\Substatus as SubstatusEnum;
use App\Models\Order;
use App\Models\Substatus as SubstatusModel;
use Illuminate\Support\Collection;

class StatusTransitionService
{
    /**
     * Get all valid substatuses (both Enum & DB) for a given CoreStatus, plus global substatuses.
     */
    public function getValidSubstatuses(CoreStatus|string|null $coreStatus): Collection
    {
        if (! $coreStatus) {
            $dbAll = SubstatusModel::query()->orderBy('sort_order')->orderBy('id')->get();

            return $dbAll->isNotEmpty() ? $dbAll : collect(SubstatusEnum::cases());
        }

        $statusEnum = $coreStatus instanceof CoreStatus ? $coreStatus : CoreStatus::tryFrom($coreStatus);

        if (! $statusEnum) {
            $dbAll = SubstatusModel::query()->orderBy('sort_order')->orderBy('id')->get();

            return $dbAll->isNotEmpty() ? $dbAll : collect(SubstatusEnum::cases());
        }

        // Query DB substatuses that belong to this core_status OR are marked is_global
        $dbSubstatuses = SubstatusModel::query()
            ->where(function ($q) use ($statusEnum) {
                if (CoreStatus::isPendingDesign($statusEnum)) {
                    $designerQueueValues = array_map(fn ($s) => $s->value, CoreStatus::designerQueueStatuses());
                    $q->whereIn('core_status', $designerQueueValues);
                } else {
                    $q->where('core_status', $statusEnum->value);
                }
                $q->orWhere('is_global', true);
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($dbSubstatuses->isEmpty()) {
            $enumCases = collect($statusEnum->validSubstatuses());
            foreach (SubstatusEnum::cases() as $subEnum) {
                if ($subEnum->isGlobal() && ! $enumCases->contains($subEnum)) {
                    $enumCases->push($subEnum);
                }
            }

            return $enumCases->values();
        }

        return $dbSubstatuses;
    }

    /**
     * Get default substatus enum for a CoreStatus.
     */
    public function getDefaultSubstatus(CoreStatus|string|null $coreStatus): ?SubstatusEnum
    {
        if (! $coreStatus) {
            return null;
        }

        $statusEnum = $coreStatus instanceof CoreStatus ? $coreStatus : CoreStatus::tryFrom($coreStatus);

        if (! $statusEnum) {
            return null;
        }

        // Check DB for default substatus first
        $dbDefault = SubstatusModel::where('core_status', $statusEnum->value)
            ->where('is_default', true)
            ->first();

        if ($dbDefault && ($enumCase = SubstatusEnum::tryFrom($dbDefault->name))) {
            return $enumCase;
        }

        return $statusEnum->defaultSubstatus();
    }

    /**
     * Normalize an order's substatus when its core_status changes.
     */
    public function normalizeSubstatusOnCoreStatusChange(Order $order, CoreStatus|string $newCoreStatus): void
    {
        $statusEnum = $newCoreStatus instanceof CoreStatus ? $newCoreStatus : CoreStatus::tryFrom($newCoreStatus);

        if (! $statusEnum) {
            return;
        }

        $currentSubstatus = $order->substatus;

        // If currently no substatus, assign default for new core status
        if (! $currentSubstatus) {
            $defaultSub = $this->getDefaultSubstatus($statusEnum);
            if ($defaultSub) {
                $order->substatus = $defaultSub;
            }

            return;
        }

        // If current substatus is global (e.g. TICKET, POTENTIAL CUSTOMER, URGENTE), preserve it
        if ($currentSubstatus->isGlobal()) {
            return;
        }

        // Handle archived_at timestamp
        if ($statusEnum === CoreStatus::ARCHIVED && ! $order->archived_at) {
            $order->archived_at = now();
        } elseif ($statusEnum !== CoreStatus::ARCHIVED && $order->archived_at) {
            $order->archived_at = null;
        }

        // Check if current substatus belongs to the new CoreStatus
        $validEnumCases = $statusEnum->validSubstatuses();
        $isValid = in_array($currentSubstatus, $validEnumCases, true);

        if (! $isValid) {
            $defaultSub = $this->getDefaultSubstatus($statusEnum);
            $order->substatus = $defaultSub;
        }
    }

    /**
     * Infer and update an order's core_status when its substatus changes.
     */
    public function updateCoreStatusFromSubstatus(Order $order, SubstatusEnum|string|null $substatus): void
    {
        if (! $substatus) {
            return;
        }

        $subName = $substatus instanceof SubstatusEnum ? $substatus->value : $substatus;
        $subEnum = $substatus instanceof SubstatusEnum ? $substatus : SubstatusEnum::tryFrom($substatus);

        // Do not change core_status if the substatus is global (flag)
        if ($subEnum && $subEnum->isGlobal()) {
            return;
        }

        $dbSub = SubstatusModel::where('name', $subName)->first();
        if ($dbSub && $dbSub->is_global) {
            return;
        }

        $targetCoreStatus = null;

        if ($dbSub && $dbSub->core_status) {
            $targetCoreStatus = $dbSub->core_status;
        } elseif ($subEnum && ($enumDefaultCore = $subEnum->defaultCoreStatus())) {
            $targetCoreStatus = $enumDefaultCore;
        } elseif (SubstatusModel::isArchivedSubstatus($subName)) {
            $targetCoreStatus = CoreStatus::ARCHIVED;
        }

        if (! $targetCoreStatus) {
            return;
        }

        // If order is currently in a designer queue or TO DO TODAY and new substatus is PONER EN ALTA, AJUSTES DE PRODUCCION, or CAMBIOS CAMILA, keep current status
        $isDesignerOrToday = $order->core_status && (CoreStatus::isPendingDesign($order->core_status) || $order->core_status === CoreStatus::TO_DO_TODAY);
        if ($isDesignerOrToday && in_array($subName, ['PONER EN ALTA', 'AJUSTES DE PRODUCCIÓN', 'CAMBIOS CAMILA', SubstatusEnum::PONER_EN_ALTA->value, SubstatusEnum::AJUSTES_PRODUCCION->value, SubstatusEnum::CAMBIOS_CAMILA->value], true)) {
            return;
        }

        if ($order->core_status !== $targetCoreStatus) {
            $order->core_status = $targetCoreStatus;
            if ($targetCoreStatus === CoreStatus::ARCHIVED && ! $order->archived_at) {
                $order->archived_at = now();
            } elseif ($targetCoreStatus !== CoreStatus::ARCHIVED && $order->archived_at) {
                $order->archived_at = null;
            }
        } elseif ($targetCoreStatus === CoreStatus::ARCHIVED && ! $order->archived_at) {
            $order->archived_at = now();
        }
    }
}
