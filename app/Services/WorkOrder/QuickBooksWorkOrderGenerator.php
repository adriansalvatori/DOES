<?php

namespace App\Services\WorkOrder;

use App\Contracts\WorkOrderNumberGenerator;
use RuntimeException;

/**
 * Driver placeholder for future QuickBooks integration.
 * When QuickBooks is connected, this class will interact with QuickBooks API
 * to query or generate the official Work Order / Estimate / Invoice number.
 */
class QuickBooksWorkOrderGenerator implements WorkOrderNumberGenerator
{
    /**
     * Generate or fetch the next work order number from QuickBooks.
     *
     * @param  array<string, mixed>  $context
     */
    public function generateNext(array $context = []): string
    {
        // TODO: Implement integration with QuickBooks Online API using $context['company_name'], etc.
        throw new RuntimeException('La integración con QuickBooks para generar WO numbers aún no está configurada.');
    }

    /**
     * Generate only the numeric digits for the next work order number from QuickBooks.
     *
     * @param  array<string, mixed>  $context
     */
    public function generateNextDigits(array $context = []): string
    {
        $full = $this->generateNext($context);

        return trim((string) preg_replace('/^WO\s*/i', '', $full));
    }

    /**
     * Get the latest recorded work order number from QuickBooks.
     */
    public function getLastNumber(): ?string
    {
        // TODO: Query QuickBooks API for latest document number.
        return null;
    }
}
