<?php

use App\Livewire\Backlog\Index as BacklogIndex;
use App\Livewire\Dashboard\Analytics;
use App\Livewire\Dashboard\Index;
use App\Livewire\Kanban\Board;
use App\Livewire\Orders\TrashBin;
use App\Livewire\Planner\WeeklyPlanner;
use App\Livewire\Resolver\ResolverList;
use App\Livewire\Settings\LanguageSettings;
use App\Livewire\Settings\Substatuses;
use App\Livewire\Settings\SubtaskPresets;
use App\Livewire\Settings\TrelloSync;
use App\Livewire\Tasks\TaskList;
use Illuminate\Support\Facades\Route;

Route::get('/', Index::class)->name('dashboard');
Route::get('/analytics', Analytics::class)->name('analytics');
Route::get('/backlog', BacklogIndex::class)->name('backlog');
Route::get('/kanban', Board::class)->name('kanban');
Route::get('/planner', WeeklyPlanner::class)->name('planner');
Route::get('/tasks', TaskList::class)->name('tasks');
Route::get('/resolver', ResolverList::class)->name('resolver');
Route::get('/trello-sync', TrelloSync::class)->name('trello-sync');
Route::get('/settings/language', LanguageSettings::class)->name('settings.language');
Route::get('/settings/substatuses', Substatuses::class)->name('settings.substatuses');
Route::get('/settings/subtasks', SubtaskPresets::class)->name('settings.subtasks');
Route::get('/trash', TrashBin::class)->name('trash');

Route::get('/set-locale/{locale}', function (string $locale) {
    if (in_array($locale, ['es', 'en'])) {
        session(['locale' => $locale]);
        cookie()->queue(cookie()->forever('app_locale', $locale));
    }

    return redirect()->back();
})->name('set-locale');
