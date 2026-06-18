<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>فیش حقوقی {{ $payslip->number }}</title>
    <style>
        body{font-family:Tahoma,Arial,sans-serif;background:#f5f7fb;color:#111827;margin:0;padding:24px}
        .sheet{max-width:980px;margin:0 auto;background:#fff;border:1px solid #d1d5db;padding:28px}
        .head{display:flex;justify-content:space-between;gap:16px;border-bottom:2px solid #111827;padding-bottom:16px}
        .title{font-size:22px;font-weight:700}.muted{color:#6b7280;font-size:12px}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:18px 0}
        .box{border:1px solid #e5e7eb;padding:10px;background:#fafafa}.label{font-size:12px;color:#6b7280}.value{font-weight:700;margin-top:4px}
        table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #e5e7eb;padding:8px;text-align:right;font-size:13px}th{background:#f3f4f6}
        .cols{display:grid;grid-template-columns:1fr 1fr;gap:18px}.sum{margin-top:18px;border:2px solid #111827;padding:14px;display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
        @media print{body{background:#fff;padding:0}.sheet{border:none;max-width:none}.no-print{display:none}}
    </style>
</head>
<body>
<div class="sheet">
    <div class="head">
        <div>
            <div class="title">فیش حقوقی رسمی</div>
            <div class="muted">شماره: {{ $payslip->number }} | دوره: {{ $calculation->period->persian_title }}</div>
        </div>
        <div>
            <div class="value">{{ $companyName ?? 'شرکت' }}</div>
            <div class="muted">تاریخ صدور: {{ formatJalaliDateSafe($payslip->issued_at) }}</div>
        </div>
    </div>

    <div class="grid">
        <div class="box"><div class="label">نام پرسنل</div><div class="value">{{ $calculation->employee->full_name }}</div></div>
        <div class="box"><div class="label">کد پرسنلی</div><div class="value">{{ $calculation->employee->personnel_code ?: $calculation->employee->employee_code }}</div></div>
        <div class="box"><div class="label">کد ملی</div><div class="value">{{ $calculation->employee->national_code ?: '-' }}</div></div>
        <div class="box"><div class="label">واحد سازمانی</div><div class="value">{{ $calculation->employee->department ?: '-' }}</div></div>
        <div class="box"><div class="label">سمت</div><div class="value">{{ $calculation->employee->position ?: '-' }}</div></div>
        <div class="box"><div class="label">وضعیت فیش</div><div class="value">{{ $payslip->status === 'issued' ? 'صادر شده' : $payslip->status }}</div></div>
    </div>

    <h3>خلاصه حضور و غیاب</h3>
    <table>
        <tr>
            <th>روز کاری</th><th>روز حضور</th><th>کار عادی</th><th>اضافه‌کاری</th><th>تاخیر</th><th>تعجیل</th><th>غیبت</th><th>مرخصی</th><th>ماموریت</th><th>شب‌کاری</th><th>تعطیل‌کاری</th>
        </tr>
        <tr>
            <td>{{ $attendance->work_days }}</td><td>{{ $attendance->present_days }}</td><td>{{ $attendance->normal_hours }}</td><td>{{ $attendance->overtime_hours }}</td><td>{{ $attendance->delay_hours }}</td><td>{{ $attendance->early_leave_hours }}</td><td>{{ $attendance->absence_hours }}</td><td>{{ $attendance->leave_hours }}</td><td>{{ $attendance->mission_hours }}</td><td>{{ $attendance->night_hours }}</td><td>{{ $attendance->holiday_hours }}</td>
        </tr>
    </table>

    <div class="cols">
        <div>
            <h3>مزایا</h3>
            <table>
                <tr><th>شرح</th><th>ساعت</th><th>نرخ</th><th>مبلغ</th></tr>
                @foreach($earningLines as $line)
                    <tr><td>{{ $line->title }}</td><td>{{ $line->hours }}</td><td>{{ number_format((float) $line->rate) }}</td><td>{{ number_format((float) $line->amount) }}</td></tr>
                @endforeach
            </table>
        </div>
        <div>
            <h3>کسورات، بیمه و مالیات</h3>
            <table>
                <tr><th>شرح</th><th>ساعت</th><th>نرخ</th><th>مبلغ</th></tr>
                @foreach($deductionLines as $line)
                    <tr><td>{{ $line->title }}</td><td>{{ $line->hours }}</td><td>{{ number_format((float) $line->rate) }}</td><td>{{ number_format((float) $line->amount) }}</td></tr>
                @endforeach
            </table>
        </div>
    </div>

    <div class="sum">
        <div><div class="label">حقوق ناخالص</div><div class="value">{{ number_format((float) $calculation->gross_salary) }}</div></div>
        <div><div class="label">جمع مزایا</div><div class="value">{{ number_format((float) $calculation->total_benefits) }}</div></div>
        <div><div class="label">جمع کسورات</div><div class="value">{{ number_format((float) $calculation->total_deductions) }}</div></div>
        <div><div class="label">خالص پرداختی</div><div class="value">{{ number_format((float) $calculation->net_payable) }}</div></div>
    </div>

    <div class="no-print" style="margin-top:18px;text-align:left">
        <button onclick="window.print()">چاپ فیش</button>
    </div>
</div>
</body>
</html>
