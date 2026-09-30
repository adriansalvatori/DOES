<?php

namespace App\Http\Middleware;

use App\Services\DemoEnvironmentService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureDemoDatabase
{
    public function __construct(
        protected DemoEnvironmentService $demoService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hasSession = $request->hasSession();
        $sessionDemo = $hasSession ? $request->session()->get(DemoEnvironmentService::DEMO_SESSION_KEY, false) : null;
        $cookieDemo = $request->cookie('kudos_demo_mode');

        // Do not let a stale guest cookie hijack the login page or lock regular login into demo DB
        $isLoginPage = $request->is('login');
        $isDemoRoute = $request->is('demo-login*');

        $isDemo = $isDemoRoute
            || ($hasSession && $sessionDemo === true)
            || (! $isLoginPage && $cookieDemo === '1');

        if ($isDemo) {
            $this->demoService->enableDemo();
            Auth::forgetGuards();
        } elseif ($isLoginPage && $sessionDemo !== true) {
            $this->demoService->disableDemo();
        }

        return $next($request);
    }
}
