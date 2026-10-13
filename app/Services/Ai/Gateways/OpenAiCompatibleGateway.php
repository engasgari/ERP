<?php

namespace App\Services\Ai\Gateways;

use App\Services\Ai\Contracts\AiGateway;
use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Data\AiResponse;
use App\Services\Ai\Exceptions\AiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Chat Completions adapter for OpenAI or any OpenAI-compatible endpoint (base URL is configurable).
 *
 * The API key is read from config only; it is never logged, stored on ai_runs or put into exception messages.
 */
class OpenAiCompatibleGateway implements AiGateway
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly ?string $defaultModel,
        private readonly string $providerName = 'openai',
        private readonly string $responseFormat = 'json_schema',
        private readonly string $tokenParameter = 'max_completion_tokens',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            apiKey: config('crm_ai.openai.api_key'),
            baseUrl: (string) config('crm_ai.openai.base_url', 'https://api.openai.com/v1'),
            defaultModel: config('crm_ai.model'),
            providerName: (string) config('crm_ai.provider', 'openai'),
            responseFormat: (string) config('crm_ai.openai.response_format', 'json_schema'),
            tokenParameter: (string) config('crm_ai.openai.token_parameter', 'max_completion_tokens'),
        );
    }

    public function generate(AiRequest $request): AiResponse
    {
        if (! $this->isConfigured()) {
            throw AiException::make(AiException::NOT_CONFIGURED, 'کلید API یا مدل هوش مصنوعی تنظیم نشده است.');
        }

        $model = $request->model ?: $this->defaultModel;

        try {
            $response = Http::withToken((string) $this->apiKey)
                ->acceptJson()
                ->timeout(max(5, $request->timeoutSeconds))
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', $this->body($request, (string) $model));
        } catch (ConnectionException $e) {
            throw AiException::make(
                AiException::TIMEOUT,
                'اتصال به سرویس هوش مصنوعی برقرار نشد یا زمان آن تمام شد: '.mb_substr($e->getMessage(), 0, 200)
            );
        }

        if (! $response->successful()) {
            throw $this->httpError($response);
        }

        $choice = $response->json('choices.0') ?? [];

        if (! empty($choice['message']['refusal'])) {
            throw AiException::make(AiException::INVALID_OUTPUT, 'مدل از پاسخ‌دادن خودداری کرد.');
        }

        if (($choice['finish_reason'] ?? null) === 'length') {
            throw AiException::make(AiException::INVALID_OUTPUT, 'پاسخ مدل به‌دلیل محدودیت طول ناقص ماند.');
        }

        $structured = $this->decode((string) ($choice['message']['content'] ?? ''));

        return new AiResponse(
            structured: $structured,
            model: $response->json('model') ?? $model,
            inputTokens: $this->intOrNull($response->json('usage.prompt_tokens')),
            outputTokens: $this->intOrNull($response->json('usage.completion_tokens')),
            reportedCostUsd: null,
            providerRequestId: $response->json('id') ?? $response->header('x-request-id') ?: null,
        );
    }

    public function providerName(): string
    {
        return $this->providerName;
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey) && filled($this->defaultModel) && filled($this->baseUrl);
    }

    /** @return array<string, mixed> */
    private function body(AiRequest $request, string $model): array
    {
        $system = $request->instructions;

        if ($this->responseFormat === 'json_schema') {
            $format = [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => preg_replace('/[^a-zA-Z0-9_-]/', '_', $request->agent.'_'.$request->promptVersion),
                    'schema' => $request->outputSchema,
                    'strict' => false,
                ],
            ];
        } else {
            // json_object mode needs the schema in the prompt and the word "JSON" in the messages.
            $format = ['type' => 'json_object'];
            $system .= "\n\nRespond with a single JSON object matching this JSON schema:\n"
                .json_encode($request->outputSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => json_encode($request->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ],
            'response_format' => $format,
            $this->tokenParameter => $request->maxOutputTokens,
        ];
    }

    /** @return array<string, mixed> */
    private function decode(string $content): array
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $content) ?? $content;
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            throw AiException::make(AiException::INVALID_OUTPUT, 'خروجی مدل JSON معتبر نیست.');
        }

        return $decoded;
    }

    private function httpError(Response $response): AiException
    {
        $status = $response->status();

        return match (true) {
            $status === 401, $status === 403 => AiException::make(
                AiException::NOT_CONFIGURED,
                "سرویس هوش مصنوعی درخواست را نپذیرفت (HTTP {$status}). کلید API یا دسترسی منطقه‌ای را بررسی کنید."
            ),
            $status === 429 => AiException::make(AiException::PROVIDER_RATE_LIMITED, 'محدودیت نرخ یا اعتبار سرویس هوش مصنوعی.'),
            $status === 408, $status >= 500 => AiException::make(AiException::PROVIDER_ERROR, "خطای موقت سرویس هوش مصنوعی (HTTP {$status})."),
            default => AiException::make(
                AiException::INVALID_INPUT,
                "درخواست توسط سرویس هوش مصنوعی رد شد (HTTP {$status}): ".mb_substr((string) ($response->json('error.message') ?? ''), 0, 300)
            ),
        };
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
