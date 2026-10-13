<?php

/*
| CRM AI configuration (Phase 0 infrastructure).
|
| Named crm_ai (not ai) so it never collides with config/ai.php published by laravel/ai.
| Secrets (API keys) are read from the environment only and never logged or rendered.
*/

return [

    'enabled' => (bool) env('CRM_AI_ENABLED', false),

    /*
    | Gateway driver:
    |  - "null"   not configured, every call fails safely
    |  - "openai" Chat Completions over HTTP (OpenAI or any OpenAI-compatible base URL)
    |  - "fake"   tests only
    | A "laravel_ai" driver can replace "openai" once laravel/ai is installed (requires laravel/framework >= 12.62).
    */
    'driver' => env('CRM_AI_DRIVER', 'null'),

    'provider' => env('CRM_AI_PROVIDER', 'openai'),

    'model' => env('CRM_AI_MODEL'),

    'openai' => [
        'api_key' => env('CRM_AI_API_KEY', env('OPENAI_API_KEY')),
        'base_url' => env('CRM_AI_BASE_URL', 'https://api.openai.com/v1'),
        // "json_schema" (OpenAI structured outputs) or "json_object" for compatible endpoints without schema support.
        'response_format' => env('CRM_AI_RESPONSE_FORMAT', 'json_schema'),
        // OpenAI uses max_completion_tokens; some compatible endpoints only accept max_tokens.
        'token_parameter' => env('CRM_AI_TOKEN_PARAMETER', 'max_completion_tokens'),
    ],

    'timeout_seconds' => (int) env('CRM_AI_TIMEOUT', 60),

    'retry' => [
        'max_attempts' => (int) env('CRM_AI_RETRY_MAX_ATTEMPTS', 2),
        'backoff_ms' => (int) env('CRM_AI_RETRY_BACKOFF_MS', 1000),
    ],

    'limits' => [
        'max_input_chars' => (int) env('CRM_AI_MAX_INPUT_CHARS', 24000),
        'max_output_tokens' => (int) env('CRM_AI_MAX_OUTPUT_TOKENS', 1500),
        'requests_per_user_per_hour' => (int) env('CRM_AI_USER_HOURLY_LIMIT', 10),
        'requests_per_agent_per_hour' => (int) env('CRM_AI_AGENT_HOURLY_LIMIT', 100),
        'lock_seconds' => (int) env('CRM_AI_LOCK_SECONDS', 300),
    ],

    'budget' => [
        // Monthly ceiling in USD; 0 disables the ceiling.
        'monthly_usd' => (float) env('CRM_AI_MONTHLY_BUDGET_USD', 0),
        // When a ceiling is set but model pricing is unknown, block new runs unless explicitly allowed.
        'allow_unknown_cost' => (bool) env('CRM_AI_ALLOW_UNKNOWN_COST', false),
        // USD per 1M tokens, per model. Leave empty when prices are not confirmed; cost is then stored as unknown.
        'pricing' => [
            // Source: developers.openai.com/api/docs/models (checked 2026-10-03).
            'gpt-6-luna' => ['input_per_million' => 0.10, 'output_per_million' => 0.50],
            'gpt-6.1-sol' => ['input_per_million' => 2.00, 'output_per_million' => 10.00],
        ],
    ],

    'queue' => env('CRM_AI_QUEUE', 'default'),

    'agents' => [
        'customer_intelligence' => [
            'enabled' => (bool) env('CRM_AI_CUSTOMER_INTELLIGENCE_ENABLED', false),
            'prompt_version' => env('CRM_AI_CUSTOMER_INTELLIGENCE_PROMPT', 'v1'),
            'freshness' => [
                'fresh_days' => (int) env('CRM_AI_CI_FRESH_DAYS', 30),
                'review_days' => (int) env('CRM_AI_CI_REVIEW_DAYS', 90),
            ],
            'max_sources' => (int) env('CRM_AI_CI_MAX_SOURCES', 5),
            'max_source_chars' => (int) env('CRM_AI_CI_MAX_SOURCE_CHARS', 4000),
            // Short public description of our own products; used only for "relevance". Empty = not requested.
            'own_company_profile' => env('CRM_AI_OWN_COMPANY_PROFILE'),
            // Deterministic CRM relationship rule (computed by Laravel, not by AI).
            'dormant_after_days' => (int) env('CRM_AI_CI_DORMANT_DAYS', 90),
        ],
        'sales_task_planner' => [
            'enabled' => (bool) env('CRM_AI_SALES_TASK_PLANNER_ENABLED', false),
            'prompt_version' => env('CRM_AI_SALES_TASK_PLANNER_PROMPT', 'v1'),
            'max_candidates' => (int) env('CRM_AI_STP_MAX_CANDIDATES', 40),
            'max_suggestions' => (int) env('CRM_AI_STP_MAX_SUGGESTIONS', 20),
            'opportunity_idle_days' => (int) env('CRM_AI_STP_OPP_IDLE_DAYS', 3),
            'proforma_idle_days' => (int) env('CRM_AI_STP_PROFORMA_IDLE_DAYS', 3),
            'customer_idle_days' => (int) env('CRM_AI_STP_CUSTOMER_IDLE_DAYS', 30),
            'near_close_days' => (int) env('CRM_AI_STP_NEAR_CLOSE_DAYS', 14),
            'high_amount_threshold' => (float) env('CRM_AI_STP_HIGH_AMOUNT', 500000000),
        ],
    ],

    'web_search' => [
        // "null" = not configured (honest empty result). Real providers are added after server access is verified.
        'driver' => env('CRM_AI_SEARCH_DRIVER', 'null'),
        'fetch_timeout_seconds' => (int) env('CRM_AI_FETCH_TIMEOUT', 10),
        'max_fetch_bytes' => (int) env('CRM_AI_FETCH_MAX_BYTES', 1_000_000),
    ],

    /*
    | Fields that may leave the system for each agent. Anything not listed is never sent.
    */
    'payload_allowlist' => [
        'customer_intelligence' => [
            'identity.name',
            'identity.kind',
            'identity.registration_number',
            'identity.national_id',
            'identity.city',
            'identity.website',
            'identity.industry',
            'crm_facts',
            'sources',
            'own_company',
        ],
        'sales_task_planner' => [
            'as_of',
            'timezone',
            'business_hours',
            'candidates',
        ],
    ],

    'log' => [
        // Store sanitized input/output on ai_runs for audit. Raw secrets are never stored.
        'store_payloads' => (bool) env('CRM_AI_LOG_PAYLOADS', true),
        'max_logged_chars' => (int) env('CRM_AI_LOG_MAX_CHARS', 20000),
    ],
];
