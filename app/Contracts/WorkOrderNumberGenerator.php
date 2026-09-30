<?php

namespace App\Contracts;

interface WorkOrderNumberGenerator
{
    /**
     * Generate or fetch the next available work order number with prefix (e.g., "WO 16367").
     *
     * @param  array<string, mixed>  $context  Optional context (company_name, task_name, client_id, etc.) for external APIs like QuickBooks.
     */
    public function generateNext(array $context = []): string;

    /**
     * Generate or fetch only the numeric digits string of the next work order number (e.g., "16367").
     *
     * @param  array<string, mixed>  $context
     */
    public function generateNextDigits(array $context = []): string;

    /**
     * Get the latest recorded work order number (e.g. "16366"), or null if none found.
     */
    public function getLastNumber(): ?string;
}
