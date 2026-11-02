@php
    $unitName = $employee?->organizationUnit?->title ?: ($employee?->department ?: '—');
    $positionName = $employee?->positionRecord?->title ?: ($employee?->position ?: '—');
    $statusLabel = match ($mission->status) {
        'pending' => 'در انتظار تایید',
        'approved' => 'تایید شده',
        'rejected' => 'رد شده',
        'cancelled' => 'لغو شده',
        default => $mission->status ?: '—',
    };
    $isDaily = $mission->request_type === 'daily';
    $durationLabel = $isDaily
        ? toPersianDigits(rtrim(rtrim(number_format((float) $mission->total_days, 2, '.', ''), '0'), '.')) . ' روز'
        : toPersianDigits(rtrim(rtrim(number_format(((int) $mission->duration_minutes ?: ((float) $mission->hours * 60)) / 60, 2, '.', ''), '0'), '.')) . ' ساعت';
@endphp

<x-erp.ui.print-layout
    title="فرم درخواست ماموریت — {{ $employee?->full_name }}"
    :back-url="$backUrl"
    size="a5"
>
    <style>
        .req-head{display:grid;grid-template-columns:1fr 1.2fr 1fr;gap:4px;align-items:center;border-bottom:1.5px solid #111;padding-bottom:6px;margin-bottom:8px}
        .req-head .center{text-align:center}
        .req-title{margin:0;font-size:14px;font-weight:800}
        .req-sub{margin-top:2px;font-size:9px;color:#334155}
        .req-meta{width:100%;border-collapse:collapse;margin-bottom:6px}
        .req-meta td{border:1px solid #111;padding:4px 5px;font-size:10px;vertical-align:middle}
        .req-meta .lbl{width:20%;background:#f3f4f6;font-weight:800}
        .req-box{border:1px solid #111;padding:6px;margin:6px 0 8px;min-height:42px}
        .req-box .k{font-size:9px;font-weight:800;margin-bottom:4px}
        .req-box .v{font-size:10px;line-height:1.55;white-space:pre-wrap}
        .req-signs{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:14px}
        .req-signs .sign{border:1px solid #111;min-height:58px;padding:6px;text-align:center}
        .req-signs .sign strong{display:block;margin-bottom:22px;font-size:10px}
        .req-signs .sign span{font-size:8px;color:#64748b}
        .req-note{margin-top:6px;font-size:8px;color:#475569;line-height:1.5}
        @media print{.req-signs{page-break-inside:avoid}}
    </style>

    <div class="req-head">
        <div>
            <div class="req-sub">{{ $company?->company_name ?: config('app.name', 'ERP') }}</div>
            <div class="req-sub">واحد منابع انسانی</div>
        </div>
        <div class="center">
            <h1 class="req-title">فرم درخواست ماموریت</h1>
            <div class="req-sub">شماره درخواست: {{ toPersianDigits($mission->id) }}</div>
        </div>
        <div style="text-align:left">
            <div class="req-sub">تاریخ چاپ: {{ toPersianDigits(gregorianToJalaliDate(now())) }}</div>
            <div class="req-sub">وضعیت: {{ $statusLabel }}</div>
        </div>
    </div>

    <table class="req-meta">
        <tr>
            <td class="lbl">نام کارمند</td>
            <td>{{ $employee?->full_name ?: '—' }}</td>
            <td class="lbl">کد پرسنلی</td>
            <td>{{ toPersianDigits($employee?->personnel_code ?: '—') }}</td>
        </tr>
        <tr>
            <td class="lbl">واحد سازمانی</td>
            <td>{{ $unitName }}</td>
            <td class="lbl">سمت</td>
            <td>{{ $positionName }}</td>
        </tr>
        <tr>
            <td class="lbl">نوع درخواست</td>
            <td>{{ $isDaily ? 'روزانه' : 'ساعتی' }}</td>
            <td class="lbl">مقصد</td>
            <td>{{ $mission->destination ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">از تاریخ</td>
            <td>{{ toPersianDigits(formatJalaliDateSafe($mission->start_date ?: $mission->mission_date)) }}</td>
            <td class="lbl">تا تاریخ</td>
            <td>{{ toPersianDigits(formatJalaliDateSafe($mission->end_date ?: $mission->mission_date)) }}</td>
        </tr>
        <tr>
            <td class="lbl">ساعت شروع</td>
            <td dir="ltr">{{ $mission->start_time ? toPersianDigits(substr((string) $mission->start_time, 0, 5)) : '—' }}</td>
            <td class="lbl">ساعت پایان</td>
            <td dir="ltr">{{ $mission->end_time ? toPersianDigits(substr((string) $mission->end_time, 0, 5)) : '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">مدت</td>
            <td>{{ $durationLabel }}</td>
            <td class="lbl">ثبت‌کننده</td>
            <td>{{ $mission->requestedBy?->name ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">پروژه</td>
            <td>{{ $mission->project?->name ?: '—' }}</td>
            <td class="lbl">مرکز هزینه</td>
            <td>{{ $mission->costCenter?->name ?: '—' }}</td>
        </tr>
    </table>

    <div class="req-box">
        <div class="k">شرح ماموریت</div>
        <div class="v">{{ $mission->description ?: '—' }}</div>
    </div>

    @if($mission->status === 'rejected' && filled($mission->rejection_reason))
        <div class="req-box">
            <div class="k">علت رد</div>
            <div class="v">{{ $mission->rejection_reason }}</div>
        </div>
    @endif

    <p class="req-note">
        این فرم برای اخذ امضای درخواست ماموریت صادر شده است. پس از امضا، درخواست در سیستم قابل پیگیری و تایید خواهد بود.
        @if($mission->approved_at)
            تاریخ اقدام سیستمی: {{ toPersianDigits(gregorianToJalaliDate($mission->approved_at)) }}
            @if($mission->approvedBy) — {{ $mission->approvedBy->name }} @endif
        @endif
    </p>

    <div class="req-signs">
        <div class="sign">
            <strong>امضای کارمند</strong>
            <span>نام و تاریخ</span>
        </div>
        <div class="sign">
            <strong>امضای مدیر واحد</strong>
            <span>نام و تاریخ</span>
        </div>
        <div class="sign">
            <strong>امضای مدیر منابع انسانی</strong>
            <span>نام و تاریخ</span>
        </div>
    </div>
</x-erp.ui.print-layout>
