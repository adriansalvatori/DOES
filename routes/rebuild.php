<?php

/*
|--------------------------------------------------------------------------
| Delivery screens (final routes)
|--------------------------------------------------------------------------
|
| Included from the authenticated group in routes/web.php. The legacy URLs
| and route names stay alive as redirects so old links, bookmarks and code
| that still calls route('kanban') keep resolving.
|
*/

use App\Application\Reporting\ReportPeriod;
use App\Http\Controllers\TeamReportController;
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

Route::get('/', TodayPage::class)->name('today');
Route::get('/board', BoardPage::class)->name('board');
Route::get('/plan-week', PlanWeekPage::class)->name('plan-week');
Route::get('/work-orders', WorkOrderIndex::class)->name('work-orders.index');
Route::get('/work-orders/{workOrder}', WorkOrderShow::class)->name('work-orders.show');
Route::get('/resolver', ResolverPage::class)->name('resolver');
Route::get('/overview', OverviewPage::class)->name('overview');
Route::get('/backlog', BacklogPage::class)->name('backlog');
Route::get('/analytics', AnalyticsPage::class)->name('analytics');
Route::get('/analytics/reports/{kind}', TeamReportController::class)->whereIn('kind', ReportPeriod::KINDS)->name('reports.team');
Route::get('/settings/reasons', ReasonsAndFlags::class)->name('settings.reasons');
Route::get('/settings/task-templates', TaskTemplates::class)->name('settings.task-templates');
Route::get('/settings/board', BoardSettings::class)->name('settings.board');

/*
| Legacy URLs and route names (kept as redirects).
*/
Route::redirect('/dashboard', '/')->name('dashboard');
Route::redirect('/today', '/');
Route::redirect('/kanban', '/board')->name('kanban');
Route::redirect('/planner', '/plan-week')->name('planner');
Route::redirect('/tasks', '/')->name('tasks');
Route::get('/archived', fn () => redirect()->route('work-orders.index', ['tab' => 'done']))->name('archived');
Route::redirect('/trello-sync', '/settings')->name('trello-sync');
Route::redirect('/settings/trello-mapping', '/settings')->name('settings.trello-mapping');
Route::redirect('/settings/substatuses', '/settings/reasons')->name('settings.substatuses');
Route::redirect('/settings/subtasks', '/settings/task-templates')->name('settings.subtasks');

/*
| The /v2 paths used while the screens were built: redirect to the final paths.
*/
Route::prefix('v2')->name('v2.')->group(function () {
    Route::redirect('/today', '/')->name('today');
    Route::redirect('/board', '/board')->name('board');
    Route::redirect('/plan-week', '/plan-week')->name('plan-week');
    Route::redirect('/work-orders', '/work-orders')->name('work-orders.index');
    Route::get('/work-orders/{workOrder}', fn (string $workOrder) => redirect()->route('work-orders.show', $workOrder))->name('work-orders.show');
    Route::redirect('/resolver', '/resolver')->name('resolver');
    Route::redirect('/overview', '/overview')->name('overview');
    Route::redirect('/backlog', '/backlog')->name('backlog');
    Route::redirect('/analytics', '/analytics')->name('analytics');
    Route::redirect('/settings/reasons', '/settings/reasons')->name('settings.reasons');
    Route::redirect('/settings/task-templates', '/settings/task-templates')->name('settings.task-templates');
    Route::redirect('/settings/board', '/settings/board')->name('settings.board');
});
