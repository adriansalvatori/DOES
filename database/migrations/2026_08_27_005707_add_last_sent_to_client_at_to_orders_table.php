<?php

use App\Enums\CoreStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('last_sent_to_client_at')->nullable()->after('client_last_response');
        });

        // Backfill existing ENVIADO_AL_CLIENTE orders using the latest status change OrderEvent, or fallback to updated_at
        $clientOrders = Order::where('core_status', CoreStatus::ENVIADO_AL_CLIENTE->value)->get();
        foreach ($clientOrders as $order) {
            $lastEvent = OrderEvent::where('order_id', $order->id)
                ->where('event_type', 'CORE_STATUS_CHANGED')
                ->where('new_value', CoreStatus::ENVIADO_AL_CLIENTE->value)
                ->latest('created_at')
                ->first();

            $sentAt = $lastEvent?->created_at ?? $order->updated_at ?? now();
            $order->update(['last_sent_to_client_at' => $sentAt]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('last_sent_to_client_at');
        });
    }
};
