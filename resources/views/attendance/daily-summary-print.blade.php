@php
    $fmtMinutes = static function (int $minutes): string {
        if ($minutes <= 0) {
            return '—';
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return toPersianDigits($hours) . ':' . toPersianDigits(str_pad((string) $mins, 2, '0', STR_PAD_LEFT));
        }

        if ($hours > 0) {
            return toPersianDigits($hours) . 'س';
        }

        return toPersianDigits($mins) . 'د';
    };

    $unitName = $employee->organizationUnit?->title
        ?: ($employee->department ?: '—');
    $positionName = $employee->positionRecord?->title
        ?: ($employee->position ?: '—');
@endphp

<x-erp.ui.print-layout
    title="فرم گزارش تردد روزانه — {{ $employee->full_name }}"
    :back-url="$backUrl"
>
    <style>
        .att-print-head{display:grid;grid-template-columns:1.1fr 1.2fr 1.1fr;gap:8px;align-items:center;border-bottom:2px solid #111;padding-bottom:8px;margin-bottom:10px}
        .att-print-head .center{text-align:center}
        .att-print-title{margin:0;font-size:16px;font-weight:800}
        .att-print-sub{margin-top:3px;font-size:11px;color:#334155}
        .att-print-meta{width:100%;border-collapse:collapse;margin-bottom:8px}
        .att-print-meta td{border:1px solid #111;padding:4px 6px;font-size:11px}
        .att-print-meta .lbl{width:18%;background:#f3f4f6;font-weight:800}
        .att-print-table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:9.5px}
        .att-print-table th,.att-print-table td{border:1px solid #111;padding:2px 3px;text-align:center;vertical-align:middle;line-height:1.35}
        .att-print-table th{background:#f3f4f6;font-weight:800}
        .att-print-table td.date,.att-print-table td.status{text-align:right}
        .att-print-table tfoot td{font-weight:800;background:#f8fafc}
        .att-print-summary{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin:8px 0 12px}
        .att-print-summary .box{border:1px solid #111;padding:5px 6px;text-align:center}
        .att-print-summary .box .k{font-size:10px;color:#475569}
        .att-print-summary .box .v{font-size:12px;font-weight:800;margin-top:2px}
        .att-print-signs{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px}
        .att-print-signs .sign{border:1px solid #111;min-height:72px;padding:8px;text-align:center}
        .att-print-signs .sign strong{display:block;margin-bottom:28px;font-size:11px}
        .att-print-signs .sign span{font-size:10px;color:#64748b}
        .att-print-note{margin-top:8px;font-size:10px;color:#475569}
        @media print{
            .att-print-table{font-size:9px}
            .att-print-table th,.att-print-table td{padding:1.5px 2px}
            .att-print-signs{page-break-inside:avoid}
        }
    </style>

    <div class="att-print-head">
        <div>
            <div class="att-print-sub">{{ $company?->company_name ?: config('app.name', 'ERP') }}</div>
            <div class="att-print-sub">فرم تأیید کارکرد روزانه</div>
        </div>
        <div class="center">
            <h1 class="att-print-title">گزارش تردد روزانه</h1>
            <div class="att-print-sub">{{ $monthLabel }} {{ toPersianDigits($year) }}</div>
        </div>
        <div style="text-align:left">
            <div class="att-print-sub">تاریخ چاپ: {{ toPersianDigits(gregorianToJalaliDate(now())) }}</div>
        </div>
    </div>

    <table class="att-print-meta">
        <tr>
            <td class="lbl">نام کارمند</td>
            <td>{{ $employee->full_name }}</td>
            <td class="lbl">کد پرسنلی</td>
            <td>{{ toPersianDigits($employee->personnel_code ?: '—') }}</td>
        </tr>
        <tr>
            <td class="lbl">واحد سازمانی</td>
            <td>{{ $unitName }}</td>
            <td class="lbl">سمت</td>
            <td>{{ $positionName }}</td>
        </tr>
    </table>

    <div class="att-print-summary">
        <div class="box">
            <div class="k">روزهای حضور</div>
            <div class="v">{{ toPersianDigits($totals['present_days']) }}</div>
        </div>
        <div class="box">
            <div class="k">روزهای غیبت</div>
            <div class="v">{{ toPersianDigits($totals['absence_days']) }}</div>
        </div>
        <div class="box">
            <div class="k">روزهای تعطیل</div>
            <div class="v">{{ toPersianDigits($totals['off_days']) }}</div>
        </div>
        <div class="box">
            <div class="k">تردد ناقص</div>
            <div class="v">{{ toPersianDigits($totals['incomplete_days']) }}</div>
        </div>
    </div>

    <table class="att-print-table">
        <thead>
            <tr>
                <th style="width:9%">تاریخ</th>
                <th style="width:7%">روز</th>
                <th style="width:10%">نوع</th>
                <th style="width:7%">ورود</th>
                <th style="width:7%">خروج</th>
                <th style="width:8%">کارکرد</th>
                <th style="width:7%">تأخیر</th>
                <th style="width:7%">تعجیل</th>
                <th style="width:8%">مرخصی</th>
                <th style="width:8%">ماموریت</th>
                <th style="width:22%">وضعیت</th>
            </tr>
        </thead>
        <tbody>
            @forelse($days as $day)
                <tr>
                    <td class="date">{{ toPersianDigits($day['work_date_jalali']) }}</td>
                    <td>{{ $day['day_name'] }}</td>
                    <td>{{ $day['day_type'] }}</td>
                    <td dir="ltr">{{ $day['start_display'] }}</td>
                    <td dir="ltr">{{ $day['end_display'] }}</td>
                    <td>{{ $fmtMinutes((int) $day['worked_minutes']) }}</td>
                    <td>{{ $fmtMinutes((int) $day['delay_minutes']) }}</td>
                    <td>{{ $fmtMinutes((int) $day['early_leave_minutes']) }}</td>
                    <td>{{ $fmtMinutes((int) $day['leave_minutes']) }}</td>
                    <td>{{ $fmtMinutes((int) $day['mission_minutes']) }}</td>
                    <td class="status">{{ $day['status_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11">برای این ماه ردیفی یافت نشد.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">جمع ماه</td>
                <td>{{ $fmtMinutes((int) $totals['worked_minutes']) }}</td>
                <td>{{ $fmtMinutes((int) $totals['delay_minutes']) }}</td>
                <td>{{ $fmtMinutes((int) $totals['early_leave_minutes']) }}</td>
                <td>{{ $fmtMinutes((int) $totals['leave_minutes']) }}</td>
                <td>{{ $fmtMinutes((int) $totals['mission_minutes']) }}</td>
                <td>—</td>
            </tr>
        </tfoot>
    </table>

    <p class="att-print-note">
        این فرم برای تأیید و امضای کارکرد روزانه کارمند در ماه انتخاب‌شده تهیه شده است.
        مقادیر زمانی به صورت ساعت:دقیقه (یا دقیقه) نمایش داده می‌شوند.
    </p>

    <div class="att-print-signs">
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
