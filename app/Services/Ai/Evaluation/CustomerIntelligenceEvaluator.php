<?php

namespace App\Services\Ai\Evaluation;

/**
 * Scores one stored Customer Intelligence result against a human-prepared reference case.
 * It never calls a model; it only compares stored output with reference data supplied by a human.
 *
 * Reference case keys:
 *   expected_identity: matched|ambiguous|not_found
 *   expected_name_keywords: list<string>   (any match counts)
 *   expected_industry_keywords: list<string>
 *   expected_website_domain: ?string
 */
class CustomerIntelligenceEvaluator
{
    /**
     * @param  array<string, mixed>  $reference
     * @param  array<string, mixed>|null  $result  stored CustomerIntelligence::result
     * @param  list<string>  $verifiedUrls  URLs of sources stored as verified for that result
     * @return array<string, bool|null>
     */
    public function score(array $reference, ?array $result, array $verifiedUrls): array
    {
        if ($result === null) {
            return [
                'has_result' => false,
                'identity_correct' => false,
                'name_correct' => null,
                'industry_correct' => null,
                'website_correct' => null,
                'sources_valid' => null,
                'no_unsourced_claims' => null,
            ];
        }

        $identity = $result['identity'] ?? [];
        $profile = $result['profile'] ?? [];
        $expectedIdentity = $reference['expected_identity'] ?? null;
        $isMatched = ($identity['status'] ?? null) === 'matched';

        $sourceUrls = $profile['source_urls'] ?? [];
        $verifiedKeys = array_map(fn ($u) => rtrim(strtolower($u), '/'), $verifiedUrls);

        $hasExternal = filled($profile['summary'] ?? null) || filled($profile['industry'] ?? null)
            || ! empty($profile['products_services']) || filled($profile['official_website'] ?? null);

        return [
            'has_result' => true,
            'identity_correct' => $expectedIdentity === null ? null : ($identity['status'] ?? null) === $expectedIdentity,
            'name_correct' => $this->keywordMatch((string) ($identity['matched_name'] ?? ''), $reference['expected_name_keywords'] ?? [], $isMatched),
            'industry_correct' => $this->keywordMatch(
                trim(($profile['industry'] ?? '').' '.($profile['summary'] ?? '')),
                $reference['expected_industry_keywords'] ?? [],
                $isMatched,
            ),
            'website_correct' => array_key_exists('expected_website_domain', $reference)
                ? $this->domain($profile['official_website'] ?? null) === $this->domain($reference['expected_website_domain'])
                : null,
            'sources_valid' => collect($sourceUrls)->every(fn ($u) => in_array(rtrim(strtolower($u), '/'), $verifiedKeys, true)),
            'no_unsourced_claims' => ! $hasExternal || $sourceUrls !== [],
        ];
    }

    /**
     * @param  list<array<string, bool|null>>  $scores
     * @return array<string, array{passed: int, total: int}>
     */
    public function aggregate(array $scores): array
    {
        $totals = [];

        foreach ($scores as $score) {
            foreach ($score as $metric => $value) {
                if ($value === null) {
                    continue;
                }
                $totals[$metric] ??= ['passed' => 0, 'total' => 0];
                $totals[$metric]['total']++;
                $totals[$metric]['passed'] += $value ? 1 : 0;
            }
        }

        return $totals;
    }

    /**
     * @param  list<string>  $keywords
     */
    private function keywordMatch(string $text, array $keywords, bool $applicable): ?bool
    {
        if ($keywords === [] || ! $applicable) {
            return null;
        }

        foreach ($keywords as $keyword) {
            if ($keyword !== '' && mb_stripos($text, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    private function domain(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url(preg_match('#^https?://#i', $url) ? $url : 'https://'.$url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }
}
