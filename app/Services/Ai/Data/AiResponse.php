<?php

namespace App\Services\Ai\Data;

final class AiResponse
{
    /**
     * @param  array<string, mixed>  $structured  decoded output; validated by AiOutputValidator before use
     */
    public function __construct(
        public readonly array $structured,
        public readonly ?string $model = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?float $reportedCostUsd = null,
        public readonly ?string $providerRequestId = null,
    ) {}
}
