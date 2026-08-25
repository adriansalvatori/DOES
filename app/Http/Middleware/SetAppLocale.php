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
        $locale = $request->query('lang')
            ?? session('locale')
            ?? $request->cookie('app_locale')
            ?? config('app.locale', 'es');

        if (! in_array($locale, ['es', 'en'])) {
            $locale = 'es';
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);
        session(['locale' => $locale]);

        return $next($request);
    }
}
