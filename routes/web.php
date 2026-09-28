<?php

use App\Livewire\Auth\Login;
use App\Livewire\Backlog\Index as BacklogIndex;
use App\Livewire\Clients\ClientIndex;
use App\Livewire\Dashboard\Analytics;
use App\Livewire\Dashboard\Index;
use App\Livewire\Kanban\Board;
use App\Livewire\Orders\ArchivedOrders;
use App\Livewire\Orders\TrashBin;
use App\Livewire\Overview\OverviewIndex;
use App\Livewire\Planner\WeeklyPlanner;
use App\Livewire\Resolver\ResolverList;
use App\Livewire\Settings\Backups;
use App\Livewire\Settings\Documentation;
use App\Livewire\Settings\LanguageSettings;
use App\Livewire\Settings\ProfileSettings;
use App\Livewire\Settings\Substatuses;
use App\Livewire\Settings\SubtaskPresets;
use App\Livewire\Settings\TrelloMapping;
use App\Livewire\Settings\TrelloSync;
use App\Livewire\Settings\UserManagement;
use App\Livewire\Tasks\TaskList;
use App\Services\TrelloSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Guest Routes
Route::middleware(['guest'])->group(function () {
    Route::get('/login', Login::class)->name('login');
});

// Authenticated Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/', Index::class)->name('dashboard');
    Route::get('/overview', OverviewIndex::class)->name('overview');
    Route::get('/clients', ClientIndex::class)->name('clients');
    Route::get('/analytics', Analytics::class)->name('analytics');
    Route::get('/backlog', BacklogIndex::class)->name('backlog');
    Route::get('/kanban', Board::class)->name('kanban');
    Route::get('/archived', ArchivedOrders::class)->name('archived');
    Route::get('/planner', WeeklyPlanner::class)->name('planner');
    Route::get('/tasks', TaskList::class)->name('tasks');
    Route::get('/resolver', ResolverList::class)->name('resolver');
    Route::get('/trello-sync', TrelloSync::class)->name('trello-sync');
    Route::get('/settings/documentation', Documentation::class)->name('settings.documentation');
    Route::get('/settings/language', LanguageSettings::class)->name('settings.language');
    Route::get('/settings/substatuses', Substatuses::class)->name('settings.substatuses');
    Route::get('/settings/subtasks', SubtaskPresets::class)->name('settings.subtasks');
    Route::get('/settings/trello-mapping', TrelloMapping::class)->name('settings.trello-mapping');
    Route::get('/settings/backups', Backups::class)->name('settings.backups');
    Route::get('/settings/profile', ProfileSettings::class)->name('settings.profile');
    Route::get('/settings/users', UserManagement::class)->name('settings.users');
    Route::get('/trash', TrashBin::class)->name('trash');

    Route::get('/trello-attachment-proxy', function (Request $request) {
        $url = $request->query('url');
        if (empty($url)) {
            abort(404);
        }

        $host = parse_url($url, PHP_URL_HOST);
        $allowedHosts = ['trello.com', 'api.trello.com', 'trello-attachments.s3.amazonaws.com', 'trello-members.s3.amazonaws.com'];

        $isAllowed = false;
        foreach ($allowedHosts as $allowed) {
            if ($host === $allowed || str_ends_with((string) $host, '.'.$allowed)) {
                $isAllowed = true;
                break;
            }
        }

        if (! $isAllowed) {
            abort(403, 'Host not allowed for Trello proxy.');
        }

        $res = app(TrelloSyncService::class)->proxyAttachment($url);

        if (! $res['success']) {
            abort(404, $res['error'] ?? 'Attachment not found');
        }

        return response($res['content'])
            ->header('Content-Type', $res['mime'])
            ->header('Cache-Control', 'public, max-age=86400');
    })->name('trello.attachment-proxy');

    Route::post('/logout', function () {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});

Route::get('/set-locale/{locale}', function (string $locale) {
    if (in_array($locale, ['es', 'en'])) {
        session(['locale' => $locale]);
        cookie()->queue(cookie()->forever('app_locale', $locale));
    }

    return redirect()->back();
})->name('set-locale');
