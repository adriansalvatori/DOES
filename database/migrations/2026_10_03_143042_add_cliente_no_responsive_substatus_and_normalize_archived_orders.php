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
        Substatus::updateOrCreate(
            ['name' => 'CLIENTE NO RESPONSIVE'],
            [
                'core_status' => CoreStatus::ARCHIVED->value,
                'is_default' => false,
                'is_global' => false,
                'bg_color' => '#FEF3C7',
                'text_color' => '#78350F',
                'border_color' => '#FDE68A',
                'is_system' => true,
                'sort_order' => 26,
            ]
        );

        Substatus::updateOrCreate(
            ['name' => 'FINALIZADA !'],
            [
                'core_status' => CoreStatus::ARCHIVED->value,
                'is_default' => true,
                'is_global' => false,
                'bg_color' => '#D1FAE5',
                'text_color' => '#065F46',
                'border_color' => '#A7F3D0',
                'is_system' => true,
                'sort_order' => 24,
            ]
        );

        $validArchivedSubstatuses = [
            'FINALIZADA !',
            'CANCELADA',
            'CANCELADA POR CLIENTE',
            'CANCELADA POR CAMILA',
            'NO REALIZADA / TRANSFERIDA',
            'CLIENTE NO RESPONSIVE',
        ];

        // Normalize existing archived orders that have active or null substatuses
        Order::query()
            ->where('core_status', CoreStatus::ARCHIVED)
            ->get()
            ->each(function (Order $order) use ($validArchivedSubstatuses) {
                $subVal = $order->substatus instanceof App\Enums\Substatus ? $order->substatus->value : (string) $order->substatus;
                if (! in_array($subVal, $validArchivedSubstatuses, true)) {
                    $order->update(['substatus' => App\Enums\Substatus::FINALIZADA]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Substatus::where('name', 'CLIENTE NO RESPONSIVE')->delete();
    }
};
