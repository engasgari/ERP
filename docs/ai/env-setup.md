# CRM AI — environment variables

Put these in `.env` (never commit real values). All are optional; defaults keep AI fully disabled.

```dotenv
# Master switch and per-agent switch
CRM_AI_ENABLED=false
CRM_AI_CUSTOMER_INTELLIGENCE_ENABLED=false
CRM_AI_SALES_TASK_PLANNER_ENABLED=false

# Provider boundary: null (default, not configured) | openai (Chat Completions over HTTP) | fake (tests only)
CRM_AI_DRIVER=null
CRM_AI_PROVIDER=openai
CRM_AI_MODEL=gpt-6-luna

# openai driver (OpenAI or any OpenAI-compatible endpoint)
CRM_AI_API_KEY=            # falls back to OPENAI_API_KEY
CRM_AI_BASE_URL=https://api.openai.com/v1
CRM_AI_RESPONSE_FORMAT=json_schema   # json_object for compatible endpoints without structured outputs
CRM_AI_TOKEN_PARAMETER=max_completion_tokens   # max_tokens for some compatible endpoints

# Calls
CRM_AI_TIMEOUT=60
CRM_AI_RETRY_MAX_ATTEMPTS=2
CRM_AI_RETRY_BACKOFF_MS=1000
CRM_AI_MAX_INPUT_CHARS=24000
CRM_AI_MAX_OUTPUT_TOKENS=1500

# Limits
CRM_AI_USER_HOURLY_LIMIT=10
CRM_AI_AGENT_HOURLY_LIMIT=100
CRM_AI_LOCK_SECONDS=300

# Budget (USD per month, 0 = off). Prices per model go in config/crm_ai.php -> budget.pricing.
CRM_AI_MONTHLY_BUDGET_USD=0
CRM_AI_ALLOW_UNKNOWN_COST=false

# Queue
CRM_AI_QUEUE=default

# Customer Intelligence
CRM_AI_CUSTOMER_INTELLIGENCE_PROMPT=v1
CRM_AI_CI_FRESH_DAYS=30
CRM_AI_CI_REVIEW_DAYS=90
CRM_AI_CI_MAX_SOURCES=5
CRM_AI_CI_MAX_SOURCE_CHARS=4000
CRM_AI_CI_DORMANT_DAYS=90
CRM_AI_OWN_COMPANY_PROFILE=

# Sales Task Planner
CRM_AI_SALES_TASK_PLANNER_PROMPT=v1
CRM_AI_STP_MAX_CANDIDATES=40
CRM_AI_STP_MAX_SUGGESTIONS=20
CRM_AI_STP_OPP_IDLE_DAYS=3
CRM_AI_STP_PROFORMA_IDLE_DAYS=3
CRM_AI_STP_CUSTOMER_IDLE_DAYS=30
CRM_AI_STP_NEAR_CLOSE_DAYS=14
CRM_AI_STP_HIGH_AMOUNT=500000000

# Web search: null (default, not configured)
CRM_AI_SEARCH_DRIVER=null
CRM_AI_FETCH_TIMEOUT=10
CRM_AI_FETCH_MAX_BYTES=1000000

# Logging of sanitized payloads in ai_runs
CRM_AI_LOG_PAYLOADS=true
CRM_AI_LOG_MAX_CHARS=20000
```

The API key is only needed for `CRM_AI_DRIVER=openai`.
It must live in `.env` or the server's secret store only; they are never written to `ai_runs`, logs, HTML or JS.

After changing `.env` in production: `php artisan config:clear` (or `config:cache`) and restart the queue worker.
