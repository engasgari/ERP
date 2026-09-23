<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>فرم حکم کارگزینی {{ $order->number }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/fonts/vazirmatn/vazirmatn-font-face.css') }}">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #e5e7eb;
            color: #111827;
            font-family: Vazirmatn, Tahoma, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.65;
        }
        .toolbar {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 16px;
        }
        .toolbar a,
        .toolbar button {
            border: 0;
            border-radius: 6px;
            padding: 8px 14px;
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
        }
        .toolbar button { background: #2563eb; }
        .toolbar a { background: #4b5563; }
        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 24px;
            background: #fff;
            padding: 12mm;
            box-shadow: 0 18px 44px rgba(15, 23, 42, .16);
        }
        .header {
            display: grid;
            grid-template-columns: 1.2fr 1.6fr 1.2fr;
            gap: 10px;
            border: 2px solid #111827;
            padding: 10px;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            text-align: center;
            font-size: 20px;
            font-weight: 900;
        }
        .muted { color: #4b5563; font-size: 11px; }
        .meta { font-weight: 700; }
        .section-title {
            margin: 12px 0 6px;
            padding: 4px 8px;
            background: #f3f4f6;
            border: 1px solid #9ca3af;
            font-weight: 900;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #9ca3af;
            padding: 5px 6px;
            text-align: right;
            vertical-align: top;
        }
        th { background: #f9fafb; font-weight: 900; }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .grid-2 table { height: 100%; }
        .totals {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 10px;
        }
        .total-box {
            border: 1px solid #111827;
            padding: 8px;
        }
        .total-box .label { color: #4b5563; font-size: 11px; font-weight: 700; }
        .total-box .value { margin-top: 3px; font-size: 14px; font-weight: 900; }
        .signs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 28px;
            text-align: center;
        }
        .sign-box {
            border-top: 1px solid #111827;
            padding-top: 8px;
            min-height: 70px;
            font-weight: 700;
        }
        .ltr { direction: ltr; text-align: left; unicode-bidi: plaintext; }
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            body { background: #fff; }
            .toolbar { display: none !important; }
            .page {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
@php
    $companyName = $company?->company_name ?: config('app.name', 'شرکت');
    $employee = $order->employee;
@endphp

<div class="toolbar">
    <button type="button" onclick="window.print()">چاپ فرم حکم</button>
    <a href="{{ $backRoute }}">بازگشت به گزارش</a>
</div>

<main class="page">
    <header class="header">
        <div>
            <div class="meta">{{ $companyName }}</div>
            <div class="muted">شناسه ملی: {{ $company?->national_id ?: '-' }}</div>
            <div class="muted">کد اقتصادی: {{ $company?->economic_code ?: '-' }}</div>
        </div>
        <div>
            <h1>حکم کارگزینی</h1>
            <div class="muted" style="text-align:center">فرم استاندارد منابع انسانی / قانون کار</div>
        </div>
        <div>
            <div class="meta">شماره حکم: <span class="ltr">{{ $order->number }}</span></div>
            <div class="muted">تاریخ صدور: {{ formatJalaliDateSafe($order->issue_date ?: $order->effective_date) }}</div>
            <div class="muted">تاریخ اجرا: {{ formatJalaliDateSafe($order->effective_date) }}</div>
            <div class="muted">وضعیت: {{ $order->status === 'approved' ? 'تایید شده' : 'پیش‌نویس' }}</div>
        </div>
    </header>

    <div class="section-title">۱) مشخصات پرسنل و حکم</div>
    <table>
        <tr>
            <th style="width:18%">نام و نام خانوادگی</th>
            <td>{{ $employee?->full_name ?: '-' }}</td>
            <th style="width:14%">کد پرسنلی</th>
            <td class="ltr">{{ $employee?->personnel_code ?: ($employee?->employee_code ?: '-') }}</td>
            <th style="width:12%">کد ملی</th>
            <td class="ltr">{{ $employee?->national_code ?: '-' }}</td>
        </tr>
        <tr>
            <th>شماره بیمه</th>
            <td class="ltr">{{ $employee?->insurance_number ?: '-' }}</td>
            <th>نوع حکم</th>
            <td>{{ $order->orderTypeLabel() }}</td>
            <th>نوع استخدام</th>
            <td>{{ $order->employmentTypeLabel() }}</td>
        </tr>
        <tr>
            <th>وضعیت بیمه</th>
            <td>{{ $order->insuranceStatusLabel() }}</td>
            <th>وضعیت تاهل</th>
            <td>{{ \App\Support\Hr\IranLaborEmploymentOrderCatalog::maritalStatuses()[$order->marital_status] ?? ($order->marital_status ?: '-') }}</td>
            <th>تعداد اولاد</th>
            <td class="ltr">{{ (int) $order->children_count }}</td>
        </tr>
        <tr>
            <th>تاریخ پایان اثر</th>
            <td>{{ formatJalaliDateSafe($order->end_date, '-') }}</td>
            <th>کد کارگاه</th>
            <td class="ltr">{{ $order->workshop_code ?: '-' }}</td>
            <th>مرکز هزینه</th>
            <td>{{ $order->cost_center_code ?: '-' }}</td>
        </tr>
        <tr>
            <th>علت / شرح حکم</th>
            <td colspan="5">{{ $order->decree_reason ?: ($order->notes ?: '-') }}</td>
        </tr>
    </table>

    <div class="section-title">۲) شغل، پست و محل خدمت (طرح طبقه‌بندی مشاغل)</div>
    <table>
        <tr>
            <th style="width:18%">عنوان شغل</th>
            <td>{{ $order->job?->title ?: '-' }}</td>
            <th style="width:14%">پست سازمانی</th>
            <td>{{ $order->position?->title ?: '-' }}</td>
            <th style="width:14%">محل خدمت</th>
            <td>{{ $order->organizationUnit?->title ?: '-' }}</td>
        </tr>
        <tr>
            <th>گروه</th>
            <td class="ltr">{{ $order->job_group ?: '-' }}</td>
            <th>رتبه</th>
            <td class="ltr">{{ $order->job_rank ?: '-' }}</td>
            <th>پایه</th>
            <td class="ltr">{{ $order->job_base ?: '-' }}</td>
        </tr>
        <tr>
            <th>ساعت کار ماهانه</th>
            <td class="ltr">{{ rtrim(rtrim(number_format((float) $order->monthly_work_hours, 2, '.', ''), '0'), '.') ?: '220' }}</td>
            <th>ساعت کار روزانه</th>
            <td class="ltr">{{ rtrim(rtrim(number_format((float) $order->daily_work_hours, 2, '.', ''), '0'), '.') ?: '7.33' }}</td>
            <th>مزد روزانه</th>
            <td class="ltr">{{ formatMoney((float) $order->daily_wage) }}</td>
        </tr>
    </table>

    <div class="section-title">۳) اقلام مزد و مزایا</div>
    <div class="grid-2">
        <table>
            <thead>
                <tr>
                    <th>شرح قلم مشمول بیمه</th>
                    <th style="width:32%">مبلغ (ریال)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($wageComponents as $component)
                    @continue(! $component['insurable'])
                    @php $amount = (float) ($order->{$component['field']} ?? 0); @endphp
                    @if($amount > 0)
                        <tr>
                            <td>{{ $component['title'] }}</td>
                            <td class="ltr">{{ formatMoney($amount) }}</td>
                        </tr>
                    @endif
                @endforeach
                @if($order->totalInsurableWage() <= 0)
                    <tr><td colspan="2" style="text-align:center">قلم مشمولی ثبت نشده است.</td></tr>
                @endif
            </tbody>
        </table>
        <table>
            <thead>
                <tr>
                    <th>شرح قلم غیرمشمول بیمه</th>
                    <th style="width:32%">مبلغ (ریال)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($wageComponents as $component)
                    @continue($component['insurable'])
                    @php $amount = (float) ($order->{$component['field']} ?? 0); @endphp
                    @if($amount > 0)
                        <tr>
                            <td>{{ $component['title'] }}</td>
                            <td class="ltr">{{ formatMoney($amount) }}</td>
                        </tr>
                    @endif
                @endforeach
                @if($order->totalNonInsurableBenefits() <= 0)
                    <tr><td colspan="2" style="text-align:center">قلم غیرمشمولی ثبت نشده است.</td></tr>
                @endif
            </tbody>
        </table>
    </div>

    <div class="totals">
        <div class="total-box">
            <div class="label">جمع مشمول بیمه</div>
            <div class="value ltr">{{ formatMoney($order->totalInsurableWage()) }}</div>
        </div>
        <div class="total-box">
            <div class="label">جمع غیرمشمول بیمه</div>
            <div class="value ltr">{{ formatMoney($order->totalNonInsurableBenefits()) }}</div>
        </div>
        <div class="total-box">
            <div class="label">جمع کل مزد و مزایا</div>
            <div class="value ltr">{{ formatMoney($order->totalBenefits()) }}</div>
        </div>
    </div>

    <div class="signs">
        <div class="sign-box">تهیه‌کننده<br><span class="muted">نام و امضا</span></div>
        <div class="sign-box">تایید منابع انسانی<br><span class="muted">نام و امضا</span></div>
        <div class="sign-box">ابلاغ به کارمند<br><span class="muted">نام و امضا</span></div>
    </div>
</main>
</body>
</html>
