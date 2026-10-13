<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Prompts\AiPromptDefinition;
use Illuminate\Support\Facades\Validator;

class AiOutputValidator
{
    /**
     * @param  array<string, mixed>  $output
     * @return array<string, mixed> only the keys covered by the contract
     *
     * @throws AiException when the output violates the contract
     */
    public function validate(AiPromptDefinition $prompt, array $output): array
    {
        $validator = Validator::make($output, $prompt->validationRules());

        if ($validator->fails()) {
            throw AiException::make(
                AiException::INVALID_OUTPUT,
                'خروجی هوش مصنوعی با قرارداد خروجی مطابقت ندارد.',
                array_slice($validator->errors()->all(), 0, 20),
            );
        }

        return $validator->validated();
    }
}
