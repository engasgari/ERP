<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>چاپ سند حسابداری {{ $document->number }}</title>
    <link rel="stylesheet" href="{{ asset('vendor/fonts/vazirmatn/vazirmatn-font-face.css') }}">
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        * { box-sizing: border-box; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        body { margin: 0; font-family: Vazirmatn, Tahoma, Arial, sans-serif; background: #f3f4f6; color: #111827; }
        .sheet { width: 285mm; min-height: 190mm; margin: 0 auto; background: #fff; border: 1px solid #111827; padding: 6mm; }
        .actions { display: flex; justify-content: flex-end; gap: 8px; margin: 0 auto 8px; width: 285mm; }
        .actions a, .actions button { border: 0; border-radius: 4px; background: #334155; color: #fff; padding: 7px 12px; font-size: 12px; font-weight: 700; text-decoration: none; cursor: pointer; }
        h1 { margin: 0; font-size: 18px; }
        .meta { margin-top: 4px; font-size: 12px; color: #475569; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #111827; padding: 4px 6px; font-size: 11px; vertical-align: middle; }
        th { background: #e5e7eb; font-weight: 800; text-align: center; }
        .summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 10px; }
        .card { border: 1px solid #111827; padding: 6px; }
        .card span { display: block; font-size: 11px; color: #475569; }
        .card strong { display: block; margin-top: 4px; font-size: 14px; }
        .bank-tag { font-size: 11px; color: #0f172a; margin-top: 4px; }
        @media print {
            body { background: #fff; }
            .actions { display: none !important; }
            .sheet { border: 0; padding: 0; }
        }
    </style>
</head>
<body>
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

    <div class="summary">
        <div class="card"><span>بدهکار</span><strong>{{ number_format((float) $document->lines->sum('debit')) }}</strong></div>
        <div class="card"><span>بستانکار</span><strong>{{ number_format((float) $document->lines->sum('credit')) }}</strong></div>
        <div class="card"><span>تعداد سطر</span><strong>{{ number_format($document->lines->count()) }}</strong></div>
        <div class="card"><span>تفصیل‌های بانکی</span><strong>{{ $document->lines->pluck('detailAccount')->filter()->unique('id')->count() }}</strong></div>
    </div>

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
                <td>{{ number_format((float) $line->debit) }}</td>
                <td>{{ number_format((float) $line->credit) }}</td>
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
