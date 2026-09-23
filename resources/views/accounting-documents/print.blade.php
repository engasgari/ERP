<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>چاپ سند حسابداری {{ $document->number }}</title>
    @include('components.pdf-persian-font-styles', [
        'forPdf' => $forPdf ?? false,
        'pdfFontRegular' => $pdfFontRegular ?? null,
        'pdfFontBold' => $pdfFontBold ?? null,
    ])
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        * { box-sizing: border-box; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        body { margin: 0; font-family: vazirmatn, Vazirmatn, Tahoma, Arial, sans-serif; background: #f3f4f6; color: #111827; direction: rtl; text-align: right; line-height: 1.65; }
        .sheet { width: 285mm; min-height: 190mm; margin: 0 auto; background: #fff; border: 1px solid #111827; padding: 6mm; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; margin: 0 auto 8px; width: 285mm; }
        .actions a, .actions button { border: 0; border-radius: 4px; background: #334155; color: #fff; padding: 7px 12px; font-size: 12px; font-weight: 700; text-decoration: none; cursor: pointer; }
        h1 { margin: 0; font-size: 18px; }
        .meta { margin-top: 4px; font-size: 12px; color: #475569; line-height: 1.7; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; direction: rtl; }
        th, td { border: 1px solid #111827; padding: 5px 8px; font-size: 11px; vertical-align: top; line-height: 1.6; text-align: right; }
        th { background: #e5e7eb; font-weight: 800; text-align: center; }
        td.pdf-num { direction: ltr; text-align: left; unicode-bidi: embed; white-space: nowrap; }
        .summary-table { margin-top: 10px; }
        .summary-table td { width: 25%; }
        .summary-table .summary-label { background: #f8fafc; color: #475569; font-size: 11px; font-weight: 700; }
        .summary-table .summary-value { font-size: 14px; font-weight: 800; }
        .bank-tag { font-size: 11px; color: #0f172a; margin-top: 4px; line-height: 1.5; }
        @media print {
            body { background: #fff; }
            .actions { display: none !important; }
            .sheet { border: 0; padding: 0; }
        }
    </style>
</head>
<body class="{{ ($forPdf ?? false) ? 'pdf-document' : '' }}">
@php
    $typeLabels = [
        'manual' => 'دستی',
        'sale_invoice' => 'فاکتور فروش',
        'purchase_invoice' => 'فاکتور خرید',
        'payment' => 'پرداخت',
        'receipt' => 'دریافت',
        'inventory' => 'انبار',
        'opening' => 'افتتاحیه',
        'closing' => 'اختتامیه',
    ];
@endphp

@unless($forPdf ?? false)
    <div class="actions">
        <a href="{{ route('accounting-documents.show', $document) }}">بازگشت</a>
        <a href="{{ route('accounting-documents.pdf', $document) }}" data-no-spa>PDF</a>
        <button type="button" onclick="window.print()">چاپ</button>
    </div>
@endunless

<main class="sheet">
    <header>
        <h1>سند حسابداری {{ $document->number }}</h1>
        <div class="meta">
            تاریخ: {{ gregorianToJalaliDate($document->document_date) }} |
            نوع: {{ $typeLabels[$document->type] ?? $document->type }} |
            وضعیت: {{ $document->status === 'posted' ? 'ثبت قطعی' : 'پیش‌نویس' }}
        </div>
        <div class="meta">{{ $document->description ?: '-' }}</div>
    </header>

    <table class="summary-table">
        <tr>
            <td class="summary-label">بدهکار</td>
            <td class="summary-label">بستانکار</td>
            <td class="summary-label">تعداد سطر</td>
            <td class="summary-label">تفصیل‌های بانکی</td>
        </tr>
        <tr>
            <td class="summary-value pdf-num">{{ formatMoney((float) $document->lines->sum('debit')) }}</td>
            <td class="summary-value pdf-num">{{ formatMoney((float) $document->lines->sum('credit')) }}</td>
            <td class="summary-value pdf-num">{{ number_format($document->lines->count()) }}</td>
            <td class="summary-value pdf-num">{{ number_format($document->lines->pluck('detailAccount')->filter()->unique('id')->count()) }}</td>
        </tr>
    </table>

    <table>
        <thead>
        <tr>
            <th>حساب</th>
            <th>تفصیل</th>
            <th>شخص/شرکت</th>
            <th>پروژه</th>
            <th>شرح</th>
            <th>بدهکار</th>
            <th>بستانکار</th>
        </tr>
        </thead>
        <tbody>
        @foreach($document->lines as $line)
            <tr>
                <td>
                    {{ chartAccountDisplayLabel($line->account) }}
                    @if($line->bankAccount)
                        <div class="bank-tag">بانک: {{ $line->bankAccount->bank_name }} - {{ $line->bankAccount->code }}</div>
                    @endif
                </td>
                <td>{{ chartAccountDisplayLabel($line->detailAccount) }}</td>
                <td>{{ $line->party?->name ?: '-' }}</td>
                <td>{{ $line->project?->name ?: '-' }}</td>
                <td>{{ $line->description ?: '-' }}</td>
                <td class="pdf-num">{{ formatMoney((float) $line->debit) }}</td>
                <td class="pdf-num">{{ formatMoney((float) $line->credit) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</main>

@unless($forPdf ?? false)
<script>
    window.addEventListener('load', () => {
        window.setTimeout(() => window.print(), 300);
    });
</script>
@endunless
</body>
</html>
