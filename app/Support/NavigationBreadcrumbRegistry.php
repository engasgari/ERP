<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class NavigationBreadcrumbRegistry
{
    /** @var array<string, string>|null */
    private static ?array $routeLabels = null;

    /** @var array<string, array{label: string, route: string}>|null */
    private static ?array $routeModules = null;

    /** @var array<string, string>|null */
    private static ?array $routeIndexMap = null;

    public function labelFor(string $routeName): ?string
    {
        $routeLabels = config('breadcrumbs.route_labels', []);
        $override = $routeLabels[$routeName] ?? null;

        if (is_string($override) && $override !== '') {
            return $override;
        }

        return $this->routeLabels()[$routeName] ?? $this->deriveLabel($routeName);
    }

    /**
     * @return array{label: string, route: string}|null
     */
    public function moduleForRoute(string $routeName): ?array
    {
        return $this->routeModules()[$routeName] ?? null;
    }

    public function indexRouteFor(string $routeName): ?string
    {
        if (str_ends_with($routeName, '.index')) {
            return $routeName;
        }

        return $this->routeIndexMap()[$routeName] ?? $this->deriveIndexRoute($routeName);
    }

    /**
     * @return array<string, string>
     */
    private function routeLabels(): array
    {
        if (self::$routeLabels !== null) {
            return self::$routeLabels;
        }

        $labels = [];

        foreach ([NavigationMenu::class, CrmNavigationMenu::class] as $menuClass) {
            foreach ($menuClass::definitionForBreadcrumbs() as $item) {
                if (! empty($item['route']) && ! empty($item['label'])) {
                    $labels[$item['route']] = $item['label'];
                }

                foreach ($item['children'] ?? [] as $child) {
                    if (($child['type'] ?? null) === 'divider') {
                        continue;
                    }

                    if (! empty($child['route']) && ! empty($child['label'])) {
                        $labels[$child['route']] = $child['label'];
                    }
                }
            }
        }

        self::$routeLabels = $labels;

        return self::$routeLabels;
    }

    /**
     * @return array<string, array{label: string, route: string}>
     */
    private function routeModules(): array
    {
        if (self::$routeModules !== null) {
            return self::$routeModules;
        }

        $modules = [];

        foreach ([NavigationMenu::class, CrmNavigationMenu::class] as $menuClass) {
            foreach ($menuClass::definitionForBreadcrumbs() as $item) {
                if (empty($item['route']) || empty($item['label'])) {
                    continue;
                }

                $module = [
                    'label' => $item['label'],
                    'route' => $item['route'],
                ];

                $patterns = $item['active'] ?? [$item['route']];

                foreach ($this->expandActivePatterns($patterns) as $routeName) {
                    $modules[$routeName] = $module;
                }

                foreach ($item['children'] ?? [] as $child) {
                    if (($child['type'] ?? null) === 'divider' || empty($child['route'])) {
                        continue;
                    }

                    $modules[$child['route']] = $module;
                }
            }
        }

        self::$routeModules = $modules;

        return self::$routeModules;
    }

    /**
     * @return array<string, string>
     */
    private function routeIndexMap(): array
    {
        if (self::$routeIndexMap !== null) {
            return self::$routeIndexMap;
        }

        $map = [];

        foreach (array_keys($this->routeLabels()) as $routeName) {
            if (! str_ends_with($routeName, '.index')) {
                continue;
            }

            $prefix = Str::beforeLast($routeName, '.index');

            foreach (array_keys($this->routeLabels()) as $candidate) {
                if ($candidate === $routeName) {
                    continue;
                }

                if (str_starts_with($candidate, $prefix . '.') || str_starts_with($candidate, $prefix . '-')) {
                    $map[$candidate] = $routeName;
                }
            }
        }

        self::$routeIndexMap = $map;

        return self::$routeIndexMap;
    }

    private function deriveIndexRoute(string $routeName): ?string
    {
        if (! str_contains($routeName, '.')) {
            return null;
        }

        $segments = explode('.', $routeName);
        array_pop($segments);

        while ($segments !== []) {
            $candidate = implode('.', $segments) . '.index';

            if (Route::has($candidate)) {
                return $candidate;
            }

            array_pop($segments);
        }

        $resource = Str::beforeLast($routeName, '.');

        if (Route::has($resource . '.index')) {
            return $resource . '.index';
        }

        return null;
    }

    private function deriveLabel(string $routeName): ?string
    {
        $action = Str::afterLast($routeName, '.');
        $actionLabels = config('breadcrumbs.action_labels', []);

        if (isset($actionLabels[$action]) && is_string($actionLabels[$action])) {
            return $actionLabels[$action];
        }

        $indexRoute = $this->indexRouteFor($routeName);

        if ($indexRoute !== null) {
            $indexLabel = $this->routeLabels()[$indexRoute] ?? null;

            if ($indexLabel !== null && isset($actionLabels[$action]) && $actionLabels[$action] !== null) {
                return $actionLabels[$action];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     * @return list<string>
     */
    private function expandActivePatterns(array $patterns): array
    {
        $routes = [];

        foreach ($patterns as $pattern) {
            if (! str_contains($pattern, '*')) {
                $routes[] = $pattern;

                continue;
            }

            $prefix = str_replace('.*', '', $pattern);

            foreach (array_keys($this->routeLabels()) as $routeName) {
                if (str_starts_with($routeName, $prefix)) {
                    $routes[] = $routeName;
                }
            }
        }

        return array_values(array_unique($routes));
    }
}
