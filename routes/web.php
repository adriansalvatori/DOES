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
use App\Livewire\Portal\ClientPortal;
use App\Livewire\Resolver\ResolverList;
use App\Livewire\Settings\Backups;
use App\Livewire\Settings\ColorCoding;
use App\Livewire\Settings\Documentation;
use App\Livewire\Settings\InstallationTypes;
use App\Livewire\Settings\LanguageSettings;
use App\Livewire\Settings\ProfileSettings;
use App\Livewire\Settings\Substatuses;
use App\Livewire\Settings\SubtaskPresets;
use App\Livewire\Settings\TrelloMapping;
use App\Livewire\Settings\TrelloSync;
use App\Livewire\Settings\UserManagement;
use App\Livewire\Tasks\TaskList;
use App\Models\User;
use App\Services\DemoEnvironmentService;
use App\Services\TrelloSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Public Client Portal Routes (Auto-authenticated via unique QR token)
Route::get('/c/{token}', ClientPortal::class)->name('client.portal');
Route::get('/portal/{token}', function (string $token) {
    return redirect()->route('client.portal', ['token' => $token]);
});
// Guest Routes
Route::middleware(['guest'])->group(function () {
    Route::get('/login', Login::class)->name('login');
    Route::get('/reset-password/{token}', function (Request $request, string $token) {
        return redirect()->route('login')->with('status', __('Enlace de recuperación validado.'));
    })->name('password.reset');
});

// Demo Fast Switch / Login (Accessible directly from login buttons without guest-check redirect loops)
Route::get('/demo-login/{role}', function (string $role) {
    $emailMap = [
        'admin' => 'admin@kudos.com',
        'manager' => 'camila@kudos.com',
        'designer' => 'adrian@kudos.com',
        'comercial' => 'ventas@kudos.com',
    ];

    $email = $emailMap[strtolower(trim($role))] ?? 'admin@kudos.com';
    $demoService = app(DemoEnvironmentService::class);
    $demoService->enableDemo();

    $user = User::on('demo')->where('email', $email)->first();
    if ($user) {
        Auth::login($user);
        $user->update(['last_login_at' => now()]);
        session()->regenerate();
        session()->put(DemoEnvironmentService::DEMO_SESSION_KEY, true);
        session()->save();
        cookie()->queue(cookie()->forever('kudos_demo_mode', '1'));
    }

    return redirect()->route('dashboard');
})->name('demo.direct-login');

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
    Route::get('/settings/color-coding', ColorCoding::class)->name('settings.color-coding');
    Route::get('/settings/language', LanguageSettings::class)->name('settings.language');
    Route::get('/settings/substatuses', Substatuses::class)->name('settings.substatuses');
    Route::get('/settings/installation-types', InstallationTypes::class)->name('settings.installation-types');
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
        app(DemoEnvironmentService::class)->disableDemo();
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
