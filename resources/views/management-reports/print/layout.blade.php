<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $reportTitle ?? 'گزارش' }} - {{ config('app.name', 'ERP') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f3f4f6;
            color: #111827;
            font-family: Tahoma, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.7;
        }
        .print-toolbar {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 14px;
        }
        .print-toolbar a,
        .print-toolbar button {
            border: 0;
            border-radius: 6px;
            padding: 8px 14px;
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
        }
        .print-toolbar button { background: #374151; }
        .print-toolbar a { background: #6b7280; }
        .print-page {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 24px;
            background: #fff;
            padding: 14mm;
            box-shadow: 0 12px 32px rgba(15, 23, 42, 0.12);
        }
        .print-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .print-title {
            margin: 0;
            font-size: 18px;
            font-weight: 900;
        }
        .print-date {
            color: #4b5563;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            margin-bottom: 12px;
        }
        .summary-card {
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 8px;
        }
        .summary-label {
            color: #6b7280;
            font-size: 11px;
            font-weight: 700;
        }
        .summary-value {
            margin-top: 3px;
            color: #111827;
            font-weight: 900;
        }
        .print-note {
            margin: 0 0 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: #f9fafb;
            padding: 8px;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th,
        td {
            border: 1px solid #9ca3af;
            padding: 5px 6px;
            text-align: right;
            vertical-align: top;
        }
        th {
            background: #f3f4f6;
            font-weight: 900;
        }
        tfoot td {
            background: #f9fafb;
            font-weight: 900;
        }
        .text-left { text-align: left; }
        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { background: #fff; }
            .print-toolbar { display: none !important; }
            .print-page {
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
<div class="print-toolbar">
    <button type="button" onclick="window.print()">چاپ</button>
    @isset($backRoute)
        <a href="{{ $backRoute }}">بازگشت</a>
    @endisset
</div>

<main class="print-page">
    <header class="print-header">
        <h1 class="print-title">{{ $reportTitle ?? 'گزارش' }}</h1>
        <div class="print-date">تاریخ چاپ: {{ todayJalaliDate() }}</div>
    </header>

    @yield('content')
</main>
</body>
</html>
