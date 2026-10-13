<?php

namespace App\Services\Ai\Gateways;

use App\Services\Ai\Contracts\AiGateway;
use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Data\AiResponse;
use App\Services\Ai\Exceptions\AiException;
use Closure;

/**
 * Deterministic gateway for tests and offline demos. Never contacts a real provider.
 */
class FakeAiGateway implements AiGateway
{
    /** @var list<AiResponse|AiException|Closure> */
    private array $queue = [];

    /** @var list<AiRequest> */
    public array $requests = [];

    /**
     * @param  AiResponse|AiException|Closure(AiRequest): AiResponse  ...$results
     */
    public function push(AiResponse|AiException|Closure ...$results): self
    {
        foreach ($results as $result) {
            $this->queue[] = $result;
        }

        return $this;
    }

    public function generate(AiRequest $request): AiResponse
    {
        $this->requests[] = $request;

        $next = array_shift($this->queue);

        if ($next === null) {
            throw AiException::make(AiException::PROVIDER_ERROR, 'Fake gateway has no queued response.');
        }

        if ($next instanceof AiException) {
            throw $next;
        }

        if ($next instanceof Closure) {
            return $next($request);
        }

        return $next;
    }

    public function providerName(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function callCount(): int
    {
        return count($this->requests);
    }
}
