<?php

namespace App\Services\Ai\Contracts;

use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Data\AiResponse;
use App\Services\Ai\Exceptions\AiException;

/**
 * Provider boundary. Implementations translate an AiRequest into one model call and return
 * the decoded structured output. They never touch the database and never receive credentials
 * from callers; credentials come from the runtime environment.
 */
interface AiGateway
{
    /**
     * @throws AiException
     */
    public function generate(AiRequest $request): AiResponse;

    public function providerName(): string;

    public function isConfigured(): bool;
}
