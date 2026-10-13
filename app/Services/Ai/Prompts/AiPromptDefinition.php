<?php

namespace App\Services\Ai\Prompts;

/**
 * A versioned prompt + output contract for one agent. Changing instructions or schema means a new version.
 */
interface AiPromptDefinition
{
    public function agent(): string;

    public function version(): string;

    public function instructions(): string;

    /**
     * JSON-schema-like description of the expected output (sent to the provider when supported).
     *
     * @return array<string, mixed>
     */
    public function outputSchema(): array;

    /**
     * Laravel validation rules applied to the decoded output. This is the authoritative contract.
     *
     * @return array<string, mixed>
     */
    public function validationRules(): array;
}
