<?php

namespace App\Services\Attendance;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PiofyDeviceClient
{
    /**
     * @return list<array{user_id:string,user_name:string,card_number:string,jalali_datetime:string,gregorian_datetime:string,operation_key:string,operation_title:string,type:int}>
     */
    public function fetchAttendanceRows(?string $baseUrl = null, ?string $username = null, ?string $password = null): array
    {
        $baseUrl = rtrim($baseUrl ?: (string) config('piofy.base_url'), '/');
        $username = $username ?: (string) config('piofy.username');
        $password = $password !== null ? $password : (string) config('piofy.password');

        if ($baseUrl === '' || $username === '' || $password === '') {
            throw new RuntimeException('تنظیمات اتصال به دستگاه Piofy کامل نیست (آدرس، نام کاربری، رمز).');
        }

        $cookieJar = new CookieJar();
        $http = $this->httpClient($cookieJar);
        $this->login($http, $baseUrl, $username, $password);

        $logsHtml = $http->get($baseUrl.'/Dashboard/Logs');
        if (! $logsHtml->successful()) {
            throw new RuntimeException('دریافت اطلاعات تردد از دستگاه ناموفق بود.');
        }

        $usersHtml = $http->get($baseUrl.'/Dashboard/Users');
        $cardsByCode = $usersHtml->successful()
            ? $this->parseUserCards($usersHtml->body())
            : [];

        return $this->parseLogRows($logsHtml->body(), $cardsByCode);
    }

    private function httpClient(CookieJar $cookieJar): PendingRequest
    {
        return Http::withOptions([
            'cookies' => $cookieJar,
            'allow_redirects' => true,
            'verify' => false,
            'timeout' => (int) config('piofy.timeout', 30),
        ])->asForm();
    }

    private function login(PendingRequest $http, string $baseUrl, string $username, string $password): void
    {
        $loginPage = $http->accept('text/html')->get($baseUrl.'/Account/Logon');
        if (! $loginPage->successful()) {
            throw new RuntimeException('صفحه ورود دستگاه در دسترس نیست.');
        }

        $token = $this->extractVerificationToken($loginPage->body());
        if ($token === null) {
            throw new RuntimeException('توکن ورود دستگاه یافت نشد.');
        }

        $response = $http->asForm()->post($baseUrl.'/Account/Logon', [
            'UserName' => $username,
            'Password' => $password,
            '__RequestVerificationToken' => $token,
        ]);

        if ($response->clientError() || $response->serverError()) {
            throw new RuntimeException('ورود به دستگاه ناموفق بود.');
        }

        $probe = $http->accept('text/html')->get($baseUrl.'/Dashboard/Logs');
        if (! $probe->successful() || str_contains($probe->body(), 'name="UserName"') || str_contains($probe->effectiveUri()?->__toString() ?? '', 'Account/Logon')) {
            throw new RuntimeException('نام کاربری یا رمز دستگاه نادرست است.');
        }
    }

    private function extractVerificationToken(string $html): ?string
    {
        if (preg_match('/name="__RequestVerificationToken"[^>]*value="([^"]+)"/i', $html, $matches) === 1) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (preg_match('/value="([^"]+)"[^>]*name="__RequestVerificationToken"/i', $html, $matches) === 1) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function parseUserCards(string $html): array
    {
        $cards = [];

        if (preg_match_all('/userArray\.push\(\{(.*?)\}\);/s', $html, $matches) === 0) {
            return $cards;
        }

        foreach ($matches[1] as $block) {
            $code = $this->extractJsString($block, 'code') ?? '';
            $card = $this->extractJsString($block, 'card') ?? '';
            if ($code !== '' && $card !== '') {
                $cards[$code] = $card;
            }
        }

        return $cards;
    }

    /**
     * @param  array<string, string>  $cardsByCode
     * @return list<array{user_id:string,user_name:string,card_number:string,jalali_datetime:string,gregorian_datetime:string,operation_key:string,operation_title:string,type:int}>
     */
    private function parseLogRows(string $html, array $cardsByCode): array
    {
        if (preg_match_all('/logArray\.push\(\{(.*?)\}\);/s', $html, $matches) === 0) {
            return [];
        }

        $rows = [];

        foreach ($matches[1] as $block) {
            $code = trim((string) ($this->extractJsString($block, 'code') ?? ''));
            $name = trim((string) ($this->extractJsString($block, 'name') ?? ''));
            $date = trim((string) ($this->extractJsString($block, 'date') ?? ''));
            $functionKey = trim((string) ($this->extractJsString($block, 'functionKey') ?? ''));
            $type = (int) ($this->extractJsNumber($block, 'type') ?? 0);

            if ($code === '' && $name === '') {
                continue;
            }

            if ($date === '') {
                continue;
            }

            $jalaliDateTime = preg_replace('/\s*-\s*/', ' ', $date) ?? $date;

            $rows[] = [
                'user_id' => $code,
                'user_name' => $name,
                'card_number' => $cardsByCode[$code] ?? '',
                'jalali_datetime' => $jalaliDateTime,
                'gregorian_datetime' => '',
                'operation_key' => $functionKey,
                'operation_title' => '',
                'type' => $type,
            ];
        }

        return $rows;
    }

    private function extractJsString(string $block, string $key): ?string
    {
        if (preg_match('/\b'.preg_quote($key, '/').'\s*:\s*\'((?:\\\\\'|[^\'])*)\'/u', $block, $matches) === 1) {
            return stripcslashes($matches[1]);
        }

        if (preg_match('/\b'.preg_quote($key, '/').'\s*:\s*"((?:\\\\"|[^"])*)"/u', $block, $matches) === 1) {
            return stripcslashes($matches[1]);
        }

        return null;
    }

    private function extractJsNumber(string $block, string $key): ?int
    {
        if (preg_match('/\b'.preg_quote($key, '/').'\s*:\s*(-?\d+)/u', $block, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }
}
