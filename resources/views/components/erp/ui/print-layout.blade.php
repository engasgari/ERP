@props([
    'title',
    'backUrl' => '#',
    'size' => 'a4', // a4 | a5
])
@php
    $pageSize = strtolower((string) $size) === 'a5' ? 'a5' : 'a4';
    $pageWidth = $pageSize === 'a5' ? '148mm' : '210mm';
    $pageMinHeight = $pageSize === 'a5' ? '210mm' : '297mm';
    $pagePadding = $pageSize === 'a5' ? '8mm' : '14mm';
    $printMargin = $pageSize === 'a5' ? '6mm' : '10mm';
    $pageCssSize = $pageSize === 'a5' ? 'A5 portrait' : 'A4 portrait';
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - {{ config('app.name', 'ERP') }}</title>
    @include('components.pdf-persian-font-styles', [
        'forPdf' => $forPdf ?? false,
        'pdfFontRegular' => $pdfFontRegular ?? null,
        'pdfFontBold' => $pdfFontBold ?? null,
    ])
    <style>
        *{box-sizing:border-box}
        body{margin:0;background:#e5e7eb;color:#111827;font-family:{{ ($forPdf ?? false) ? 'vazirmatn, DejaVu Sans, sans-serif' : 'Vazirmatn, Tahoma, Arial, sans-serif' }};font-size:{{ $pageSize === 'a5' ? '11px' : '12px' }};line-height:1.7}
        .print-toolbar{display:flex;justify-content:center;gap:8px;padding:16px}
        .print-toolbar a,.print-toolbar button{border:0;border-radius:6px;padding:8px 14px;color:#fff;cursor:pointer;font:inherit;font-weight:700;text-decoration:none}
        .print-toolbar button{background:#2563eb}
        .print-toolbar a{background:#4b5563}
        .print-page{width:{{ $pageWidth }};min-height:{{ $pageMinHeight }};margin:0 auto 24px;background:#fff;padding:{{ $pagePadding }};box-shadow:0 18px 44px rgba(15,23,42,.16)}
        table{width:100%;border-collapse:collapse}
        th,td{border:1px solid #9ca3af;padding:{{ $pageSize === 'a5' ? '4px 5px' : '5px 6px' }};text-align:right;vertical-align:top}
        th{background:#f3f4f6;font-weight:900}
        @media print{
            @page{size:{{ $pageCssSize }};margin:{{ $printMargin }}}
            body{background:#fff}
            .print-toolbar{display:none!important}
            .print-page{width:auto;min-height:auto;margin:0;padding:0;box-shadow:none}
        }
    </style>
</head>
<body>
    @if(! ($forPdf ?? false))
    <div class="print-toolbar">
        <button type="button" onclick="window.print()">چاپ</button>
        <a href="{{ $backUrl }}">بازگشت</a>
    </div>
    @endif
    <main class="print-page">
        {{ $slot }}
    </main>
</body>
</html>
