<?php

namespace App\Services\Ai\WebSearch;

use App\Services\Ai\WebSearch\Contracts\WebSearchProvider;

/**
 * Default until a search provider is chosen and server access to it is verified. Returns no results.
 */
class NullWebSearchProvider implements WebSearchProvider
{
    public function search(string $query, int $limit): array
    {
        return [];
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'none';
    }
}
