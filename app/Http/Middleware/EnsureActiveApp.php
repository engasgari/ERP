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
        if (! in_array($app, ['erp', 'crm'], true)) {
            abort(500, 'Invalid application context.');
        }

        $user = $request->user();
        abort_unless($user, 403);

        if (session('active_app') !== $app) {
            abort(403, 'دسترسی به این برنامه مجاز نیست. از درگاه ورود همان برنامه وارد شوید.');
        }

        if (! $this->access->canAccessApp($user, $app)) {
            abort(403, 'شما به این برنامه دسترسی ندارید.');
        }

        return $next($request);
    }
}
