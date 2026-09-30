<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DemoEnvironmentService
{
    public const DEMO_SESSION_KEY = 'is_demo';

    /**
     * Check if the current session or request is running in demo mode.
     */
    public function isDemo(): bool
    {
        return session(self::DEMO_SESSION_KEY, false) === true
            || (request() && request()->cookie('kudos_demo_mode') === '1')
            || config('database.default') === 'demo';
    }

    /**
     * Enable the demo database and disconnect Trello completely.
     */
    public function enableDemo(bool $forceInTesting = false): void
    {
        session()->put(self::DEMO_SESSION_KEY, true);
        cookie()->queue(cookie()->forever('kudos_demo_mode', '1'));

        if (app()->environment('testing') && ! $forceInTesting && config('database.connections.sqlite.database') === ':memory:') {
            return;
        }

        config([
            'database.default' => 'demo',
            'database.connections.sqlite.database' => database_path('demo.database.sqlite'),
            'services.trello.api_key' => null,
            'services.trello.api_secret' => null,
            'services.trello.token' => null,
            'services.trello.board_id' => null,
        ]);

        DB::purge('sqlite');
        DB::purge('demo');
        DB::setDefaultConnection('demo');
        DB::reconnect('demo');
        DB::reconnect('sqlite');

        if (class_exists(Auth::class)) {
            Auth::forgetGuards();
        }
    }

    /**
     * Disable demo mode and restore the default database connection.
     */
    public function disableDemo(): void
    {
        session()->forget(self::DEMO_SESSION_KEY);
        cookie()->queue(cookie()->forget('kudos_demo_mode'));

        if (app()->environment('testing') && config('database.connections.sqlite.database') === ':memory:') {
            return;
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => env('DB_DATABASE', database_path('database.sqlite')),
        ]);

        DB::purge('sqlite');
        DB::purge('demo');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        if (class_exists(Auth::class)) {
            Auth::forgetGuards();
        }
    }
}
