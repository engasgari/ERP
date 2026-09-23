<?php

namespace App\Services;

/**
 * Controlled AI analytics layer for sales.
 * Phase 1: deterministic brief from structured ERP metrics (no free DB access, no external LLM required).
 * External LLM can later consume the same structured payload.
 */
class AISalesAnalyticsService
{
    /**
     * @param  array<string, float|int|null>  $metrics
     * @return array{headline: string, bullets: list<string>, source: string}
     */
    public function dailyBrief(array $metrics): array
    {
        $bullets = [];
        $growth = $metrics['growth_percent'] ?? null;
        $margin = (float) ($metrics['margin_percent'] ?? 0);
        $overdue = (float) ($metrics['overdue'] ?? 0);
        $pipeline = (float) ($metrics['pipeline'] ?? 0);
        $tasks = (int) ($metrics['overdue_tasks'] ?? 0);

        if ($growth === null) {
            $bullets[] = 'برای مقایسه رشد فروش با ماه قبل داده مرجع کافی نیست.';
        } elseif ($growth > 0) {
            $bullets[] = 'فروش ماه جاری '.$this->pct($growth).' بیشتر از ماه قبل است.';
        } elseif ($growth < 0) {
            $bullets[] = 'فروش ماه جاری '.$this->pct(abs((float) $growth)).' کمتر از ماه قبل است.';
        } else {
            $bullets[] = 'فروش ماه جاری نسبت به ماه قبل تقریباً بدون تغییر است.';
        }

        if (($metrics['this_month_sales'] ?? 0) > 0) {
            $bullets[] = 'حاشیه سود ناخالص محاسبه‌شده برای این ماه حدود '.$this->pct($margin).' است.';
        }

        if ($overdue > 0.009) {
            $bullets[] = 'مطالبات سررسیدشده نیازمند پیگیری: '.formatMoney($overdue).' ریال.';
        }

        if ($pipeline > 0.009) {
            $bullets[] = 'ارزش Pipeline باز CRM: '.formatMoney($pipeline).' ریال.';
        }

        if ($tasks > 0) {
            $bullets[] = $tasks.' وظیفه CRM سررسید گذشته ثبت شده است.';
        }

        if ($bullets === []) {
            $bullets[] = 'اطلاعات کافی برای تولید خلاصه هوشمند وجود ندارد.';
        }

        $headline = ($growth !== null && $growth < -10)
            ? 'توجه: افت فروش نسبت به ماه قبل'
            : (($overdue > 0.009) ? 'وضعیت فروش پایدار؛ پیگیری مطالبات اولویت دارد' : 'خلاصه وضعیت فروش');

        return [
            'headline' => $headline,
            'bullets' => $bullets,
            'source' => 'deterministic_metrics_v1',
        ];
    }

    /**
     * @param  array<string, mixed>  $context  precomputed metrics from SalesDashboardService / SalesProfitCalculationService
     */
    public function answerQuestion(string $question, array $context): array
    {
        $q = mb_strtolower(trim($question));
        if ($q === '') {
            return [
                'answer' => 'لطفاً سؤال خود را وارد کنید.',
                'confidence' => 'low',
            ];
        }

        if (str_contains($q, 'حاشیه') || str_contains($q, 'سود')) {
            $margin = $context['profit_analysis']['margin_percent'] ?? null;
            $profit = $context['profit_analysis']['gross_profit'] ?? null;
            if ($margin === null) {
                return ['answer' => 'اطلاعات کافی برای پاسخ قطعی وجود ندارد.', 'confidence' => 'low'];
            }

            return [
                'answer' => 'سود ناخالص دوره: '.formatMoney((float) $profit).' ریال — حاشیه سود: '.$this->pct((float) $margin).'. منبع: SalesProfitCalculationService (فروش خالص بدون VAT منهای بهای خرید واقعی/مستر).',
                'confidence' => 'high',
            ];
        }

        if (str_contains($q, 'مطالبه') || str_contains($q, 'وصول')) {
            $rec = null;
            foreach ($context['kpis'] ?? [] as $kpi) {
                if (($kpi['key'] ?? '') === 'receivables') {
                    $rec = $kpi['value'];
                }
            }

            return [
                'answer' => $rec === null
                    ? 'اطلاعات کافی برای پاسخ قطعی وجود ندارد.'
                    : 'مانده مطالبات فعلی: '.formatMoney((float) $rec).' ریال. برای جزئیات به گزارش مطالبات مراجعه کنید.',
                'confidence' => $rec === null ? 'low' : 'medium',
            ];
        }

        if (str_contains($q, 'رشد') || str_contains($q, 'کاهش')) {
            foreach ($context['kpis'] ?? [] as $kpi) {
                if (($kpi['key'] ?? '') === 'growth') {
                    return [
                        'answer' => 'رشد فروش ماه جاری نسبت به ماه قبل: '.$this->pct((float) $kpi['value']).'.',
                        'confidence' => 'high',
                    ];
                }
            }
        }

        return [
            'answer' => 'اطلاعات کافی برای پاسخ قطعی وجود ندارد. سؤال را دقیق‌تر بپرسید یا از گزارش‌های فروش/سودآوری استفاده کنید.',
            'confidence' => 'low',
        ];
    }

    private function pct(float $value): string
    {
        return number_format($value, 1).'%';
    }
}
