<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $keys = collect($permissions)
            ->flatMap(fn (string $permission) => preg_split('/[|,]/', $permission) ?: [])
            ->map(fn (string $permission) => trim($permission))
            ->filter()
            ->values();

        $user = $request->user();
        $allowed = $user && $keys->contains(
            fn (string $permission) => $user->hasPermission($permission)
        );

        if (! $allowed) {
            return response()->view('errors.no-access', status: 403);
        }

        return $next($request);
    }
}
