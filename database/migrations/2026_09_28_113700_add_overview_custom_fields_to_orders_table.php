<?php

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
            if (! Schema::hasColumn('orders', 'manual_creation_date')) {
                $table->date('manual_creation_date')->nullable()->after('created_at');
            }
            if (! Schema::hasColumn('orders', 'production_sent_at')) {
                $table->timestamp('production_sent_at')->nullable()->after('manual_creation_date');
            }
            if (! Schema::hasColumn('orders', 'production_note')) {
                $table->text('production_note')->nullable()->after('task_name');
            }
            if (! Schema::hasColumn('orders', 'estimate_invoice_number')) {
                $table->string('estimate_invoice_number')->nullable()->after('production_note');
            }
            if (! Schema::hasColumn('orders', 'review_status')) {
                $table->string('review_status')->nullable()->after('estimate_invoice_number'); // 'CS', 'CAMILA', null
            }
            if (! Schema::hasColumn('orders', 'installation_type')) {
                $table->string('installation_type')->nullable()->after('review_status'); // 'CLIENTE', 'KUDOS', null
            }
            if (! Schema::hasColumn('orders', 'overview_checked')) {
                $table->boolean('overview_checked')->default(false)->after('installation_type');
            }
            if (! Schema::hasColumn('orders', 'delivery_note')) {
                $table->text('delivery_note')->nullable()->after('overview_checked');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'manual_creation_date',
                'production_sent_at',
                'production_note',
                'estimate_invoice_number',
                'review_status',
                'installation_type',
                'overview_checked',
                'delivery_note',
            ]);
        });
    }
};
