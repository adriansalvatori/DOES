<?php

/*
|--------------------------------------------------------------------------
| Rebuild routes (phase 5)
|--------------------------------------------------------------------------
|
| New screens built on the domain layer live under /v2 while the legacy
| screens keep running. Each route registers only once its component
| exists. Phase 6 moves them to their final paths and removes the
| legacy screens.
|
*/

use App\Livewire\Analytics\AnalyticsPage;
use App\Livewire\Backlog\BacklogPage;
use App\Livewire\Board\BoardPage;
use App\Livewire\Overview\OverviewPage;
use App\Livewire\PlanWeek\PlanWeekPage;
use App\Livewire\Resolver\ResolverPage;
use App\Livewire\Settings\BoardSettings;
use App\Livewire\Settings\ReasonsAndFlags;
use App\Livewire\Settings\TaskTemplates;
use App\Livewire\Today\TodayPage;
use App\Livewire\WorkOrders\WorkOrderIndex;
use App\Livewire\WorkOrders\WorkOrderShow;
use Illuminate\Support\Facades\Route;

$rebuildScreens = [
    ['/today', TodayPage::class, 'today'],
    ['/board', BoardPage::class, 'board'],
    ['/plan-week', PlanWeekPage::class, 'plan-week'],
    ['/work-orders', WorkOrderIndex::class, 'work-orders.index'],
    ['/work-orders/{workOrder}', WorkOrderShow::class, 'work-orders.show'],
    ['/resolver', ResolverPage::class, 'resolver'],
    ['/overview', OverviewPage::class, 'overview'],
    ['/backlog', BacklogPage::class, 'backlog'],
    ['/analytics', AnalyticsPage::class, 'analytics'],
    ['/settings/reasons', ReasonsAndFlags::class, 'settings.reasons'],
    ['/settings/task-templates', TaskTemplates::class, 'settings.task-templates'],
    ['/settings/board', BoardSettings::class, 'settings.board'],
];

Route::middleware(['auth'])->prefix('v2')->name('v2.')->group(function () use ($rebuildScreens) {
    foreach ($rebuildScreens as [$uri, $component, $name]) {
        if (class_exists($component)) {
            Route::get($uri, $component)->name($name);
        }
    }
});
