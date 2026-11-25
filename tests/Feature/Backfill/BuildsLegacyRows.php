<?php

namespace Tests\Feature\Backfill;

use App\Infrastructure\Backfill\LegacyBackfill;
use Illuminate\Support\Facades\DB;

/**
 * Inserts legacy rows with the query builder so the tests do not depend on the legacy models and casts.
 */
trait BuildsLegacyRows
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function legacyOrder(array $attributes = []): int
    {
        return DB::table('orders')->insertGetId(array_merge([
            'company_name' => 'ACME',
            'task_name' => 'Sign',
            'core_status' => 'ENTRANTE',
            'wo_number' => null,
            'in_workspace' => true,
            'created_at' => '2026-10-01 10:00:00',
            'updated_at' => '2026-10-01 10:00:00',
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function legacyDesigner(array $attributes = []): int
    {
        return DB::table('designers')->insertGetId(array_merge([
            'name' => 'Designer '.fake()->unique()->numberBetween(1, 99999),
            'active' => true,
            'color_type' => 'cyan',
            'is_lead' => false,
            'is_external' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function legacyTask(int $orderId, array $attributes = []): int
    {
        return DB::table('related_tasks')->insertGetId(array_merge([
            'order_id' => $orderId,
            'title' => 'Task',
            'status' => 'todo',
            'priority' => 'normal',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    protected function runBackfill(): array
    {
        return app(LegacyBackfill::class)->run();
    }

    protected function row(string $table, int $id): object
    {
        return DB::table($table)->where('id', $id)->first();
    }
}
