<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>چاپ فاکتور {{ $invoice->number }}</title>
    @include('components.pdf-persian-font-styles', [
        'forPdf' => $forPdf ?? false,
        'pdfFontRegular' => $pdfFontRegular ?? null,
        'pdfFontBold' => $pdfFontBold ?? null,
    ])
    <style>
        @page {
            size: A4 landscape;
            margin: 5mm;
        }

        * {
            box-sizing: border-box;
            print-color-adjust: exact;
            -webkit-print-color-adjust: exact;
        }

        html,
        body {
            margin: 0;
            background: #e5e7eb;
            color: #000;
            font-family: vazirmatn, Vazirmatn, Tahoma, Arial, sans-serif;
            direction: rtl;
            text-align: right;
        }

        @if($forPdf ?? false)
        body {
            line-height: 1.65;
            word-spacing: 0.02em;
        }

        th,
        td {
            direction: rtl;
            text-align: right;
            padding: 4px 6px;
            line-height: 1.6;
        }

        th.section-title,
        .title-box,
        .items-table th {
            text-align: center;
        }

        .top-grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2mm;
        }

        .top-grid-table td {
            border: 1px solid #000;
            vertical-align: middle;
            padding: 3px 5px;
        }

        .top-grid-table .title-cell {
            text-align: center;
            font-size: 16px;
            font-weight: 900;
        }

        .meta-label {
            background: #e5e5e5;
            font-weight: 900;
            text-align: center;
            width: 18mm;
        }

        .meta-value {
            direction: ltr;
            text-align: left;
            unicode-bidi: embed;
        }

        .items-table td.num {
            direction: ltr;
            text-align: left;
            unicode-bidi: embed;
        }
        @endif

        body {
            padding: 10px;
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            max-width: 287mm;
            margin: 0 auto 10px;
        }

        .print-actions button,
        .print-actions a {
            border: 0;
            border-radius: 4px;
            background: #334155;
            color: #fff;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }

        .invoice-page {
            width: 287mm;
            min-height: 200mm;
            margin: 0 auto;
            background: #fff;
            padding: 4mm;
            border: 1px solid #000;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #000;
            padding: 2.5px 4px;
            vertical-align: middle;
            font-size: 10px;
            line-height: 1.45;
            color: #000;
        }

        th,
        .section-title,
        .label {
            background: #e5e5e5;
            font-weight: 900;
            text-align: center;
        }

        .top-grid {
            display: grid;
            grid-template-columns: 42mm 1fr 42mm;
            gap: 0;
            margin-bottom: 2mm;
        }

        .top-box {
            border: 1px solid #000;
            min-height: 14mm;
        }

        .title-box {
            display: grid;
            place-items: center;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            min-height: 14mm;
            text-align: center;
        }

        .title-box h1 {
            margin: 0;
            font-size: 16px;
            font-weight: 900;
        }

        .meta-row {
            display: grid;
            grid-template-columns: 18mm 1fr;
            min-height: 7mm;
            border-bottom: 1px solid #000;
        }

        .meta-row:last-child {
            border-bottom: 0;
        }

        .meta-row span,
        .meta-row strong {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            padding: 1px 3px;
            font-size: 10px;
        }

        .meta-row span {
            border-left: 1px solid #000;
            background: #e5e5e5;
            font-weight: 900;
        }

        .meta-row strong {
            direction: ltr;
            overflow-wrap: anywhere;
        }

        .section-title {
            padding: 3px;
            font-size: 11px;
        }

        .party-table {
            margin-bottom: 2mm;
        }

        .party-table .label {
            width: 28mm;
        }

        .party-table td {
            height: 8mm;
        }

        .items-table {
            margin-top: 1mm;
        }

        .items-table th {
            height: 14mm;
            font-size: 9.2px;
            line-height: 1.35;
        }

        .items-table td {
            height: 8.6mm;
            text-align: center;
            font-size: 9.5px;
        }

        .items-table .description {
            text-align: right;
            font-weight: 700;
        }

        .items-table .description span {
            display: block;
            margin-top: 1px;
            font-weight: 400;
            font-size: 8.6px;
        }

        .items-table tfoot th,
        .items-table tfoot td {
            height: 8mm;
            font-weight: 900;
        }

        .col-row { width: 9mm; }
        .col-code { width: 18mm; }
        .col-desc { width: 70mm; }
        .col-qty { width: 12mm; }
        .col-unit { width: 12mm; }
        .col-price { width: 25mm; }
        .col-total { width: 25mm; }
        .col-discount { width: 22mm; }
        .col-after { width: 28mm; }
        .col-tax { width: 25mm; }
        .col-final { width: 34mm; }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 78mm;
            gap: 0;
            margin-top: 2mm;
        }

        .note-table th {
            width: 28mm;
        }

        .note-table td,
        .note-table th {
            height: 8mm;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-top: 1px solid #000;
            border-left: 1px solid #000;
        }

        .signature {
            min-height: 28mm;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
        }

        .signature strong {
            display: block;
            border-bottom: 1px solid #000;
            background: #e5e5e5;
            padding: 3px;
            text-align: center;
            font-size: 10px;
        }

        .bank-note {
            margin-top: 2mm;
            border: 1px solid #000;
            padding: 3px 5px;
            text-align: center;
            font-size: 10px;
            font-weight: 800;
        }

        @media print {
            html,
            body {
                width: 297mm;
                min-width: 297mm;
                margin: 0;
                padding: 0;
                background: #fff;
            }

            body {
                padding: 0;
            }

            .print-actions {
                display: none !important;
            }

            .invoice-page {
                width: 287mm;
                min-height: 200mm;
                margin: 0 auto;
                border: 1px solid #000;
                box-shadow: none;
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body class="{{ ($forPdf ?? false) ? 'pdf-document' : '' }}">
@php
    $isSale = $invoice->direction === 'sale';
    $documentTitle = $invoice->document_type === 'proforma'
        ? 'پیش فاکتور فروش کالا و خدمات'
        : ($isSale ? 'فاکتور فروش کالا و خدمات' : 'صورتحساب خرید کالا و خدمات');

    // Official invoice layout: seller block first, buyer block second.
    // Sale: company = seller, party = buyer
    // Purchase: party = seller, company = buyer
    $seller = $isSale
        ? [
            'name' => $company?->company_name ?? config('app.name', 'ERP'),
            'economic_code' => $company?->economic_code ?: '-',
            'national_id' => $company?->national_id ?: '-',
            'address' => $company?->address ?: '-',
            'postal_code' => $company?->postal_code ?: '-',
            'phone' => $company?->phone ?: '-',
        ]
        : [
            'name' => $invoice->party?->name ?: 'طرف حساب حذف‌شده',
            'economic_code' => $invoice->party?->economic_code ?: '-',
            'national_id' => $invoice->party?->national_id ?: '-',
            'address' => $invoice->party?->address ?: '-',
            'postal_code' => $invoice->party?->postal_code ?: '-',
            'phone' => $invoice->party?->phone ?: ($invoice->party?->mobile ?: '-'),
        ];

    $buyer = $isSale
        ? [
            'name' => $invoice->party?->name ?: 'طرف حساب حذف‌شده',
            'economic_code' => $invoice->party?->economic_code ?: '-',
            'national_id' => $invoice->party?->national_id ?: '-',
            'address' => $invoice->party?->address ?: '-',
            'postal_code' => $invoice->party?->postal_code ?: '-',
            'phone' => $invoice->party?->phone ?: ($invoice->party?->mobile ?: '-'),
        ]
        : [
            'name' => $company?->company_name ?? config('app.name', 'ERP'),
            'economic_code' => $company?->economic_code ?: '-',
            'national_id' => $company?->national_id ?: '-',
            'address' => $company?->address ?: '-',
            'postal_code' => $company?->postal_code ?: '-',
            'phone' => $company?->phone ?: '-',
        ];

    $rowBaseTotal = $invoice->lines->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_price);
    $discountTotal = (float) $invoice->discount_amount;
    $taxTotal = (float) $invoice->tax_amount;
    $finalTotal = (float) $invoice->total_amount;
    $emptyRows = max(0, 8 - $invoice->lines->count());
@endphp

@unless($forPdf ?? false)
<div class="print-actions">
    @if($preview ?? false)
        <a href="#" onclick="window.close(); return false;">بستن</a>
    @else
        <a href="{{ route('invoices.show', $invoice) }}">بازگشت</a>
        <a href="{{ route('invoices.pdf', $invoice) }}" target="_blank" data-no-spa>PDF</a>
        <a href="{{ route('invoices.excel', $invoice) }}" target="_blank" data-no-spa>Excel</a>
    @endif
    <button type="button" onclick="window.print()">چاپ</button>
</div>
@endunless

<main class="invoice-page">
    @if($forPdf ?? false)
        <table class="top-grid-table">
            <tr>
                <td style="width:33%">
                    <table style="width:100%;border-collapse:collapse">
                        <tr><td class="meta-label">تاریخ:</td><td class="meta-value">{{ gregorianToJalaliDate($invoice->invoice_date) }}</td></tr>
                        <tr><td class="meta-label">وضعیت:</td><td class="meta-value">{{ $invoice->status === 'confirmed' ? 'تایید نهایی' : 'ثبت موقت' }}</td></tr>
                    </table>
                </td>
                <td class="title-cell" style="width:34%">{{ $documentTitle }}</td>
                <td style="width:33%">
                    <table style="width:100%;border-collapse:collapse">
                        <tr><td class="meta-label">شماره فاکتور:</td><td class="meta-value">{{ $invoice->number }}</td></tr>
                        <tr><td class="meta-label">پروژه:</td><td class="meta-value">{{ $invoice->project?->code ? $invoice->project->code . ' - ' . $invoice->project->name : '-' }}</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    @else
    <div class="top-grid">
        <div class="top-box">
            <div class="meta-row">
                <span>تاریخ:</span>
                <strong>{{ gregorianToJalaliDate($invoice->invoice_date) }}</strong>
            </div>
            <div class="meta-row">
                <span>وضعیت:</span>
                <strong>{{ $invoice->status === 'confirmed' ? 'تایید نهایی' : 'ثبت موقت' }}</strong>
            </div>
        </div>
        <div class="title-box">
            <h1>{{ $documentTitle }}</h1>
        </div>
        <div class="top-box">
            <div class="meta-row">
                <span>شماره فاکتور:</span>
                <strong>{{ $invoice->number }}</strong>
            </div>
            <div class="meta-row">
                <span>پروژه:</span>
                <strong>{{ $invoice->project?->code ? $invoice->project->code . ' - ' . $invoice->project->name : '-' }}</strong>
            </div>
        </div>
    </div>
    @endif

    <table class="party-table">
        <tr>
            <th class="section-title" colspan="8">مشخصات فروشنده</th>
        </tr>
        <tr>
            <th class="label">نام شخص حقیقی / حقوقی:</th>
            <td colspan="3">{{ $seller['name'] }}</td>
            <th class="label">شماره اقتصادی:</th>
            <td>{{ $seller['economic_code'] }}</td>
            <th class="label">شناسه ملی:</th>
            <td>{{ $seller['national_id'] }}</td>
        </tr>
        <tr>
            <th class="label">نشانی کامل:</th>
            <td colspan="3">{{ $seller['address'] }}</td>
            <th class="label">کد پستی:</th>
            <td>{{ $seller['postal_code'] }}</td>
            <th class="label">تلفن / همراه:</th>
            <td>{{ $seller['phone'] }}</td>
        </tr>
    </table>

    <table class="party-table">
        <tr>
            <th class="section-title" colspan="8">مشخصات خریدار</th>
        </tr>
        <tr>
            <th class="label">نام شخص حقیقی / حقوقی:</th>
            <td colspan="3">{{ $buyer['name'] }}</td>
            <th class="label">شماره اقتصادی:</th>
            <td>{{ $buyer['economic_code'] }}</td>
            <th class="label">شناسه / کد ملی:</th>
            <td>{{ $buyer['national_id'] }}</td>
        </tr>
        <tr>
            <th class="label">نشانی کامل:</th>
            <td colspan="{{ $isSale ? 5 : 3 }}">{{ $buyer['address'] }}</td>
            @unless($isSale)
                <th class="label">کد پستی:</th>
                <td>{{ $buyer['postal_code'] }}</td>
            @endunless
            <th class="label">تلفن / همراه:</th>
            <td>{{ $buyer['phone'] }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
        <tr>
            <th class="section-title" colspan="11">مشخصات کالا یا خدمات</th>
        </tr>
        <tr>
            <th class="col-row">ردیف</th>
            <th class="col-code">کد کالا</th>
            <th class="col-desc">شرح کالا یا خدمات</th>
            <th class="col-qty">تعداد / مقدار</th>
            <th class="col-unit">واحد اندازه گیری</th>
            <th class="col-price">مبلغ واحد (ریال)</th>
            <th class="col-total">مبلغ کل (ریال)</th>
            <th class="col-discount">مبلغ تخفیف (ریال)</th>
            <th class="col-after">مبلغ پس از تخفیف (ریال)</th>
            <th class="col-tax">جمع مالیات و عوارض (ریال)</th>
            <th class="col-final">جمع مبلغ کل بعلاوه مالیات و عوارض (ریال)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($invoice->lines as $line)
            @php
                $base = (float) $line->quantity * (float) $line->unit_price;
                $afterDiscount = max($base - (float) $line->discount_amount, 0);
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td>{{ $line->item->code ?: '_' }}</td>
                <td class="description">
                    {{ $line->item->name }}
                    @if($line->description)
                        <span>{{ $line->description }}</span>
                    @endif
                </td>
                <td>{{ formatQuantity((float) $line->quantity) }}</td>
                <td>{{ $line->item->unit?->name ?: '-' }}</td>
                <td>{{ formatMoney((float) $line->unit_price) }}</td>
                <td>{{ formatMoney($base) }}</td>
                <td>{{ formatMoney((float) $line->discount_amount) }}</td>
                <td>{{ formatMoney($afterDiscount) }}</td>
                <td>{{ formatMoney((float) $line->tax_amount) }}</td>
                <td>{{ formatMoney((float) $line->line_total) }}</td>
            </tr>
        @endforeach

        @for($i = 0; $i < $emptyRows; $i++)
            <tr>
                <td>{{ $invoice->lines->count() + $i + 1 }}</td>
                <td></td>
                <td class="description"></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        @endfor
        </tbody>
        <tfoot>
        <tr>
            <th colspan="2">جمع کل:</th>
            <td colspan="4" class="description">{{ persianNumberToWords($finalTotal) }} ریال</td>
            <td>{{ formatMoney($rowBaseTotal) }}</td>
            <td>{{ formatMoney($discountTotal) }}</td>
            <td>{{ formatMoney(max($rowBaseTotal - $discountTotal, 0)) }}</td>
            <td>{{ formatMoney($taxTotal) }}</td>
            <td>{{ formatMoney($finalTotal) }}</td>
        </tr>
        </tfoot>
    </table>

    <div class="bottom-grid">
        <table class="note-table">
            <tr>
                <th class="label">نحوه پرداخت:</th>
                <td>نقدی □ &nbsp;&nbsp; غیر نقدی □</td>
            </tr>
            <tr>
                <th class="label">توضیحات:</th>
                <td>{{ $invoice->description ?: 'نرخ پایه خدمات / کالا طبق توافق طرفین' }}</td>
            </tr>
        </table>

        <div class="signatures">
            <div class="signature">
                <strong>مهر و امضا فروشنده:</strong>
            </div>
            <div class="signature">
                <strong>مهر و امضا خریدار:</strong>
            </div>
        </div>
    </div>

    <div class="bank-note">
        شماره حساب / شبا:
    </div>
</main>

@unless($forPdf ?? false)
<script>
    window.addEventListener('load', () => {
        window.setTimeout(() => window.print(), 350);
    });
</script>
@endunless
</body>
</html>
