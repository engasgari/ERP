<?php

namespace App\Services\Crm;

use App\Jobs\Crm\ProcessCustomerIntelligenceJob;
use App\Models\Ai\AiRun;
use App\Models\Crm\CustomerIntelligence;
use App\Models\Party;
use App\Models\User;
use App\Repositories\Crm\CrmCustomerRepository;
use App\Services\Ai\AiRunner;
use App\Services\Ai\AiUsageGuard;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\Prompts\AiPromptRegistry;
use App\Services\Ai\WebSearch\Contracts\WebSearchProvider;
use App\Services\Ai\WebSearch\FetchedSource;
use App\Services\Ai\WebSearch\VerifiedSourceFetcher;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Customer Intelligence agent (Phase 1).
 *
 * Laravel decides who may run it, which data leaves the system and which sources are trusted.
 * The model only summarises verified sources; its output is validated and post-filtered so that
 * unverified URLs and unsourced external claims are never stored as facts.
 */
class CustomerIntelligenceService
{
    public const AGENT = 'customer_intelligence';

    public const PERMISSION = 'crm.ai.customer_intelligence.request';

    public function __construct(
        private readonly CrmCustomerRepository $customers,
        private readonly CrmCustomerInsightService $insights,
        private readonly AiRunner $runner,
        private readonly AiUsageGuard $guard,
        private readonly AiPromptRegistry $prompts,
        private readonly WebSearchProvider $search,
        private readonly VerifiedSourceFetcher $fetcher,
    ) {}

    public function isEnabled(): bool
    {
        return $this->guard->isAgentEnabled(self::AGENT) && $this->isInstalled();
    }

    /**
     * Migrations may not be applied yet on a given environment; the customer page must keep working.
     */
    public function isInstalled(): bool
    {
        static $installed = null;

        return $installed ??= Schema::hasTable('ai_runs')
            && Schema::hasTable('crm_customer_intelligence')
            && Schema::hasTable('crm_customer_intelligence_sources');
    }

    public function canRequest(User $user, int $partyId): bool
    {
        return ($user->isAdmin() || $user->hasPermission(self::PERMISSION))
            && $this->customers->findForShow($partyId, $user) !== null;
    }

    /**
     * Latest record for display, scoped by the caller's access to the customer.
     */
    public function latestFor(int $partyId, User $user): ?CustomerIntelligence
    {
        if (! $this->isInstalled() || $this->customers->findForShow($partyId, $user) === null) {
            return null;
        }

        return CustomerIntelligence::query()
            ->where('party_id', $partyId)
            ->with(['sources' => fn ($q) => $q->orderByDesc('is_verified')->orderBy('id')])
            ->latest('id')
            ->first();
    }

    /**
     * @throws AiException
     */
    public function request(int $partyId, User $user): CustomerIntelligence
    {
        $this->guard->assertEnabled(self::AGENT);

        if (! $this->isInstalled()) {
            throw AiException::make(AiException::NOT_CONFIGURED, 'جدول‌های هوش مصنوعی هنوز ایجاد نشده‌اند (migrate اجرا نشده است).');
        }

        if (! $this->canRequest($user, $partyId)) {
            throw AiException::make(AiException::UNAUTHORIZED, 'اجازه درخواست خلاصه هوشمند برای این مشتری را ندارید.');
        }

        $lock = Cache::lock("crm-ai:customer-intelligence:{$partyId}", 10);

        if (! $lock->get()) {
            throw AiException::make(AiException::DUPLICATE, 'درخواست دیگری برای این مشتری در حال ثبت است.');
        }

        try {
            $this->expireStalePending($partyId);

            $pending = CustomerIntelligence::query()
                ->where('party_id', $partyId)
                ->where('status', CustomerIntelligence::STATUS_PENDING)
                ->exists();

            if ($pending) {
                throw AiException::make(AiException::DUPLICATE, 'خلاصه هوشمند این مشتری در حال تولید است.');
            }

            $this->guard->hitRateLimits(self::AGENT, $user->id);

            $prompt = $this->prompts->resolve(self::AGENT);

            $intelligence = DB::transaction(function () use ($partyId, $user, $prompt) {
                $run = $this->runner->createRun(
                    $prompt,
                    $user->id,
                    'party',
                    $partyId,
                    self::AGENT.':party:'.$partyId,
                    'customer_profile',
                );

                return CustomerIntelligence::query()->create([
                    'party_id' => $partyId,
                    'ai_run_id' => $run->id,
                    'status' => CustomerIntelligence::STATUS_PENDING,
                    'review_status' => CustomerIntelligence::REVIEW_PENDING,
                    'prompt_version' => $prompt->version(),
                    'requested_by' => $user->id,
                ]);
            });
        } finally {
            $lock->release();
        }

        ProcessCustomerIntelligenceJob::dispatch($intelligence->id)
            ->onQueue((string) config('crm_ai.queue', 'default'));

        return $intelligence;
    }

    /**
     * Executed by the queued job. Never throws for expected AI failures; the record is marked failed instead.
     */
    public function process(int $intelligenceId): void
    {
        $intelligence = CustomerIntelligence::query()->with(['party.crmProfile', 'aiRun'])->find($intelligenceId);

        if (! $intelligence || $intelligence->status !== CustomerIntelligence::STATUS_PENDING) {
            return;
        }

        $run = $intelligence->aiRun;
        $party = $intelligence->party;

        if (! $run || ! $party || $run->status !== AiRun::STATUS_PENDING) {
            $this->markFailed($intelligence, 'اجرای مرتبط معتبر نیست یا قبلاً پردازش شده است.');

            return;
        }

        $prompt = $this->prompts->resolve(self::AGENT, $intelligence->prompt_version);
        $summary = $this->insights->internalSummary($party);

        $fetched = $this->collectSources($party);
        $this->storeSources($intelligence, $fetched);
        $verified = array_values(array_filter($fetched, fn (FetchedSource $s) => $s->isVerified()));

        [$payload, $exempt] = $this->buildPayload($party, $summary, $verified);

        try {
            $output = $this->runner->execute($run, $prompt, $payload, $exempt);
        } catch (AiException $e) {
            $this->markFailed($intelligence, $e->getMessage());

            return;
        }

        $result = $this->enforceSourceRules($output, $verified);

        $intelligence->forceFill([
            'status' => $result['identity']['status'] === 'matched'
                ? CustomerIntelligence::STATUS_COMPLETED
                : CustomerIntelligence::STATUS_INSUFFICIENT,
            'identity_status' => $result['identity']['status'],
            'confidence' => $result['identity']['confidence'],
            'result' => $result + [
                'meta' => [
                    'search_provider' => $this->search->name(),
                    'search_configured' => $this->search->isConfigured(),
                    'verified_source_count' => count($verified),
                ],
            ],
            'model' => $run->fresh()?->model,
            'generated_at' => now(),
            'sources_checked_at' => $fetched === [] ? null : now(),
        ])->save();

        $this->attachClaims($intelligence, $result['source_claims']);
    }

    public function review(CustomerIntelligence $intelligence, User $user, bool $accepted): void
    {
        if (! $this->canRequest($user, (int) $intelligence->party_id)) {
            throw AiException::make(AiException::UNAUTHORIZED, 'اجازه بازبینی این خلاصه را ندارید.');
        }

        if (! in_array($intelligence->status, [CustomerIntelligence::STATUS_COMPLETED, CustomerIntelligence::STATUS_INSUFFICIENT], true)) {
            return;
        }

        $intelligence->forceFill([
            'review_status' => $accepted ? CustomerIntelligence::REVIEW_ACCEPTED : CustomerIntelligence::REVIEW_REJECTED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ])->save();
    }

    /**
     * fresh | review | stale, based on configurable day thresholds.
     */
    public function freshness(?CustomerIntelligence $intelligence): ?string
    {
        if (! $intelligence?->generated_at) {
            return null;
        }

        $age = $intelligence->generated_at->diffInDays(now());
        $fresh = (int) config('crm_ai.agents.customer_intelligence.freshness.fresh_days', 30);
        $review = (int) config('crm_ai.agents.customer_intelligence.freshness.review_days', 90);

        return match (true) {
            $age <= $fresh => 'fresh',
            $age <= $review => 'review',
            default => 'stale',
        };
    }

    /**
     * @return list<FetchedSource>
     */
    private function collectSources(Party $party): array
    {
        if (! $this->search->isConfigured()) {
            return [];
        }

        $maxSources = (int) config('crm_ai.agents.customer_intelligence.max_sources', 5);
        $maxChars = (int) config('crm_ai.agents.customer_intelligence.max_source_chars', 4000);
        $query = trim(implode(' ', array_filter([$party->name, $party->crmProfile?->city])));

        $candidates = [];
        if ($website = $party->crmProfile?->website) {
            $candidates[] = ['url' => $this->normalizeUrl($website), 'type' => 'official_website_claimed'];
        }

        try {
            foreach ($this->search->search($query, $maxSources) as $hit) {
                $candidates[] = ['url' => $hit->url, 'type' => $hit->sourceType];
            }
        } catch (\Throwable $e) {
            Log::warning('crm_ai.search_failed', ['provider' => $this->search->name(), 'exception' => $e::class]);
        }

        $seen = [];
        $fetched = [];
        foreach ($candidates as $candidate) {
            $key = rtrim(strtolower($candidate['url']), '/');
            if (isset($seen[$key]) || count($fetched) >= $maxSources) {
                continue;
            }
            $seen[$key] = true;
            $fetched[] = $this->fetcher->fetch($candidate['url'], $maxChars, $candidate['type']);
        }

        return $fetched;
    }

    /**
     * @param  list<FetchedSource>  $fetched
     */
    private function storeSources(CustomerIntelligence $intelligence, array $fetched): void
    {
        foreach ($fetched as $source) {
            $intelligence->sources()->create([
                'url' => mb_substr($source->url, 0, 2048),
                'domain' => mb_substr($source->domain, 0, 255),
                'title' => $source->title,
                'source_type' => $source->sourceType,
                'fetch_status' => $source->fetchStatus,
                'http_status' => $source->httpStatus,
                'is_verified' => $source->isVerified(),
                'fetched_at' => $source->isVerified() ? $source->checkedAt : null,
                'last_checked_at' => $source->checkedAt,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  list<FetchedSource>  $verified
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function buildPayload(Party $party, array $summary, array $verified): array
    {
        $isCompany = $party->kind === 'company';

        $identity = array_filter([
            'name' => $party->name,
            'kind' => $party->kind,
            // Company registration identifiers are public; personal national codes are never sent.
            'registration_number' => $isCompany ? $party->registration_number : null,
            'national_id' => $isCompany ? $party->national_id : null,
            'city' => $party->crmProfile?->city,
            'website' => $party->crmProfile?->website,
            'industry' => $party->crmProfile?->industry,
        ], fn ($v) => $v !== null && $v !== '');

        $payload = [
            'identity' => $identity,
            'crm_facts' => $this->insights->toAiFacts($summary),
            'sources' => array_map(fn (FetchedSource $s) => [
                'url' => $s->url,
                'title' => $s->title,
                'text' => $s->text,
            ], $verified),
        ];

        if ($own = config('crm_ai.agents.customer_intelligence.own_company_profile')) {
            $payload['own_company'] = mb_substr((string) $own, 0, 2000);
        }

        $exempt = ['identity.name', 'identity.website'];
        if ($isCompany) {
            $exempt[] = 'identity.registration_number';
            $exempt[] = 'identity.national_id';
        }
        foreach (array_keys($verified) as $i) {
            $exempt[] = "sources.{$i}.url";
        }

        return [$payload, $exempt];
    }

    /**
     * Drops anything external that is not backed by a verified, actually fetched source.
     *
     * @param  array<string, mixed>  $output
     * @param  list<FetchedSource>  $verified
     * @return array<string, mixed>
     */
    private function enforceSourceRules(array $output, array $verified): array
    {
        $verifiedUrls = [];
        $verifiedHosts = [];
        foreach ($verified as $source) {
            $verifiedUrls[$this->urlKey($source->url)] = $source->url;
            $verifiedHosts[$this->hostKey($source->url)] = true;
        }

        $missing = $output['missing_information'] ?? [];
        $profile = $output['profile'];

        $profile['source_urls'] = array_values(array_unique(array_filter(
            array_map(fn ($url) => $verifiedUrls[$this->urlKey((string) $url)] ?? null, $profile['source_urls'] ?? []),
        )));

        $website = $profile['official_website'] ?? null;
        if ($website && ! isset($verifiedHosts[$this->hostKey($website)])) {
            $profile['official_website'] = null;
            $missing[] = 'وب‌سایت پیشنهادی از منبع تأییدشده دریافت نشد و حذف شد.';
        }

        $identity = $output['identity'];
        $hasExternalContent = filled($profile['summary'] ?? null) || filled($profile['industry'] ?? null)
            || ! empty($profile['products_services']) || filled($profile['official_website'] ?? null);

        if ($identity['status'] !== 'matched' || $profile['source_urls'] === []) {
            if ($hasExternalContent) {
                $missing[] = 'اطلاعات بیرونی بدون منبع تأییدشده حذف شد.';
            }

            $profile = [
                'summary' => null,
                'industry' => null,
                'products_services' => [],
                'official_website' => null,
                'source_urls' => [],
            ];

            if ($identity['status'] === 'matched') {
                $identity['status'] = 'ambiguous';
                $identity['confidence'] = 'low';
                $identity['ambiguities'][] = 'تطبیق هویت بدون منبع تأییدشده قابل قبول نیست.';
            }
        }

        $claims = [];
        foreach ($output['source_claims'] ?? [] as $claim) {
            $url = $verifiedUrls[$this->urlKey((string) $claim['url'])] ?? null;
            if ($url !== null) {
                $claims[] = ['url' => $url, 'claims' => array_values($claim['claims'] ?? [])];
            }
        }

        if ($verified === []) {
            $missing[] = $this->search->isConfigured()
                ? 'هیچ منبع وب قابل‌دریافتی برای این مشتری یافت نشد.'
                : 'جست‌وجوی وب پیکربندی نشده است؛ اطلاعات بیرونی بررسی نشد.';
        }

        return [
            'identity' => $identity,
            'profile' => $profile,
            'source_claims' => $claims,
            'relevance_to_aleh' => $profile['source_urls'] === [] ? null : ($output['relevance_to_aleh'] ?? null),
            'missing_information' => array_values(array_unique($missing)),
            'suggested_next_action' => $output['suggested_next_action'] ?? null,
            'human_review_note' => $output['human_review_note'] ?? null,
        ];
    }

    /**
     * @param  list<array{url: string, claims: list<string>}>  $claims
     */
    private function attachClaims(CustomerIntelligence $intelligence, array $claims): void
    {
        foreach ($claims as $claim) {
            $intelligence->sources()
                ->where('is_verified', true)
                ->where('url', $claim['url'])
                ->update(['claims' => json_encode($claim['claims'], JSON_UNESCAPED_UNICODE)]);
        }
    }

    /**
     * Pending requests whose job never ran (e.g. no queue worker) must not block new requests forever.
     */
    private function expireStalePending(int $partyId): void
    {
        $cutoff = now()->subSeconds((int) config('crm_ai.limits.lock_seconds', 300));

        CustomerIntelligence::query()
            ->where('party_id', $partyId)
            ->where('status', CustomerIntelligence::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->with('aiRun')
            ->get()
            ->each(function (CustomerIntelligence $stale) {
                if ($stale->aiRun && $stale->aiRun->isActive()) {
                    $stale->aiRun->forceFill([
                        'status' => AiRun::STATUS_CANCELLED,
                        'error_code' => 'expired',
                        'error_message' => 'Pending run expired before processing.',
                        'finished_at' => now(),
                    ])->save();
                }

                $this->markFailed($stale, 'درخواست قبلی در مهلت مقرر پردازش نشد (صف پردازش فعال نیست؟).');
            });
    }

    private function markFailed(CustomerIntelligence $intelligence, string $reason): void
    {
        $intelligence->forceFill([
            'status' => CustomerIntelligence::STATUS_FAILED,
            'failure_reason' => mb_substr($reason, 0, 500),
        ])->save();
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);

        return preg_match('#^https?://#i', $url) ? $url : 'https://'.$url;
    }

    private function urlKey(string $url): string
    {
        return rtrim(strtolower(trim($url)), '/');
    }

    private function hostKey(string $url): string
    {
        $host = strtolower((string) parse_url($this->normalizeUrl($url), PHP_URL_HOST));

        return preg_replace('/^www\./', '', $host) ?? $host;
    }
}
