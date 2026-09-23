<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>فیش حقوقی {{ toPersianDigits($payslip->number) }}</title>
    @include('components.pdf-persian-font-styles', [
        'forPdf' => $forPdf ?? false,
        'pdfFontRegular' => $pdfFontRegular ?? null,
        'pdfFontBold' => $pdfFontBold ?? null,
    ])
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: vazirmatn, Vazirmatn, Tahoma, Arial, sans-serif;
            background: #e5e7eb;
            color: #111;
            margin: 0;
            padding: 16px;
            font-size: 12px;
            direction: rtl;
            text-align: right;
            line-height: 1.65;
        }
        .sheet {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #111;
            padding: 14px 16px 18px;
        }
        .head {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1.2fr;
            gap: 8px;
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .head .center { text-align: center; }
        .title { font-size: 18px; font-weight: 800; margin: 0; }
        .sub { color: #333; font-size: 11px; margin-top: 4px; }
        .meta-table, .info-table, .att-table, .money-table, .sum-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td, .info-table td, .att-table th, .att-table td,
        .money-table th, .money-table td, .sum-table th, .sum-table td {
            border: 1px solid #111;
            padding: 6px 8px;
            vertical-align: middle;
            line-height: 1.6;
            text-align: right;
        }

        .att-table th, .money-table th, .sum-table th, .section-title {
            text-align: center;
        }

        @if($forPdf ?? false)
        .head {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .head > div {
            display: table-cell;
            vertical-align: middle;
            padding: 0 6px;
        }

        .cols {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .cols > div {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .signs {
            display: table;
            width: 100%;
            table-layout: fixed;
        }

        .signs > div {
            display: table-cell;
            width: 33.33%;
            vertical-align: bottom;
            padding: 0 8px;
        }
        @endif
        .label { background: #f3f4f6; font-weight: 600; width: 22%; white-space: nowrap; }
        .section-title {
            background: #111;
            color: #fff;
            text-align: center;
            font-weight: 700;
            padding: 6px;
            margin: 12px 0 0;
        }
        .cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            margin-top: 0;
        }
        .cols > div:first-child .money-table { border-left: 0; }
        .money-table th { background: #f3f4f6; }
        .money-table td.num, .att-table td.num, .sum-table td.num { text-align: left; direction: ltr; font-variant-numeric: tabular-nums; }
        .sum-table th { background: #f3f4f6; }
        .net-row td { font-size: 14px; font-weight: 800; background: #fffbeb; }
        .words { margin-top: 10px; border: 1px solid #111; padding: 8px; }
        .signs {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 24px;
            margin-top: 28px;
            text-align: center;
        }
        .signs .line { border-top: 1px solid #111; margin-top: 42px; padding-top: 6px; }
        .toolbar { margin: 0 auto 12px; max-width: 210mm; text-align: left; }
        .toolbar button, .toolbar a {
            display: inline-block;
            padding: 8px 14px;
            background: #111;
            color: #fff;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            font-family: inherit;
            margin-left: 6px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .sheet { border: 0; max-width: none; }
            .toolbar { display: none !important; }
        }
    </style>
</head>
<body class="{{ ($forPdf ?? false) ? 'pdf-document' : '' }}">
@php
    $att = $attendance;
    $showDelay = $att && (float) ($att->delay_hours ?? 0) > 0;
    $showEarly = $att && (float) ($att->early_leave_hours ?? 0) > 0;
    $showAbsence = $att && (float) ($att->absence_hours ?? 0) > 0;
    $showLeave = $att && (float) ($att->leave_hours ?? 0) > 0;
    $showMission = $att && (float) ($att->mission_hours ?? 0) > 0;
@endphp

@unless($forPdf ?? false)
<div class="toolbar">
    <button type="button" onclick="window.print()">چاپ</button>
    <a href="{{ route('payslips.download', $payslip) }}">دانلود PDF</a>
</div>
@endunless

<div class="sheet">
    <div class="head">
        <div>
            <div style="font-weight:800;font-size:14px">{{ $companyName }}</div>
            @if(!empty($companyNationalId))
                <div class="sub">شناسه/کد اقتصادی: {{ toPersianDigits($companyNationalId) }}</div>
            @endif
        </div>
        <div class="center">
            <h1 class="title">فیش حقوق و دستمزد</h1>
            <div class="sub">دوره {{ $header['period_title'] }}</div>
        </div>
        <div style="text-align:left">
            <div>شماره فیش: <strong dir="ltr">{{ toPersianDigits($payslip->number) }}</strong></div>
            <div class="sub">تاریخ صدور: {{ formatJalaliDateSafe($payslip->issued_at) }}</div>
            @if(!empty($header['employment_order_number']))
                <div class="sub">حکم مبنا: {{ toPersianDigits($header['employment_order_number']) }}</div>
            @endif
        </div>
    </div>

    <table class="info-table">
        <tr>
            <td class="label">نام و نام خانوادگی</td>
            <td>{{ $header['full_name'] }}</td>
            <td class="label">کد پرسنلی</td>
            <td dir="ltr">{{ toPersianDigits($header['personnel_code']) }}</td>
        </tr>
        <tr>
            <td class="label">کد ملی</td>
            <td dir="ltr">{{ toPersianDigits($header['national_code']) }}</td>
            <td class="label">شماره بیمه</td>
            <td dir="ltr">{{ toPersianDigits($header['insurance_number'] ?: '-') }}</td>
        </tr>
        <tr>
            <td class="label">واحد سازمانی</td>
            <td>{{ $header['organization_unit'] }}</td>
            <td class="label">سمت</td>
            <td>{{ $header['position'] }}</td>
        </tr>
        @if(!empty($header['bank_account_number']))
            <tr>
                <td class="label">شماره حساب</td>
                <td colspan="3" dir="ltr">{{ toPersianDigits($header['bank_account_number']) }}</td>
            </tr>
        @endif
    </table>

    @if($att)
        <div class="section-title">خلاصه کارکرد ماهانه</div>
        <table class="att-table">
            <thead>
            <tr>
                <th>روز کاری</th>
                <th>روز حضور</th>
                <th>ساعات کارکرد</th>
                <th>اضافه‌کاری</th>
                @if($showDelay)<th>تأخیر</th>@endif
                @if($showEarly)<th>تعجیل</th>@endif
                @if($showAbsence)<th>غیبت</th>@endif
                @if($showLeave)<th>مرخصی</th>@endif
                @if($showMission)<th>ماموریت</th>@endif
            </tr>
            </thead>
            <tbody>
            <tr>
                <td class="num">{{ formatMoney((float) ($att->work_days ?? $att->working_days ?? 0), 0) }}</td>
                <td class="num">{{ formatMoney((float) ($att->present_days ?? 0), 0) }}</td>
                <td class="num">{{ formatMoney((float) ($att->normal_hours ?? $att->worked_hours ?? $att->net_payable_hours ?? 0), 2) }}</td>
                <td class="num">{{ formatMoney((float) ($att->overtime_hours ?? 0), 2) }}</td>
                @if($showDelay)<td class="num">{{ formatMoney((float) $att->delay_hours, 2) }}</td>@endif
                @if($showEarly)<td class="num">{{ formatMoney((float) $att->early_leave_hours, 2) }}</td>@endif
                @if($showAbsence)<td class="num">{{ formatMoney((float) $att->absence_hours, 2) }}</td>@endif
                @if($showLeave)<td class="num">{{ formatMoney((float) $att->leave_hours, 2) }}</td>@endif
                @if($showMission)<td class="num">{{ formatMoney((float) $att->mission_hours, 2) }}</td>@endif
            </tr>
            </tbody>
        </table>
    @endif

    <div class="cols">
        <div>
            <div class="section-title">حقوق و مزایا</div>
            <table class="money-table">
                <thead>
                <tr>
                    <th>شرح</th>
                    <th style="width:28%">مبلغ (ریال)</th>
                </tr>
                </thead>
                <tbody>
                @forelse($earningLines as $line)
                    <tr>
                        <td>{{ $line->title }}</td>
                        <td class="num">{{ formatMoney((float) $line->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" style="text-align:center">-</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div>
            <div class="section-title">کسورات</div>
            <table class="money-table">
                <thead>
                <tr>
                    <th>شرح</th>
                    <th style="width:28%">مبلغ (ریال)</th>
                </tr>
                </thead>
                <tbody>
                @forelse($deductionLines as $line)
                    <tr>
                        <td>{{ $line->title }}</td>
                        <td class="num">{{ formatMoney((float) $line->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" style="text-align:center">-</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @php
        $snapshot = is_array($payslip->snapshot) ? $payslip->snapshot : [];
        $insuranceBase = (float) ($calculation->insurance_base ?? $snapshot['insurance_base'] ?? 0);
        $taxBase = (float) ($calculation->tax_base ?? $snapshot['tax_base'] ?? 0);
        $nonInsurance = (float) ($snapshot['non_insurance_earnings'] ?? max(0, (float) ($calculation->gross_salary ?? 0) - $insuranceBase));
        $nonTax = (float) ($snapshot['non_tax_earnings'] ?? max(0, (float) ($calculation->gross_salary ?? 0) - $taxBase));
        $insuranceEmployer = (float) ($calculation->insurance_employer ?? $snapshot['insurance_employer'] ?? 0);
    @endphp

    <div class="section-title">مبنای محاسبات</div>
    <table class="sum-table" style="margin-top:4px">
        <tr>
            <th>مبلغ مشمول بیمه</th>
            <td class="num">{{ formatMoney($insuranceBase) }}</td>
            <th>مبلغ غیرمشمول بیمه</th>
            <td class="num">{{ formatMoney($nonInsurance) }}</td>
        </tr>
        <tr>
            <th>مبلغ مشمول مالیات</th>
            <td class="num">{{ formatMoney($taxBase) }}</td>
            <th>مبلغ غیرمشمول مالیات</th>
            <td class="num">{{ formatMoney($nonTax) }}</td>
        </tr>
        <tr>
            <th>بیمه سهم کارفرما</th>
            <td class="num">{{ formatMoney($insuranceEmployer) }}</td>
            <th>بیمه سهم کارمند (کسورات)</th>
            <td class="num">{{ formatMoney((float) ($calculation->insurance_employee ?? 0)) }}</td>
        </tr>
    </table>

    <table class="sum-table" style="margin-top:12px">
        <tr>
            <th>جمع ناخالص دریافتی</th>
            <td class="num">{{ formatMoney((float) ($calculation->gross_salary ?? 0)) }}</td>
            <th>جمع کسورات</th>
            <td class="num">{{ formatMoney((float) ($calculation->total_deductions ?? 0)) }}</td>
        </tr>
        <tr class="net-row">
            <th colspan="2">خالص پرداختی (ریال)</th>
            <td class="num" colspan="2">{{ formatMoney((float) ($calculation->net_payable ?? 0)) }}</td>
        </tr>
    </table>

    <div class="words">
        مبلغ به حروف:
        <strong>{{ persianNumberToWords((float) ($calculation->net_payable ?? 0)) }} ریال</strong>
    </div>

    <div class="signs">
        <div><div class="line">امضای کارگزینی</div></div>
        <div><div class="line">امضای امور مالی</div></div>
        <div><div class="line">امضای دریافت‌کننده</div></div>
    </div>
</div>
</body>
</html>
