<?php

namespace App\Services\Ai;

use App\Models\Ai\AiRun;
use App\Services\Ai\Contracts\AiGateway;
use App\Services\Ai\Data\AiRequest;
use App\Services\Ai\Data\AiResponse;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Prompts\AiPromptDefinition;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Executes one AI call for an already-created AiRun: sanitize -> call provider (bounded retries)
 * -> validate structured output -> record usage. It never performs CRM writes; callers decide
 * what to persist from the validated output.
 */
class AiRunner
{
    public function __construct(
        private readonly AiGateway $gateway,
        private readonly AiPayloadSanitizer $sanitizer,
        private readonly AiOutputValidator $validator,
        private readonly AiUsageGuard $guard,
    ) {}

    /**
     * Creates the run record in "pending" state. Duplicate active runs for the same idempotency key are rejected.
     *
     * @throws AiException
     */
    public function createRun(
        AiPromptDefinition $prompt,
        ?int $userId,
        ?string $subjectType,
        ?int $subjectId,
        ?string $idempotencyKey = null,
        ?string $inputType = null,
    ): AiRun {
        if ($idempotencyKey !== null) {
            $active = AiRun::query()
                ->where('idempotency_key', $idempotencyKey)
                ->whereIn('status', AiRun::ACTIVE_STATUSES)
                ->exists();

            if ($active) {
                throw AiException::make(AiException::DUPLICATE, 'درخواست مشابهی در حال اجراست.');
            }
        }

        return AiRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'agent' => $prompt->agent(),
            'status' => AiRun::STATUS_PENDING,
            'provider' => $this->gateway->providerName(),
            'model' => config('crm_ai.model'),
            'prompt_version' => $prompt->version(),
            'correlation_id' => (string) Str::uuid(),
            'idempotency_key' => $idempotencyKey,
            'user_id' => $userId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'input_type' => $inputType,
            'cost_status' => AiRun::COST_UNKNOWN,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload  raw payload assembled by a Laravel service (allowlist applied here)
     * @param  list<string>  $exemptPaths
     * @return array<string, mixed> validated structured output
     *
     * @throws AiException
     */
    public function execute(AiRun $run, AiPromptDefinition $prompt, array $payload, array $exemptPaths = []): array
    {
        if ($run->status !== AiRun::STATUS_PENDING) {
            throw AiException::make(AiException::DUPLICATE, 'این اجرا قبلاً پردازش شده است.');
        }

        $startedAt = now();
        $run->forceFill(['status' => AiRun::STATUS_RUNNING, 'started_at' => $startedAt])->save();

        try {
            $this->guard->assertEnabled($prompt->agent());

            if (! $this->gateway->isConfigured()) {
                throw AiException::make(AiException::NOT_CONFIGURED, $this->gateway->providerName() === 'none'
                    ? 'هیچ ارائه‌دهنده هوش مصنوعی پیکربندی نشده است (CRM_AI_DRIVER).'
                    : 'کلید API یا مدل هوش مصنوعی تنظیم نشده است (CRM_AI_API_KEY / CRM_AI_MODEL در فایل .env).');
            }

            $this->guard->assertWithinBudget(config('crm_ai.model'));

            $sanitized = $this->sanitizer->sanitize($prompt->agent(), $payload, $exemptPaths);
            $encoded = json_encode($sanitized, JSON_UNESCAPED_UNICODE);

            if (mb_strlen((string) $encoded) > (int) config('crm_ai.limits.max_input_chars', 24000)) {
                throw AiException::make(AiException::INPUT_TOO_LARGE, 'حجم ورودی از سقف مجاز بیشتر است.');
            }

            $this->storePayload($run, 'input_payload', (string) $encoded);

            $request = new AiRequest(
                agent: $prompt->agent(),
                promptVersion: $prompt->version(),
                instructions: $prompt->instructions(),
                payload: $sanitized,
                outputSchema: $prompt->outputSchema(),
                model: config('crm_ai.model'),
                maxOutputTokens: (int) config('crm_ai.limits.max_output_tokens', 1500),
                timeoutSeconds: (int) config('crm_ai.timeout_seconds', 60),
            );

            $response = $this->callWithRetry($run, $request);

            $this->recordUsage($run, $response->model, $response->inputTokens, $response->outputTokens, $response->reportedCostUsd);
            $this->storePayload($run, 'output_payload', (string) json_encode($response->structured, JSON_UNESCAPED_UNICODE));

            $validated = $this->validator->validate($prompt, $response->structured);

            $this->finish($run, AiRun::STATUS_COMPLETED, $startedAt);

            return $validated;
        } catch (AiException $e) {
            $this->fail($run, $e->errorCode, $e->getMessage(), $startedAt, $e->details);

            throw $e;
        } catch (Throwable $e) {
            $this->fail($run, AiException::PROVIDER_ERROR, 'خطای غیرمنتظره در اجرای هوش مصنوعی.', $startedAt);
            Log::warning('crm_ai.unexpected_failure', ['run' => $run->uuid, 'exception' => $e::class]);

            throw new AiException(AiException::PROVIDER_ERROR, 'خطای غیرمنتظره در اجرای هوش مصنوعی.', $e);
        }
    }

    private function callWithRetry(AiRun $run, AiRequest $request): AiResponse
    {
        $maxAttempts = max(1, (int) config('crm_ai.retry.max_attempts', 2));
        $backoffMs = max(0, (int) config('crm_ai.retry.backoff_ms', 1000));
        $attempt = 0;

        while (true) {
            $attempt++;
            $run->forceFill(['attempts' => $attempt])->save();

            try {
                return $this->gateway->generate($request);
            } catch (AiException $e) {
                if (! $e->isRetryable() || $attempt >= $maxAttempts) {
                    throw $e;
                }

                if ($backoffMs > 0) {
                    usleep($backoffMs * 1000 * (2 ** ($attempt - 1)));
                }
            }
        }
    }

    private function recordUsage(AiRun $run, ?string $model, ?int $inputTokens, ?int $outputTokens, ?float $reportedCost): void
    {
        $model ??= $run->model;
        $estimated = $this->guard->estimateCostUsd($model, $inputTokens, $outputTokens);

        $run->forceFill([
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost_usd' => $estimated,
            'reported_cost_usd' => $reportedCost,
            'cost_status' => $reportedCost !== null
                ? AiRun::COST_REPORTED
                : ($estimated !== null ? AiRun::COST_KNOWN_ESTIMATED : AiRun::COST_UNKNOWN),
        ])->save();
    }

    private function storePayload(AiRun $run, string $column, string $json): void
    {
        if (! config('crm_ai.log.store_payloads', true)) {
            return;
        }

        $max = (int) config('crm_ai.log.max_logged_chars', 20000);
        $masked = $this->sanitizer->maskText($json);

        $run->forceFill([$column => mb_substr($masked, 0, $max)])->save();
    }

    private function finish(AiRun $run, string $status, Carbon $startedAt): void
    {
        $finishedAt = now();

        $run->forceFill([
            'status' => $status,
            'finished_at' => $finishedAt,
            'duration_ms' => (int) abs($finishedAt->diffInMilliseconds($startedAt)),
        ])->save();
    }

    /**
     * @param  list<string>  $details
     */
    private function fail(AiRun $run, string $code, string $message, Carbon $startedAt, array $details = []): void
    {
        $text = $message.($details !== [] ? ' | '.implode(' ; ', $details) : '');

        $run->forceFill([
            'error_code' => $code,
            'error_message' => mb_substr($this->sanitizer->maskText($text), 0, 500),
        ])->save();

        $this->finish($run, $code === AiException::DISABLED ? AiRun::STATUS_CANCELLED : AiRun::STATUS_FAILED, $startedAt);
    }
}
