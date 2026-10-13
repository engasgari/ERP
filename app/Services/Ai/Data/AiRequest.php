<?php

namespace App\Services\Ai\Data;

final class AiRequest
{
    /**
     * @param  array<string, mixed>  $payload  sanitized, allowlisted data (untrusted content stays inside this payload)
     * @param  array<string, mixed>  $outputSchema  JSON-schema-like description sent to providers that support structured output
     */
    public function __construct(
        public readonly string $agent,
        public readonly string $promptVersion,
        public readonly string $instructions,
        public readonly array $payload,
        public readonly array $outputSchema,
        public readonly ?string $model,
        public readonly int $maxOutputTokens,
        public readonly int $timeoutSeconds,
    ) {}
}
