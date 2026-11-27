<?php

use App\Support\TrelloCutover;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('db:backup')->daily();
Schedule::command('deliverables:daily-cycle')->dailyAt('07:00');
Schedule::command('deliverables:daily-cycle --no-notify')->dailyAt('14:30');
Schedule::command('reports:send weekly')->weeklyOn(1, '07:30');
Schedule::command('reports:send biweekly')->weeklyOn(1, '07:45');
Schedule::command('trello:sync')->everyFifteenMinutes()->skip(fn (): bool => TrelloCutover::isComplete());
