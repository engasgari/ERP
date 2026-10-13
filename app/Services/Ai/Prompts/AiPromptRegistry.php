<?php

namespace App\Services\Ai\Prompts;

use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Prompts\CustomerIntelligence\CustomerIntelligencePromptV1;
use App\Services\Ai\Prompts\SalesTaskPlanner\SalesTaskPlannerPromptV1;

class AiPromptRegistry
{
    /** @var array<string, array<string, class-string<AiPromptDefinition>>> */
    private const DEFINITIONS = [
        'customer_intelligence' => [
            'v1' => CustomerIntelligencePromptV1::class,
        ],
        'sales_task_planner' => [
            'v1' => SalesTaskPlannerPromptV1::class,
        ],
    ];

    public function resolve(string $agent, ?string $version = null): AiPromptDefinition
    {
        $version ??= (string) config("crm_ai.agents.{$agent}.prompt_version", 'v1');
        $class = self::DEFINITIONS[$agent][$version] ?? null;

        if ($class === null) {
            throw AiException::make(AiException::NOT_CONFIGURED, "Prompt {$agent}@{$version} is not registered.");
        }

        return app($class);
    }

    /**
     * @return list<string>
     */
    public function versions(string $agent): array
    {
        return array_keys(self::DEFINITIONS[$agent] ?? []);
    }
}
