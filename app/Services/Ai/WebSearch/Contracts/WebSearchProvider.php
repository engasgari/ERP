<?php

namespace App\Services\Ai\WebSearch\Contracts;

use App\Services\Ai\WebSearch\WebSearchResult;

interface WebSearchProvider
{
    /**
     * @return list<WebSearchResult>
     */
    public function search(string $query, int $limit): array;

    public function isConfigured(): bool;

    public function name(): string;
}
