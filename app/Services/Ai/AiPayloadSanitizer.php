<?php

namespace App\Services\Ai;

use Illuminate\Support\Arr;

/**
 * Enforces the per-agent field allowlist and masks personal data inside free text
 * before anything is sent to an external provider or written to ai_runs.
 */
class AiPayloadSanitizer
{
    public const MASK = '[حذف‌شده]';

    private const PATTERNS = [
        // e-mail
        '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u',
        // IBAN (IR + 24 digits, spaces allowed)
        '/\bIR\s?(?:\d\s?){24}\b/iu',
        // card numbers (16 digits, optional separators)
        '/(?<!\d)(?:\d{4}[\s\-]?){3}\d{4}(?!\d)/u',
        // Iranian mobile: 09xx xxx xxxx, +98 9xx..., 00989...
        '/(?<!\d)(?:\+98|0098|98|0)?\s?9\d{2}[\s\-]?\d{3}[\s\-]?\d{4}(?!\d)/u',
        // landline: 0xx(x) xxxxxxx(x)
        '/(?<!\d)0\d{2,3}[\s\-]?\d{7,8}(?!\d)/u',
        // national codes and other long identifiers (10+ consecutive digits)
        '/(?<!\d)\d{10,}(?!\d)/u',
    ];

    private const SECRET_KEYS = ['password', 'api_key', 'apikey', 'token', 'secret', 'authorization'];

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $exemptPaths  structured identity fields deliberately allowed unmasked (e.g. company registration number)
     * @return array<string, mixed>
     */
    public function sanitize(string $agent, array $payload, array $exemptPaths = []): array
    {
        $allowed = $this->filterAllowlist($payload, $this->allowlist($agent));

        return $this->maskRecursive($allowed, '', $exemptPaths);
    }

    /**
     * Masking only (no allowlist) for log storage of outputs and error messages.
     */
    public function maskText(string $text): string
    {
        $text = $this->normalizeDigits($text);

        return (string) preg_replace(self::PATTERNS, self::MASK, $text);
    }

    /**
     * @return list<string>
     */
    public function allowlist(string $agent): array
    {
        return array_values((array) config("crm_ai.payload_allowlist.{$agent}", []));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $allowlist
     * @return array<string, mixed>
     */
    private function filterAllowlist(array $payload, array $allowlist): array
    {
        $result = [];

        foreach ($allowlist as $path) {
            if (Arr::has($payload, $path)) {
                Arr::set($result, $path, Arr::get($payload, $path));
            }
        }

        return $result;
    }

    /**
     * @param  array<string|int, mixed>  $data
     * @param  list<string>  $exemptPaths
     * @return array<string|int, mixed>
     */
    private function maskRecursive(array $data, string $prefix, array $exemptPaths): array
    {
        foreach ($data as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->maskRecursive($value, $path, $exemptPaths);

                continue;
            }

            if (is_string($value) && ! in_array($path, $exemptPaths, true)) {
                $data[$key] = $this->maskText($value);
            }
        }

        return $data;
    }

    private function normalizeDigits(string $text): string
    {
        return strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
