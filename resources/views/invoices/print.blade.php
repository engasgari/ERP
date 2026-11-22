<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>چاپ فاکتور {{ $print['invoice_number'] ?? $invoice->number }}</title>
    @include('components.pdf-persian-font-styles', [
        'forPdf' => $forPdf ?? false,
        'pdfFontRegular' => $pdfFontRegular ?? null,
        'pdfFontBold' => $pdfFontBold ?? null,
    ])
    <style>
        @page {
            size: A4 landscape;
            margin: 4mm;
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

        body {
            padding: 10px;
        }

        .print-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            max-width: 289mm;
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
            width: 289mm;
            min-height: 200mm;
            margin: 0 auto 8mm;
            background: #fff;
            padding: 3.5mm;
            border: 1px solid #111;
            page-break-after: always;
            break-after: page;
        }

        .invoice-page:last-of-type {
            page-break-after: auto;
            break-after: auto;
            margin-bottom: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th,
        td {
            border: 1px solid #111;
            padding: 1.5px 3px;
            vertical-align: middle;
            font-size: 9.5px;
            line-height: 1.35;
            color: #000;
        }

        .label,
        .section-title,
        .items-table thead th {
            background: #e8e8e8;
            font-weight: 900;
            text-align: center;
        }

        .header-grid {
            display: grid;
            grid-template-columns: 46mm 1fr 46mm;
            margin-bottom: 1.5mm;
        }

        .header-side {
            border: 1px solid #111;
        }

        .header-side .meta-row {
            display: grid;
            grid-template-columns: 18mm 1fr;
            min-height: 7mm;
            border-bottom: 1px solid #111;
        }

        .header-side .meta-row:last-child {
            border-bottom: 0;
        }

        .header-side .meta-row span,
        .header-side .meta-row strong {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1px 3px;
            font-size: 10px;
            min-width: 0;
        }

        .header-side .meta-row span {
            border-left: 1px solid #111;
            background: #e8e8e8;
            font-weight: 900;
        }

        .header-side .meta-row strong {
            direction: ltr;
            overflow-wrap: anywhere;
        }

        .title-box {
            border-top: 1px solid #111;
            border-bottom: 1px solid #111;
            display: grid;
            place-items: center;
            text-align: center;
            min-height: 14mm;
            padding: 2mm;
        }

        .title-box .bismillah {
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 1mm;
        }

        .title-box h1 {
            margin: 0;
            font-size: 15px;
            font-weight: 900;
        }

        .project-line {
            border: 1px solid #111;
            border-top: 0;
            padding: 2px 5px;
            font-size: 10px;
            font-weight: 700;
            margin-bottom: 1.5mm;
        }

        .party-table {
            margin-bottom: 1.5mm;
        }

        .party-table .label {
            width: 28mm;
            font-size: 9px;
        }

        .party-table td {
            height: 7.2mm;
            font-size: 9.5px;
        }

        .digit-boxes {
            display: inline-flex;
            flex-direction: row;
            direction: ltr;
            gap: 1px;
            vertical-align: middle;
        }

        .digit-box {
            width: 3.6mm;
            height: 5mm;
            border: 1px solid #111;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 800;
            line-height: 1;
        }

        .party-inline {
            display: flex;
            align-items: center;
            gap: 4px;
            flex-wrap: wrap;
        }

        .party-inline .mini-label {
            font-size: 8.5px;
            font-weight: 800;
            white-space: nowrap;
        }

        .items-table {
            margin-top: 0.5mm;
        }

        .items-table thead th {
            height: 12mm;
            font-size: 8.6px;
            line-height: 1.25;
            padding: 2px;
        }

        .items-table td {
            height: 7.8mm;
            text-align: center;
            font-size: 9px;
        }

        .items-table .description {
            text-align: right;
            font-weight: 700;
        }

        .items-table tfoot th,
        .items-table tfoot td {
            height: 7.5mm;
            font-weight: 900;
        }

        .col-row { width: 8mm; }
        .col-code { width: 16mm; }
        .col-desc { width: auto; }
        .col-qty { width: 12mm; }
        .col-unit { width: 14mm; }
        .col-price { width: 22mm; }
        .col-total { width: 22mm; }
        .col-discount { width: 18mm; }
        .col-after { width: 24mm; }
        .col-tax { width: 22mm; }
        .col-final { width: 28mm; }

        .num {
            direction: ltr;
            unicode-bidi: embed;
        }

        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 78mm;
            margin-top: 1.5mm;
        }

        .note-table th {
            width: 28mm;
        }

        .note-table td,
        .note-table th {
            height: 7.5mm;
            font-size: 9.5px;
        }

        .payment-boxes {
            display: inline-flex;
            gap: 14px;
            align-items: center;
        }

        .payment-boxes .box {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .payment-boxes .sq {
            width: 3.2mm;
            height: 3.2mm;
            border: 1px solid #111;
            display: inline-block;
        }

        .signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-top: 1px solid #111;
            border-left: 1px solid #111;
        }

        .signature {
            min-height: 26mm;
            border-right: 1px solid #111;
            border-bottom: 1px solid #111;
        }

        .signature strong {
            display: block;
            border-bottom: 1px solid #111;
            background: #e8e8e8;
            padding: 3px;
            text-align: center;
            font-size: 10px;
        }

        .bank-note {
            margin-top: 1.5mm;
            border: 1px solid #111;
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

            .print-actions {
                display: none !important;
            }

            .invoice-page {
                width: 289mm;
                min-height: auto;
                margin: 0 auto;
                border: 1px solid #111;
                box-shadow: none;
            }
        }
    </style>
</head>
<body class="{{ ($forPdf ?? false) ? 'pdf-document' : '' }}">
@php
    $print = $print ?? [];
    $seller = $print['seller'] ?? [];
    $buyer = $print['buyer'] ?? [];
    $pages = $print['pages'] ?? [];
    $totals = $print['totals'] ?? [];
    $printRoutes = $routes ?? [];
    $isPrintPreview = (bool) ($preview ?? false);
    $printShowUrl = $printPdfUrl = $printExcelUrl = null;

    if (! $isPrintPreview) {
        $printInvoiceKey = null;
        if (is_object($invoice)) {
            $printInvoiceKey = method_exists($invoice, 'getKey') ? $invoice->getKey() : null;
            $printInvoiceKey = $printInvoiceKey ?? ($invoice->id ?? null);
        } elseif (is_numeric($invoice)) {
            $printInvoiceKey = (int) $invoice;
        }

        $printShowUrl = $printRoutes['show']
            ?? ($printInvoiceKey ? route('invoices.show', $printInvoiceKey) : null);
        $printPdfUrl = $printRoutes['pdf']
            ?? ($printInvoiceKey ? route('invoices.pdf', $printInvoiceKey) : null);
        $printExcelUrl = $printRoutes['excel']
            ?? ($printInvoiceKey ? route('invoices.excel', $printInvoiceKey) : null);
    }
@endphp

@unless($forPdf ?? false)
<div class="print-actions">
    @if($isPrintPreview)
        <a href="#" onclick="window.close(); return false;">بستن</a>
    @else
        @if($printShowUrl)
            <a href="{{ $printShowUrl }}">بازگشت</a>
        @endif
        @if($printPdfUrl)
            <a href="{{ $printPdfUrl }}" target="_blank" rel="noopener" data-no-spa>PDF</a>
        @endif
        @if($printExcelUrl)
            <a href="{{ $printExcelUrl }}" target="_blank" rel="noopener" data-no-spa>Excel</a>
        @endif
    @endif
    <button type="button" onclick="window.print()">چاپ</button>
</div>
@endunless

@foreach($pages as $page)
<main class="invoice-page">
    <div class="header-grid">
        <div class="header-side">
            <div class="meta-row">
                <span>تاریخ:</span>
                <strong>{{ toPersianDigits($print['invoice_date'] ?? '') }}</strong>
            </div>
        </div>
        <div class="title-box">
            <div class="bismillah">بسمه تعالی</div>
            <h1>{{ $print['document_title'] ?? '' }}</h1>
        </div>
        <div class="header-side">
            <div class="meta-row">
                <span>شماره فاکتور:</span>
                <strong>{{ toPersianDigits($print['invoice_number'] ?? '') }}</strong>
            </div>
        </div>
    </div>

    <div class="project-line">{{ $print['project_label'] ?? 'پروژه: -' }}</div>

    @foreach([
        ['title' => 'مشخصات فروشنده', 'party' => $seller],
        ['title' => 'مشخصات خریدار', 'party' => $buyer],
    ] as $block)
        <table class="party-table">
            <tr>
                <th class="section-title" colspan="6">{{ $block['title'] }}</th>
            </tr>
            <tr>
                <th class="label">نام شخص حقیقی / حقوقی:</th>
                <td>{{ $block['party']['name'] ?? '-' }}</td>
                <th class="label">شماره اقتصادی:</th>
                <td>
                    <span class="digit-boxes">
                        @foreach(($block['party']['economic_digits'] ?? []) as $digit)
                            <span class="digit-box">{{ $digit !== '' ? toPersianDigits($digit) : '' }}</span>
                        @endforeach
                    </span>
                </td>
                <th class="label">شماره ثبت:</th>
                <td>
                    <span class="digit-boxes">
                        @foreach(($block['party']['registration_digits'] ?? []) as $digit)
                            <span class="digit-box">{{ $digit !== '' ? toPersianDigits($digit) : '' }}</span>
                        @endforeach
                    </span>
                </td>
            </tr>
            <tr>
                <th class="label">استان:</th>
                <td>{{ ($block['party']['province'] ?? '') !== '' ? $block['party']['province'] : '-' }}</td>
                <th class="label">شهرستان:</th>
                <td>{{ ($block['party']['city'] ?? '') !== '' ? $block['party']['city'] : '-' }}</td>
                <th class="label">کد پستی ده رقمی:</th>
                <td>
                    <div class="party-inline">
                        <span class="digit-boxes">
                            @foreach(($block['party']['postal_digits'] ?? []) as $digit)
                                <span class="digit-box">{{ $digit !== '' ? toPersianDigits($digit) : '' }}</span>
                            @endforeach
                        </span>
                        <span class="mini-label">شناسه ملی:</span>
                        <span class="digit-boxes">
                            @foreach(($block['party']['national_id_digits'] ?? []) as $digit)
                                <span class="digit-box">{{ $digit !== '' ? toPersianDigits($digit) : '' }}</span>
                            @endforeach
                        </span>
                    </div>
                </td>
            </tr>
            <tr>
                <th class="label">نشانی کامل:</th>
                <td colspan="3">{{ ($block['party']['address'] ?? '') !== '' ? $block['party']['address'] : '-' }}</td>
                <th class="label">تلفن / همراه:</th>
                <td>{{ ($block['party']['phone'] ?? '') !== '' ? toPersianDigits($block['party']['phone']) : '-' }}</td>
            </tr>
        </table>
    @endforeach

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
            <th class="col-after">مبلغ کل پس از تخفیف (ریال)</th>
            <th class="col-tax">جمع مالیات و عوارض (ریال)</th>
            <th class="col-final">جمع مبلغ کل بعلاوه جمع مالیات و عوارض (ریال)</th>
        </tr>
        </thead>
        <tbody>
        @foreach($page['lines'] as $line)
            <tr>
                <td class="num">{{ toPersianDigits($line['row']) }}</td>
                <td class="num">{{ toPersianDigits($line['code']) }}</td>
                <td class="description">{{ $line['description'] }}</td>
                <td class="num">{{ formatQuantity($line['quantity']) }}</td>
                <td>{{ $line['unit'] }}</td>
                <td class="num">{{ formatMoney($line['unit_price']) }}</td>
                <td class="num">{{ formatMoney($line['base']) }}</td>
                <td class="num">{{ formatMoney($line['discount']) }}</td>
                <td class="num">{{ formatMoney($line['after_discount']) }}</td>
                <td class="num">{{ formatMoney($line['tax']) }}</td>
                <td class="num">{{ formatMoney($line['total']) }}</td>
            </tr>
        @endforeach

        @for($i = 0; $i < ($page['empty_slots'] ?? 0); $i++)
            <tr>
                <td></td>
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
        @if($page['is_last'])
            <tr>
                <th colspan="2">جمع کل:</th>
                <td colspan="4" class="description">{{ $totals['final_words'] ?? '' }}</td>
                <td class="num">{{ formatMoney($totals['subtotal'] ?? 0) }}</td>
                <td class="num">{{ formatMoney($totals['discount'] ?? 0) }}</td>
                <td class="num">{{ formatMoney($totals['after_discount'] ?? 0) }}</td>
                <td class="num">{{ formatMoney($totals['tax'] ?? 0) }}</td>
                <td class="num">{{ formatMoney($totals['final'] ?? 0) }}</td>
            </tr>
        @else
            <tr>
                <th colspan="2">جمع کل:</th>
                <td colspan="9" class="description">ادامه در صفحه بعد</td>
            </tr>
        @endif
        </tfoot>
    </table>

    <div class="bottom-grid">
        <table class="note-table">
            <tr>
                <th class="label">نحوه پرداخت:</th>
                <td>
                    <span class="payment-boxes">
                        <span class="box"><span class="sq"></span> نقدی</span>
                        <span class="box"><span class="sq"></span> غیر نقدی</span>
                    </span>
                </td>
            </tr>
            <tr>
                <th class="label">توضیحات:</th>
                <td>{{ $page['is_last'] ? ($print['notes'] ?? '') : '' }}</td>
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
        {{ $print['bank_note'] ?? '' }}
    </div>
</main>
@endforeach

@unless($forPdf ?? false)
<script>
    window.addEventListener('load', () => {
        window.setTimeout(() => window.print(), 350);
    });
</script>
@endunless
</body>
</html>
