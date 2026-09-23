<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class BreadcrumbContextService
{
    private const SESSION_REFS = 'erp.breadcrumb.refs';

    private const SESSION_REPORT = 'erp.breadcrumb.report';

    public function shouldRememberRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        foreach ($this->rememberPatterns() as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    public function isReportRoute(?string $routeName): bool
    {
        if ($routeName === null || $routeName === '') {
            return false;
        }

        foreach ($this->reportPatterns() as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    public function remember(string $routeName, string $url): void
    {
        if (! $this->isSafeUrl($url)) {
            return;
        }

        $refs = session(self::SESSION_REFS, []);
        $refs[$routeName] = $url;
        session([self::SESSION_REFS => $refs]);
    }

    public function recall(string $routeName, ?string $fallback = null): ?string
    {
        $refs = session(self::SESSION_REFS, []);
        $url = $refs[$routeName] ?? null;

        if (is_string($url) && $this->isSafeUrl($url)) {
            return $url;
        }

        return $fallback;
    }

    public function captureReportEntry(Request $request, string $routeName): void
    {
        $returnTo = $request->query('return_to');
        $from = $request->query('from');

        if (is_string($returnTo) && $returnTo !== '' && $this->isSafeUrl($returnTo)) {
            $this->storeReportReturn($routeName, $returnTo, is_string($from) ? $from : null);

            return;
        }

        if ($this->hasReportReturn($routeName)) {
            return;
        }

        if (is_string($from) && $from !== '' && Route::has($from)) {
            $url = $this->recall($from) ?? route($from);

            if ($this->isSafeUrl($url)) {
                $this->storeReportReturn($routeName, $url, $from);

                return;
            }
        }

        $previous = url()->previous();

        if ($this->isSafeUrl($previous) && $previous !== $request->fullUrl()) {
            $previousRoute = $this->routeNameFromUrl($previous);

            if ($previousRoute !== null
                && ! $this->isReportRoute($previousRoute)
                && ! $this->isExcludedPreviousRoute($previousRoute)) {
                $this->storeReportReturn($routeName, $previous, $previousRoute);

                return;
            }
        }

        $this->storeReportReturn($routeName, null, null);
    }

    /**
     * @return array{url: ?string, route: ?string, label: ?string}
     */
    public function reportReturn(string $routeName): array
    {
        $stored = session(self::SESSION_REPORT, []);
        $entry = $stored[$routeName] ?? [];

        $url = $entry['url'] ?? null;
        $fromRoute = $entry['from_route'] ?? null;

        if (is_string($url) && $url !== '' && $this->isSafeUrl($url)) {
            return [
                'url' => $url,
                'route' => is_string($fromRoute) ? $fromRoute : null,
                'label' => $this->labelForReturnRoute($fromRoute, $url),
            ];
        }

        return [
            'url' => null,
            'route' => null,
            'label' => null,
        ];
    }

    public function isSafeUrl(string $url): bool
    {
        if ($url === '' || str_starts_with($url, '//')) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            return false;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        if ($appHost && strcasecmp((string) $parts['host'], (string) $appHost) !== 0) {
            return false;
        }

        $path = $parts['path'] ?? '/';

        foreach (['/login', '/logout', '/register', '/forgot-password'] as $blocked) {
            if (str_starts_with($path, $blocked)) {
                return false;
            }
        }

        return true;
    }

    public function routeNameFromUrl(string $url): ?string
    {
        if (! $this->isSafeUrl($url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '/';

        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));

            return $route->getName();
        } catch (\Throwable) {
            return null;
        }
    }

    private function storeReportReturn(string $routeName, ?string $url, ?string $fromRoute): void
    {
        $stored = session(self::SESSION_REPORT, []);
        $stored[$routeName] = [
            'url' => $url,
            'from_route' => $fromRoute,
        ];
        session([self::SESSION_REPORT => $stored]);
    }

    private function hasReportReturn(string $routeName): bool
    {
        $stored = session(self::SESSION_REPORT, []);

        return ! empty($stored[$routeName]['url']);
    }

    private function labelForReturnRoute(?string $fromRoute, string $url): ?string
    {
        $routeLabels = config('breadcrumbs.route_labels', []);

        if (is_string($fromRoute) && $fromRoute !== '') {
            $label = $routeLabels[$fromRoute] ?? null;

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        $resolvedRoute = $this->routeNameFromUrl($url);

        if ($resolvedRoute !== null) {
            $label = $routeLabels[$resolvedRoute] ?? null;

            if (is_string($label) && $label !== '') {
                return $label;
            }
        }

        return null;
    }

    private function isExcludedPreviousRoute(string $routeName): bool
    {
        foreach ($this->excludedPreviousPatterns() as $pattern) {
            if (Str::is($pattern, $routeName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function rememberPatterns(): array
    {
        return config('breadcrumbs.remember_patterns', ['*.index']);
    }

    /**
     * @return list<string>
     */
    private function reportPatterns(): array
    {
        return config('breadcrumbs.report_patterns', []);
    }

    /**
     * @return list<string>
     */
    private function excludedPreviousPatterns(): array
    {
        return config('breadcrumbs.excluded_previous_patterns', []);
    }
}
