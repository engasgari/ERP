<?php

namespace App\Support;

use App\Services\NavigationBreadcrumbService;

/**
 * Fluent builder for page-specific breadcrumb overrides.
 */
class Breadcrumb
{
    /** @var list<array<string, mixed>> */
    private array $items = [];

    public static function make(): self
    {
        return new self;
    }

    public function add(string $label, ?string $url = null, ?string $route = null, array $routeParams = []): self
    {
        $item = ['label' => $label];

        if ($url !== null) {
            $item['url'] = $url;
        }

        if ($route !== null) {
            $item['route'] = $route;
            $item['routeParams'] = $routeParams;
        }

        $this->items[] = $item;

        return $this;
    }

    public function current(string $label): self
    {
        $this->items[] = ['label' => $label];

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->items;
    }

    public function title(): string
    {
        if ($this->items === []) {
            return '';
        }

        return (string) ($this->items[array_key_last($this->items)]['label'] ?? '');
    }

    public static function forCurrentRequest(): array
    {
        return app(NavigationBreadcrumbService::class)->trailForRequest(request());
    }
}
