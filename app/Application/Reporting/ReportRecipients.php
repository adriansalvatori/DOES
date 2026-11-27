<?php

namespace App\Application\Reporting;

use App\Enums\UserRole;
use App\Models\Setting;
use App\Models\User;

/**
 * Who receives the emailed reports: the addresses saved on the Analytics page, or else every active admin.
 * Reserved example domains (example.com, .net, .org) are never mailed.
 */
class ReportRecipients
{
    public const SETTING = 'report_recipients';

    /**
     * @return list<string>
     */
    public function emails(): array
    {
        $saved = $this->saved();

        $emails = $saved !== []
            ? $saved
            : User::query()->where('active', true)->withAnyRole(UserRole::ADMIN)->orderBy('id')->pluck('email')->all();

        return array_values(array_unique(array_filter($emails, fn (?string $email): bool => filled($email) && ! $this->isReserved($email))));
    }

    /**
     * The addresses typed on the Analytics page (empty means "every admin").
     *
     * @return list<string>
     */
    public function saved(): array
    {
        return self::parse((string) Setting::get(self::SETTING, ''));
    }

    /**
     * @param  list<string>  $emails
     */
    public function save(array $emails): void
    {
        Setting::set(self::SETTING, implode(', ', $emails));
    }

    /**
     * @return list<string>
     */
    public static function parse(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('~[\s,;]+~', strtolower($text)) ?: [])));
    }

    private function isReserved(string $email): bool
    {
        return preg_match('~@(.+\.)?example\.(com|net|org)$~i', $email) === 1;
    }
}
