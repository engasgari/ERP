<?php

namespace App\Services\Ai\WebSearch;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads a candidate source. A source is "verified" only when this fetch returned HTTP 2xx HTML/text
 * from a public host. Redirects are not followed (they are reported, not trusted).
 */
class VerifiedSourceFetcher
{
    public function __construct(private readonly PublicHostGuard $hostGuard) {}

    public function fetch(string $url, int $maxChars, string $sourceType = 'web'): FetchedSource
    {
        $checkedAt = now();
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return new FetchedSource($url, $host, FetchedSource::INVALID_URL, null, null, null, $checkedAt, $sourceType);
        }

        if (! $this->hostGuard->isPublicHost($host)) {
            return new FetchedSource($url, $host, FetchedSource::BLOCKED_HOST, null, null, null, $checkedAt, $sourceType);
        }

        try {
            $response = Http::timeout((int) config('crm_ai.web_search.fetch_timeout_seconds', 10))
                ->withOptions(['allow_redirects' => false])
                ->withHeaders(['Accept' => 'text/html,text/plain;q=0.9'])
                ->get($url);
        } catch (Throwable) {
            return new FetchedSource($url, $host, FetchedSource::FAILED, null, null, null, $checkedAt, $sourceType);
        }

        $status = $response->status();

        if ($status >= 300 && $status < 400) {
            return new FetchedSource($url, $host, FetchedSource::REDIRECT, $status, null, null, $checkedAt, $sourceType);
        }

        if (! $response->successful()) {
            return new FetchedSource($url, $host, FetchedSource::HTTP_ERROR, $status, null, null, $checkedAt, $sourceType);
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        if ($contentType !== '' && ! str_contains($contentType, 'html') && ! str_contains($contentType, 'text/plain')) {
            return new FetchedSource($url, $host, FetchedSource::UNSUPPORTED_CONTENT, $status, null, null, $checkedAt, $sourceType);
        }

        $body = mb_substr((string) $response->body(), 0, (int) config('crm_ai.web_search.max_fetch_bytes', 1_000_000));

        return new FetchedSource(
            $url,
            $host,
            FetchedSource::OK,
            $status,
            $this->extractTitle($body),
            mb_substr($this->extractText($body), 0, $maxChars),
            $checkedAt,
            $sourceType,
        );
    }

    private function extractTitle(string $html): ?string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/isu', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            return $title !== '' ? mb_substr($title, 0, 500) : null;
        }

        return null;
    }

    private function extractText(string $html): string
    {
        $html = (string) preg_replace('/<(script|style|noscript|svg)[^>]*>.*?<\/\1>/isu', ' ', $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
