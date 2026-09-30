<?php

namespace App\Providers;

use App\Contracts\WorkOrderNumberGenerator;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Observers\OrderObserver;
use App\Observers\RelatedTaskObserver;
use App\Services\WorkOrder\QuickBooksWorkOrderGenerator;
use App\Services\WorkOrder\SequentialWorkOrderGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WorkOrderNumberGenerator::class, function ($app) {
            $driver = config('work_orders.driver', 'sequential');

            return match ($driver) {
                'quickbooks' => $app->make(QuickBooksWorkOrderGenerator::class),
                'sequential' => $app->make(SequentialWorkOrderGenerator::class),
                default => $app->make(SequentialWorkOrderGenerator::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);
        RelatedTask::observe(RelatedTaskObserver::class);
    }
}
