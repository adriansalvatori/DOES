<?php

namespace App\Providers;

use App\Contracts\WorkOrderNumberGenerator;
use App\Models\Order;
use App\Models\RelatedTask;
use App\Observers\OrderObserver;
use App\Observers\RelatedTaskObserver;
use App\Services\WorkOrder\QuickBooksWorkOrderGenerator;
use App\Services\WorkOrder\SequentialWorkOrderGenerator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Blaze;

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

        Blaze::optimize()
            ->in(resource_path('views/components'), compile: true, memo: false, fold: false)
            ->in(resource_path('views/components/layouts'), compile: false, memo: false, fold: false);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
            if (config('app.url')) {
                URL::forceRootUrl(config('app.url'));
            }
        } elseif (request()->header('x-forwarded-proto') === 'https') {
            URL::forceScheme('https');
        }
    }
}
