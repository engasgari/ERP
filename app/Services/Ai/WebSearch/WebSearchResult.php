<?php

namespace App\Services\Ai\WebSearch;

final class WebSearchResult
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $title = null,
        public readonly ?string $snippet = null,
        public readonly string $sourceType = 'web',
    ) {}
}
