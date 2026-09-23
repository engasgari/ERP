<?php

use Carbon\Carbon;
use Hekmatinasser\Verta\Verta;

if (!function_exists('getPersianMonthName')) {
    function getPersianMonthName($month): string
    {
        $months = [
            1 => 'فروردین',
            2 => 'اردیبهشت',
            3 => 'خرداد',
            4 => 'تیر',
            5 => 'مرداد',
            6 => 'شهریور',
            7 => 'مهر',
            8 => 'آبان',
            9 => 'آذر',
            10 => 'دی',
            11 => 'بهمن',
            12 => 'اسفند',
        ];

        return $months[(int) $month] ?? 'نامشخص';
    }
}

if (!function_exists('getCurrentPersianYear')) {
    function getCurrentPersianYear(): string
    {
        return Verta::now()->format('Y');
    }
}

if (!function_exists('getCurrentPersianMonth')) {
    function getCurrentPersianMonth(): string
    {
        return Verta::now()->format('n');
    }
}

if (!function_exists('getPersianMonthRange')) {
    function getPersianMonthRange($year, $month): array
    {
        $startJalali = Verta::createJalali((int) $year, (int) $month, 1, 0, 0, 0);
        $endJalali = (clone $startJalali)->endMonth();

        return [
            'start' => Carbon::instance($startJalali->datetime()),
            'end' => Carbon::instance($endJalali->datetime())->endOfDay(),
            'start_jalali' => $startJalali->format('Y-m-d'),
            'end_jalali' => $endJalali->format('Y-m-d'),
        ];
    }
}

if (!function_exists('chartAccountDisplayLabel')) {
    function chartAccountDisplayLabel($account): string
    {
        if (! $account) {
            return '-';
        }

        $code = data_get($account, 'code');
        $title = data_get($account, 'title') ?: data_get($account, 'name') ?: data_get($account, 'bank_name');

        if (! $code && ! $title) {
            return '-';
        }

        if ($code && $title) {
            return trim($code . ' - ' . $title);
        }

        return (string) ($code ?: $title);
    }
}

if (!function_exists('convertToPersian')) {
    function convertToPersian($carbonDate): Verta
    {
        return Verta::instance($carbonDate);
    }
}

if (!function_exists('getPersianDate')) {
    function getPersianDate($carbonDate = null): Verta
    {
        return Verta::instance($carbonDate ?: Carbon::now());
    }
}

if (!function_exists('normalizePersianDigits')) {
    function normalizePersianDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }
}

if (!function_exists('toPersianDigits')) {
    function toPersianDigits($value): string
    {
        return strtr((string) $value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    }
}

if (!function_exists('jalaliToGregorianDate')) {
    function jalaliToGregorianDate(?string $value): ?string
    {
        $value = trim((string) normalizePersianDigits($value));

        if ($value === '') {
            return null;
        }

        try {
            $normalized = str_replace(['.', '/'], '-', $value);
            $digitsOnly = preg_replace('/[^\d]/', '', $value);

            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $digitsOnly, $compactMatches) === 1) {
                $normalized = sprintf('%04d-%02d-%02d', (int) $compactMatches[1], (int) $compactMatches[2], (int) $compactMatches[3]);
            }

            if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $normalized, $matches) !== 1) {
                return null;
            }

            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];

            if ($year >= 1700) {
                return checkdate($month, $day, $year)
                    ? Carbon::create($year, $month, $day)->format('Y-m-d')
                    : null;
            }

            if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                return null;
            }

            $jalali = Verta::parse(sprintf('%04d-%02d-%02d', $year, $month, $day));

            if ($jalali->format('Y/m/d') !== sprintf('%04d/%02d/%02d', $year, $month, $day)) {
                return null;
            }

            return Carbon::instance($jalali->datetime())->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('gregorianToJalaliDate')) {
    function gregorianToJalaliDate($value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return toPersianDigits(Verta::instance($value)->format('Y/m/d'));
        } catch (Throwable) {
            return '';
        }
    }
}

if (!function_exists('formatJalaliDateTime')) {
    function formatJalaliDateTime($value, string $fallback = '-'): string
    {
        if (!$value) {
            return $fallback;
        }

        try {
            return toPersianDigits(Verta::instance($value)->format('Y/m/d H:i'));
        } catch (Throwable) {
            return $fallback;
        }
    }
}

if (!function_exists('formatJalaliDateSafe')) {
    function formatJalaliDateSafe($value, string $fallback = '-'): string
    {
        if (!$value) {
            return $fallback;
        }

        try {
            $date = $value instanceof DateTimeInterface ? Carbon::instance($value) : Carbon::parse($value);
            $gy = (int) $date->format('Y');
            $gm = (int) $date->format('n');
            $gd = (int) $date->format('j');

            $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

            $gy -= 1600;
            $gm -= 1;
            $gd -= 1;

            $gDayNo = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);

            for ($i = 0; $i < $gm; $i++) {
                $gDayNo += $gDaysInMonth[$i];
            }

            if ($gm > 1 && (($gy + 1600) % 4 === 0 && (($gy + 1600) % 100 !== 0 || ($gy + 1600) % 400 === 0))) {
                $gDayNo++;
            }

            $gDayNo += $gd;
            $jDayNo = $gDayNo - 79;
            $jNp = intdiv($jDayNo, 12053);
            $jDayNo %= 12053;
            $jy = 979 + 33 * $jNp + 4 * intdiv($jDayNo, 1461);
            $jDayNo %= 1461;

            if ($jDayNo >= 366) {
                $jy += intdiv($jDayNo - 1, 365);
                $jDayNo = ($jDayNo - 1) % 365;
            }

            for ($i = 0; $i < 11 && $jDayNo >= $jDaysInMonth[$i]; $i++) {
                $jDayNo -= $jDaysInMonth[$i];
            }

            return toPersianDigits(sprintf('%04d/%02d/%02d', $jy, $i + 1, $jDayNo + 1));
        } catch (Throwable) {
            return $fallback;
        }
    }
}

if (!function_exists('jalaliToGregorianDateSafe')) {
    function jalaliToGregorianDateSafe(int $jy, int $jm, int $jd): ?string
    {
        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) {
            return null;
        }

        try {
            $gDaysInMonth = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
            $jDaysInMonth = [31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29];

            $jy -= 979;
            $jm -= 1;
            $jd -= 1;

            $jDayNo = 365 * $jy + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4);

            for ($i = 0; $i < $jm; $i++) {
                $jDayNo += $jDaysInMonth[$i];
            }

            $jDayNo += $jd;
            $gDayNo = $jDayNo + 79;
            $gy = 1600 + 400 * intdiv($gDayNo, 146097);
            $gDayNo %= 146097;

            $leap = true;
            if ($gDayNo >= 36525) {
                $gDayNo--;
                $gy += 100 * intdiv($gDayNo, 36524);
                $gDayNo %= 36524;

                if ($gDayNo >= 365) {
                    $gDayNo++;
                } else {
                    $leap = false;
                }
            }

            $gy += 4 * intdiv($gDayNo, 1461);
            $gDayNo %= 1461;

            if ($gDayNo >= 366) {
                $leap = false;
                $gDayNo--;
                $gy += intdiv($gDayNo, 365);
                $gDayNo %= 365;
            }

            for ($i = 0; $gDayNo >= $gDaysInMonth[$i] + (($i === 1 && $leap) ? 1 : 0); $i++) {
                $gDayNo -= $gDaysInMonth[$i] + (($i === 1 && $leap) ? 1 : 0);
            }

            return Carbon::create($gy, $i + 1, $gDayNo + 1)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('jalaliMonthRangeGregorianSafe')) {
    function jalaliMonthRangeGregorianSafe(int $year, int $month): array
    {
        $start = jalaliToGregorianDateSafe($year, $month, 1);
        $nextYear = $month === 12 ? $year + 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $nextStart = jalaliToGregorianDateSafe($nextYear, $nextMonth, 1);

        $startDate = Carbon::parse($start);
        $endDate = Carbon::parse($nextStart)->subDay()->endOfDay();

        return [
            'start' => $startDate,
            'end' => $endDate,
            'start_jalali' => sprintf('%04d/%02d/01', $year, $month),
            'end_jalali' => formatJalaliDateSafe($endDate),
        ];
    }
}

if (!function_exists('todayJalaliDate')) {
    function todayJalaliDate(): string
    {
        return formatJalaliDateSafe(Carbon::now());
    }
}

if (!function_exists('jalaliDateInputValue')) {
    function jalaliDateInputValue($value = null, $fallback = null): string
    {
        $value = trim((string) normalizePersianDigits($value));

        if ($value === '') {
            return gregorianToJalaliDate($fallback);
        }

        $normalized = str_replace(['.', '-'], '/', $value);
        $year = (int) substr($normalized, 0, 4);

        if ($year >= 1700) {
            return gregorianToJalaliDate($value);
        }

        $converted = jalaliToGregorianDate($normalized);

        return $converted ? gregorianToJalaliDate($converted) : toPersianDigits($normalized);
    }
}

if (!function_exists('jalaliDateTimeInputValue')) {
    function jalaliDateTimeInputValue($value = null): string
    {
        if (!$value) {
            return '';
        }

        try {
            return toPersianDigits(Verta::instance($value)->format('Y/m/d H:i'));
        } catch (Throwable) {
            return '';
        }
    }
}

if (!function_exists('jalaliToGregorianDateTime')) {
    function jalaliToGregorianDateTime(?string $value): ?string
    {
        $value = trim((string) normalizePersianDigits($value));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T\s](\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $isoMatches) === 1) {
            $year = (int) substr($isoMatches[1], 0, 4);

            if ($year >= 1700) {
                try {
                    return Carbon::parse($value)->format('Y-m-d H:i:s');
                } catch (Throwable) {
                    return null;
                }
            }
        }

        $datePart = $value;
        $hour = 0;
        $minute = 0;
        $second = 0;

        if (preg_match('/^(.+?)\s+(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches) === 1) {
            $datePart = trim($matches[1]);
            $hour = (int) $matches[2];
            $minute = (int) $matches[3];
            $second = isset($matches[4]) ? (int) $matches[4] : 0;
        }

        $gregorianDate = jalaliToGregorianDate($datePart);

        if (!$gregorianDate) {
            try {
                return Carbon::parse($value)->format('Y-m-d H:i:s');
            } catch (Throwable) {
                return null;
            }
        }

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return Carbon::parse($gregorianDate)
            ->setTime($hour, $minute, $second)
            ->format('Y-m-d H:i:s');
    }
}

if (!function_exists('normalizeJalaliFilterDate')) {
    function normalizeJalaliFilterDate(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $digits = preg_replace('/[^\d]/', '', normalizePersianDigits($value));

        if (strlen($digits) === 8) {
            $formatted = sprintf(
                '%s/%s/%s',
                substr($digits, 0, 4),
                substr($digits, 4, 2),
                substr($digits, 6, 2),
            );

            return toPersianDigits($formatted);
        }

        return jalaliDateInputValue($value);
    }
}

if (!function_exists('formatNumber')) {
    function formatNumber($value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            $value = 0;
        }

        if (extension_loaded('intl')) {
            $formatter = new NumberFormatter('fa_IR', NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $formatted = $formatter->format((float) $value);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        $formatted = number_format((float) $value, $decimals, '.', ',');
        $formatted = str_replace(',', '٬', $formatted);

        if ($decimals > 0) {
            $formatted = str_replace('.', '٫', $formatted);
        }

        return toPersianDigits($formatted);
    }
}

if (!function_exists('formatMoney')) {
    function formatMoney($value, int $decimals = 0): string
    {
        return formatNumber($value, $decimals);
    }
}

if (!function_exists('formatQuantity')) {
    function formatQuantity($value, int $maxDecimals = 3): string
    {
        if ($value === null || $value === '') {
            $value = 0;
        }

        $numeric = round((float) $value, $maxDecimals);
        $plain = rtrim(rtrim(sprintf('%.' . $maxDecimals . 'f', $numeric), '0'), '.');
        $decimals = str_contains($plain, '.') ? strlen(explode('.', $plain)[1]) : 0;

        return formatNumber($numeric, $decimals);
    }
}

if (!function_exists('normalizeMoneyValue')) {
    function normalizeMoneyValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = normalizePersianDigits((string) $value);
        $normalized = str_replace(['٬', ',', ' ', '٫'], ['', '', '', '.'], $normalized);

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }
}

if (!function_exists('moneyInputValue')) {
    function moneyInputValue(mixed $value, int $decimals = 0): string
    {
        $numeric = normalizeMoneyValue($value);

        if ($numeric === null || abs($numeric) < 0.0000001) {
            return '';
        }

        return formatMoney($numeric, $decimals);
    }
}

if (!function_exists('persianNumberToWords')) {
    function persianNumberToWords($number): string
    {
        $number = (int) round((float) $number);

        if ($number === 0) {
            return 'صفر';
        }

        $negative = $number < 0;
        $number = abs($number);

        $ones = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];
        $teens = [10 => 'ده', 11 => 'یازده', 12 => 'دوازده', 13 => 'سیزده', 14 => 'چهارده', 15 => 'پانزده', 16 => 'شانزده', 17 => 'هفده', 18 => 'هجده', 19 => 'نوزده'];
        $tens = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];
        $hundreds = ['', 'یکصد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];
        $scales = ['', 'هزار', 'میلیون', 'میلیارد', 'هزار میلیارد'];

        $chunkToWords = function (int $chunk) use ($ones, $teens, $tens, $hundreds): string {
            $parts = [];
            $hundred = intdiv($chunk, 100);
            $remainder = $chunk % 100;

            if ($hundred > 0) {
                $parts[] = $hundreds[$hundred];
            }

            if ($remainder >= 10 && $remainder <= 19) {
                $parts[] = $teens[$remainder];
            } else {
                $ten = intdiv($remainder, 10);
                $one = $remainder % 10;

                if ($ten > 0) {
                    $parts[] = $tens[$ten];
                }

                if ($one > 0) {
                    $parts[] = $ones[$one];
                }
            }

            return implode(' و ', $parts);
        };

        $parts = [];
        $scaleIndex = 0;

        while ($number > 0) {
            $chunk = $number % 1000;

            if ($chunk > 0) {
                $text = $chunkToWords($chunk);

                if (($scales[$scaleIndex] ?? '') !== '') {
                    $text .= ' ' . $scales[$scaleIndex];
                }

                array_unshift($parts, $text);
            }

            $number = intdiv($number, 1000);
            $scaleIndex++;
        }

        return ($negative ? 'منفی ' : '') . implode(' و ', $parts);
    }
}

if (! function_exists('searchSelectOptions')) {
    /**
     * @param  iterable<mixed>  $items
     * @return list<array{id:int|string,label:string,code:?string,category:?string,type:?string}>
     */
    function searchSelectOptions(
        iterable $items,
        ?callable $labelResolver = null,
        ?callable $codeResolver = null,
        ?callable $idResolver = null,
    ): array {
        $options = [];

        foreach ($items as $key => $item) {
            if (is_array($item)) {
                $options[] = [
                    'id' => $item['id'] ?? $key,
                    'label' => (string) ($item['label'] ?? $item['name'] ?? ''),
                    'code' => $item['code'] ?? null,
                    'category' => $item['category'] ?? null,
                    'type' => isset($item['type']) && is_string($item['type']) ? $item['type'] : null,
                ];

                continue;
            }

            if (is_string($item) || is_numeric($item)) {
                $options[] = [
                    'id' => $key,
                    'label' => (string) $item,
                    'code' => null,
                    'category' => null,
                    'type' => null,
                ];

                continue;
            }

            $options[] = [
                'id' => $idResolver ? $idResolver($item) : ($item->id ?? $key),
                'label' => $labelResolver ? (string) $labelResolver($item) : (string) ($item->name ?? $item->label ?? $item->title ?? ''),
                'code' => $codeResolver ? $codeResolver($item) : ($item->code ?? null),
                'category' => $item->category ?? null,
                'type' => isset($item->type) && is_string($item->type) ? $item->type : null,
            ];
        }

        return $options;
    }
}

if (! function_exists('itemSearchSelectOptions')) {
    /**
     * @param  iterable<object>  $items
     * @return list<array{id:int,label:string,code:?string,category:?string,type:string}>
     */
    function itemSearchSelectOptions(iterable $items, ?string $defaultTypeLabel = null): array
    {
        $options = [];

        foreach ($items as $item) {
            $type = $item->type ?? null;
            $typeLabel = match ($type) {
                'product' => 'کالا',
                'service' => 'خدمت',
                default => $defaultTypeLabel ?? 'کالا/خدمت',
            };

            $options[] = [
                'id' => (int) $item->id,
                'label' => (string) ($item->name ?? ''),
                'code' => $item->code ?? null,
                'category' => $item->category ?? null,
                'type' => $typeLabel,
            ];
        }

        return $options;
    }
}

if (! function_exists('erp_breadcrumb_url')) {
    function erp_breadcrumb_url(string $routeName, array $params = [], ?string $fallback = null): string
    {
        $context = app(\App\Services\BreadcrumbContextService::class);

        return $context->recall($routeName, $fallback)
            ?? (Route::has($routeName) ? route($routeName, $params) : ($fallback ?? url('/')));
    }
}

if (! function_exists('erp_with_return_to')) {
    function erp_with_return_to(string $url, ?string $returnTo = null, ?string $fromRoute = null): string
    {
        $returnTo ??= request()->query('return_to');
        $fromRoute ??= request()->query('from');

        if ($returnTo === null) {
            $currentRoute = request()->route()?->getName();
            $context = app(\App\Services\BreadcrumbContextService::class);

            if (is_string($currentRoute) && $currentRoute !== '' && ! $context->isReportRoute($currentRoute)) {
                $returnTo = request()->fullUrl();
                $fromRoute ??= $currentRoute;
            }
        }

        $query = array_filter([
            'return_to' => $returnTo,
            'from' => $fromRoute,
        ], fn ($value) => is_string($value) && $value !== '');

        if ($query === []) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . http_build_query($query);
    }
}

if (! function_exists('erp_report_url')) {
    function erp_report_url(string $routeName, array $params = [], ?string $returnTo = null, ?string $fromRoute = null): string
    {
        if (! Route::has($routeName)) {
            return erp_with_return_to(url('/'), $returnTo, $fromRoute);
        }

        return erp_with_return_to(route($routeName, $params), $returnTo, $fromRoute);
    }
}
