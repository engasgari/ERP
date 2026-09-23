<?php

namespace App\Http\Middleware;

use App\Services\BreadcrumbContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleBreadcrumbContext
{
    public function __construct(
        private readonly BreadcrumbContextService $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->rememberFromReferer($request);
        }

        /** @var Response $response */
        $response = $next($request);

        if (! $request->user() || ! $request->isMethod('GET')) {
            return $response;
        }

        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            return $response;
        }

        if ($this->context->shouldRememberRoute($routeName)) {
            $this->context->remember($routeName, $request->fullUrl());
        }

        if ($this->context->isReportRoute($routeName)) {
            $this->context->captureReportEntry($request, $routeName);
        }

        return $response;
    }

    private function rememberFromReferer(Request $request): void
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return;
        }

        if (! $this->context->isSafeUrl($referer)) {
            return;
        }

        $routeName = $this->context->routeNameFromUrl($referer);

        if ($routeName === null || ! $this->context->shouldRememberRoute($routeName)) {
            return;
        }

        $this->context->remember($routeName, $referer);
    }
}
