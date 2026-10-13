<?php

namespace App\Services\Ai\Gateways;

use App\Services\Ai\Contracts\AiGateway;
use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Data\AiResponse;
use App\Services\Ai\Exceptions\AiException;

/**
 * Default driver until a real provider adapter is installed: every call fails as "not configured".
 */
class NullAiGateway implements AiGateway
{
    public function generate(AiRequest $request): AiResponse
    {
        throw AiException::make(AiException::NOT_CONFIGURED, 'هیچ ارائه‌دهنده هوش مصنوعی پیکربندی نشده است.');
    }

    public function providerName(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }
}
