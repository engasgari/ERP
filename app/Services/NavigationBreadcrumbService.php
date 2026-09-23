<?php

namespace App\Services;

use App\Support\NavigationBreadcrumbRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class NavigationBreadcrumbService
{
    public function __construct(
        private readonly BreadcrumbContextService $context,
        private readonly NavigationBreadcrumbRegistry $registry,
    ) {}

    /** @var array<string, list<array<string, mixed>>> */
    private array $definitions = [
        'dashboard' => [
            ['label' => 'داشبورد'],
        ],
        'invoices.create' => [
            ['label' => 'داشبورد', 'route' => 'dashboard'],
            ['label' => 'بازرگانی', 'route' => 'invoices.index', 'remember' => true],
            ['label' => 'فاکتورها', 'route' => 'invoices.index', 'remember' => true, 'contextRoute' => 'invoices.index'],
            ['label' => 'ثبت سند بازرگانی'],
        ],
    ];

    public function trail(?string $routeName = null, ?Request $request = null): array
    {
        $routeName ??= Route::currentRouteName() ?? '';
        $request ??= request();

        if ($routeName === '') {
            return [];
        }

        $trail = $this->definitions[$routeName]
            ?? ($this->context->isReportRoute($routeName)
                ? $this->reportTrail($routeName, $request)
                : $this->autoTrail($routeName));

        return $this->applyContext($trail, $routeName, $request);
    }

    public function trailForRequest(Request $request): array
    {
        return $this->trail($request->route()?->getName(), $request);
    }

    public function title(?string $routeName = null, ?Request $request = null): string
    {
        $trail = $this->trail($routeName, $request);

        return (string) ($trail[array_key_last($trail)]['label'] ?? '');
    }

    /**
     * @return array{url: string, label: string}|null
     */
    public function backLink(?Request $request = null): ?array
    {
        $request ??= request();
        $routeName = $request->route()?->getName();

        if ($routeName === null || ! $this->context->isReportRoute($routeName)) {
            return null;
        }

        $return = $this->context->reportReturn($routeName);
        $url = $return['url'] ?? null;

        if (! is_string($url) || $url === '') {
            $defaults = $this->reportDefaultsPrefix($routeName);

            if ($defaults !== null && ! empty($defaults['fallback_parent']['route'])) {
                $parentRoute = $defaults['fallback_parent']['route'];
                $url = $this->context->recall($parentRoute) ?? route($parentRoute);
            }
        }

        if (! is_string($url) || $url === '') {
            return null;
        }

        $label = $return['label'] ?? null;

        if (! is_string($label) || $label === '') {
            $label = 'صفحه قبل';
        }

        return [
            'url' => $url,
            'label' => 'بازگشت به ' . $label,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function autoTrail(string $routeName): array
    {
        if ($routeName === 'dashboard') {
            return [['label' => 'داشبورد']];
        }

        $trail = [$this->dashboardCrumb($routeName)];
        $module = $this->registry->moduleForRoute($routeName);
        $indexRoute = $this->registry->indexRouteFor($routeName);

        if ($module !== null && ($module['route'] ?? '') !== $indexRoute) {
            $trail[] = $this->linkCrumb(
                $module['label'],
                $module['route'],
                remember: true,
                contextRoute: $module['route'],
            );
        }

        if ($indexRoute !== null && $indexRoute !== $routeName) {
            $trail[] = $this->linkCrumb(
                $this->registry->labelFor($indexRoute) ?? 'فهرست',
                $indexRoute,
                remember: true,
                contextRoute: $indexRoute,
            );
        }

        $currentLabel = $this->registry->labelFor($routeName) ?? Str::headline(str_replace('.', ' ', Str::afterLast($routeName, '.')));

        $trail[] = ['label' => $currentLabel];

        return $trail;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reportTrail(string $routeName, Request $request): array
    {
        $trail = [$this->dashboardCrumb($routeName)];
        $defaults = $this->reportDefaultsPrefix($routeName);
        $return = $this->context->reportReturn($routeName);

        if (! empty($return['url'])) {
            $parentLabel = $return['label']
                ?? ($defaults['fallback_parent']['label'] ?? 'صفحه قبل');

            $trail[] = [
                'label' => $parentLabel,
                'url' => $return['url'],
            ];
        } elseif ($defaults !== null) {
            if (! empty($defaults['fallback_parent'])) {
                $parent = $defaults['fallback_parent'];
                $trail[] = $this->linkCrumb(
                    $parent['label'],
                    $parent['route'],
                    remember: true,
                    contextRoute: $parent['route'],
                );
            } elseif (! empty($defaults['hub'])) {
                $hub = $defaults['hub'];
                $trail[] = $this->linkCrumb(
                    $hub['label'],
                    $hub['route'],
                    remember: true,
                    contextRoute: $hub['route'],
                );
            }
        }

        $trail[] = ['label' => $this->reportTitle($routeName, $request)];

        return $trail;
    }

    /**
     * @param  list<array<string, mixed>>  $trail
     * @return list<array<string, mixed>>
     */
    private function applyContext(array $trail, string $routeName, Request $request): array
    {
        foreach ($trail as $index => $crumb) {
            if (! empty($crumb['url'])) {
                continue;
            }

            $contextRoute = $crumb['contextRoute'] ?? null;

            if (is_string($contextRoute) && ($crumb['remember'] ?? false)) {
                $remembered = $this->context->recall($contextRoute);

                if ($remembered !== null) {
                    $trail[$index]['url'] = $remembered;

                    continue;
                }
            }

            $route = $crumb['route'] ?? null;

            if (! is_string($route) || $route === '' || ! Route::has($route)) {
                continue;
            }

            $params = $crumb['routeParams'] ?? [];

            if (($crumb['remember'] ?? false) || $contextRoute !== null) {
                $remembered = $this->context->recall($contextRoute ?? $route);

                if ($remembered !== null) {
                    $trail[$index]['url'] = $remembered;

                    continue;
                }
            }

            $trail[$index]['url'] = route($route, $params);
        }

        if ($this->context->isReportRoute($routeName)) {
            $return = $this->context->reportReturn($routeName);

            if (! empty($return['url'])) {
                foreach ($trail as $index => $crumb) {
                    if ($index === array_key_last($trail)) {
                        continue;
                    }

                    $isParent = ! empty($crumb['contextRoute'])
                        || ! empty($crumb['remember'])
                        || ($defaults = $this->reportDefaultsPrefix($routeName)) !== null && (
                            ($crumb['route'] ?? null) === ($defaults['fallback_parent']['route'] ?? null)
                            || ($crumb['route'] ?? null) === ($defaults['hub']['route'] ?? null)
                        );

                    if ($isParent && empty($crumb['url'])) {
                        $trail[$index]['url'] = $return['url'];
                    }
                }
            }
        }

        return $trail;
    }

    /**
     * @return array<string, mixed>
     */
    private function dashboardCrumb(string $routeName): array
    {
        if (str_starts_with($routeName, 'crm.')) {
            return [
                'label' => 'داشبورد',
                'route' => 'crm.dashboard',
            ];
        }

        return [
            'label' => 'داشبورد',
            'route' => 'dashboard',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function linkCrumb(
        string $label,
        string $route,
        bool $remember = false,
        ?string $contextRoute = null,
    ): array {
        return [
            'label' => $label,
            'route' => $route,
            'remember' => $remember,
            'contextRoute' => $contextRoute ?? $route,
        ];
    }

    private function reportTitle(string $routeName, Request $request): string
    {
        $reportTitles = config('breadcrumbs.report_titles', []);
        $configured = $reportTitles[$routeName] ?? null;

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        if (is_array($configured)) {
            $reportKey = $request->route('report');

            if (is_string($reportKey) && isset($configured[$reportKey])) {
                return $configured[$reportKey];
            }
        }

        return $this->registry->labelFor($routeName) ?? 'گزارش';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reportDefaultsPrefix(string $routeName): ?array
    {
        foreach (config('breadcrumbs.report_defaults', []) as $prefix => $defaults) {
            if (str_starts_with($routeName, $prefix)) {
                return $defaults;
            }
        }

        if (str_contains($routeName, '-report') || str_ends_with($routeName, '.statement')) {
            return [
                'hub' => null,
                'module' => null,
                'fallback_parent' => [
                    'route' => $this->registry->indexRouteFor($routeName) ?? 'dashboard',
                    'label' => $this->registry->labelFor($this->registry->indexRouteFor($routeName) ?? '') ?? 'فهرست',
                ],
            ];
        }

        return null;
    }
}
