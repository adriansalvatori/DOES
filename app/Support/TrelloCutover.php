<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Single switch for the hard cutover from Trello to native comments, descriptions and Drive media.
 * Before the cutover the app behaves as it always has (Trello is the source of truth);
 * after it, Trello is never read from or written to again.
 */
class TrelloCutover
{
    public const SETTING_KEY = 'trello_cutover_completed_at';

    public static function isComplete(): bool
    {
        return ! empty(Setting::get(self::SETTING_KEY));
    }

    public static function completedAt(): ?Carbon
    {
        $value = Setting::get(self::SETTING_KEY);

        return $value ? Carbon::parse($value) : null;
    }

    public static function markComplete(): void
    {
        Setting::set(self::SETTING_KEY, now()->toIso8601String());
    }
}
