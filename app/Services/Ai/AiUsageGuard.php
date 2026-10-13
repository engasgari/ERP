<?php

namespace App\Services\Ai;

use App\Models\Ai\AiRun;
use App\Services\Ai\Exceptions\AiException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Deterministic pre-flight checks: feature flags, rate limits and monthly budget.
 */
class AiUsageGuard
{
    public function isAgentEnabled(string $agent): bool
    {
        return (bool) config('crm_ai.enabled') && (bool) config("crm_ai.agents.{$agent}.enabled");
    }

    /**
     * @throws AiException
     */
    public function assertEnabled(string $agent): void
    {
        if (! $this->isAgentEnabled($agent)) {
            throw AiException::make(AiException::DISABLED, 'قابلیت هوش مصنوعی برای این بخش غیرفعال است.');
        }
    }

    /**
     * Consumes one slot per user and per agent. Call only right before a run is actually queued.
     *
     * @throws AiException
     */
    public function hitRateLimits(string $agent, ?int $userId): void
    {
        $userLimit = (int) config('crm_ai.limits.requests_per_user_per_hour', 10);
        $agentLimit = (int) config('crm_ai.limits.requests_per_agent_per_hour', 100);

        $agentKey = "crm-ai:agent:{$agent}";
        $userKey = $userId ? "crm-ai:user:{$userId}:{$agent}" : null;

        if ($agentLimit > 0 && RateLimiter::tooManyAttempts($agentKey, $agentLimit)) {
            throw AiException::make(AiException::RATE_LIMITED, 'سقف درخواست‌های این بخش در ساعت جاری پر شده است.');
        }

        if ($userKey && $userLimit > 0 && RateLimiter::tooManyAttempts($userKey, $userLimit)) {
            throw AiException::make(AiException::RATE_LIMITED, 'سقف درخواست‌های شما در ساعت جاری پر شده است.');
        }

        RateLimiter::hit($agentKey, 3600);
        if ($userKey) {
            RateLimiter::hit($userKey, 3600);
        }
    }

    /**
     * @throws AiException
     */
    public function assertWithinBudget(?string $model): void
    {
        $budget = (float) config('crm_ai.budget.monthly_usd', 0);

        if ($budget <= 0) {
            return;
        }

        if ($this->pricingFor($model) === null && ! config('crm_ai.budget.allow_unknown_cost')) {
            throw AiException::make(
                AiException::COST_UNKNOWN,
                'سقف بودجه تعریف شده اما قیمت مدل مشخص نیست؛ اجرای جدید متوقف شد.',
            );
        }

        if ($this->monthToDateSpendUsd() >= $budget) {
            throw AiException::make(AiException::BUDGET_EXCEEDED, 'سقف بودجه ماهانه هوش مصنوعی پر شده است.');
        }
    }

    public function monthToDateSpendUsd(): float
    {
        return (float) AiRun::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(COALESCE(reported_cost_usd, estimated_cost_usd)), 0) as total')
            ->value('total');
    }

    /**
     * @return array{input_per_million: float, output_per_million: float}|null
     */
    public function pricingFor(?string $model): ?array
    {
        if (! $model) {
            return null;
        }

        $pricing = config("crm_ai.budget.pricing.{$model}");

        if (! is_array($pricing) || ! isset($pricing['input_per_million'], $pricing['output_per_million'])) {
            return null;
        }

        return [
            'input_per_million' => (float) $pricing['input_per_million'],
            'output_per_million' => (float) $pricing['output_per_million'],
        ];
    }

    /**
     * Returns null when pricing or token counts are unknown; never invents a cost.
     */
    public function estimateCostUsd(?string $model, ?int $inputTokens, ?int $outputTokens): ?float
    {
        $pricing = $this->pricingFor($model);

        if ($pricing === null || $inputTokens === null || $outputTokens === null) {
            return null;
        }

        return round(
            ($inputTokens / 1_000_000) * $pricing['input_per_million']
            + ($outputTokens / 1_000_000) * $pricing['output_per_million'],
            6,
        );
    }
}
