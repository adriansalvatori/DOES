<?php

use App\Enums\CoreStatus;
use App\Models\Order;
use App\Models\Substatus;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $archivedNames = Substatus::getArchivedNames();

        Order::query()
            ->where(function ($q) use ($archivedNames) {
                $q->whereIn('substatus', $archivedNames);
                foreach ($archivedNames as $name) {
                    $q->orWhere('substatus', 'like', "%{$name}%");
                }
            })
            ->where(function ($q) {
                $q->where('core_status', '!=', CoreStatus::ARCHIVED->value)
                    ->orWhereNull('archived_at');
            })
            ->get()
            ->each(function (Order $order) {
                $order->update([
                    'core_status' => CoreStatus::ARCHIVED,
                    'archived_at' => $order->archived_at ?? now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Non-reversible data normalization
    }
};
