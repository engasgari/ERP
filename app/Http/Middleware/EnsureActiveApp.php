<?php

namespace App\Http\Middleware;

use App\Services\AppAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveApp
{
    public function __construct(private readonly AppAccessService $access) {}

    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $app): Response
    {
        if (! in_array($app, [AppAccessService::APP_ERP, AppAccessService::APP_CRM], true)) {
            abort(500, 'Invalid application context.');
        }

        $user = $request->user();
        abort_unless($user, 403);

        if (! $this->access->canAccessApp($user, $app)) {
            abort(403, 'شما به این برنامه دسترسی ندارید.');
        }

        if (session('active_app') !== $app) {
            // Clear stale app context (e.g. CRM session opening ERP) and continue.
            session(['active_app' => $app]);
            $request->session()->forget('url.intended');
        }

        return $next($request);
    }
}
