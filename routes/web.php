<?php

use App\Livewire\Backlog\Index as BacklogIndex;
use App\Livewire\Dashboard\Analytics;
use App\Livewire\Dashboard\Index;
use App\Livewire\Kanban\Board;
use App\Livewire\Orders\TrashBin;
use App\Livewire\Planner\WeeklyPlanner;
use App\Livewire\Resolver\ResolverList;
use App\Livewire\Settings\Substatuses;
use App\Livewire\Settings\TrelloSync;
use App\Livewire\Tasks\TaskList;
use Illuminate\Support\Facades\Route;

Route::get('/', Index::class);
Route::get('/analytics', Analytics::class)->name('analytics');
Route::get('/backlog', BacklogIndex::class);
Route::get('/kanban', Board::class)->name('kanban');
Route::get('/planner', WeeklyPlanner::class);
Route::get('/tasks', TaskList::class);
Route::get('/resolver', ResolverList::class);
Route::get('/trello-sync', TrelloSync::class);
Route::get('/settings/substatuses', Substatuses::class)->name('settings.substatuses');
Route::get('/trash', TrashBin::class)->name('trash');
