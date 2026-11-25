<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetAppLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = ['es', 'en'];
        $defaultLocale = config('app.locale', 'en');

        $candidates = [
            $request->query('lang'),
            session('locale'),
            rescue(fn () => $request->user()?->getPreference('locale'), null, false),
            $request->cookie('app_locale'),
        ];

        $explicitLocale = collect($candidates)->first(fn ($candidate) => in_array($candidate, $supportedLocales, true));
        $locale = $explicitLocale ?? (in_array($defaultLocale, $supportedLocales, true) ? $defaultLocale : 'en');

        App::setLocale($locale);
        Carbon::setLocale($locale);
        if ($explicitLocale !== null) {
            session(['locale' => $explicitLocale]);
        }

        return $next($request);
    }
}
