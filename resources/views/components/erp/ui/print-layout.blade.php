@props(['title', 'backUrl' => '#'])
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} - {{ config('app.name', 'ERP') }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
    <style>
        *{box-sizing:border-box}body{margin:0;background:#e5e7eb;color:#111827;font-family:Vazirmatn,Tahoma,Arial,sans-serif;font-size:12px;line-height:1.7}.print-toolbar{display:flex;justify-content:center;gap:8px;padding:16px}.print-toolbar a,.print-toolbar button{border:0;border-radius:6px;padding:8px 14px;color:#fff;cursor:pointer;font:inherit;font-weight:700;text-decoration:none}.print-toolbar button{background:#2563eb}.print-toolbar a{background:#4b5563}.print-page{width:210mm;min-height:297mm;margin:0 auto 24px;background:#fff;padding:14mm;box-shadow:0 18px 44px rgba(15,23,42,.16)}table{width:100%;border-collapse:collapse}th,td{border:1px solid #9ca3af;padding:5px 6px;text-align:right;vertical-align:top}th{background:#f3f4f6;font-weight:900}@media print{@page{size:A4 portrait;margin:10mm}body{background:#fff}.print-toolbar{display:none!important}.print-page{width:auto;min-height:auto;margin:0;padding:0;box-shadow:none}}
    </style>
</head>
<body>
    <div class="print-toolbar">
        <button type="button" onclick="window.print()">چاپ</button>
        <a href="{{ $backUrl }}">بازگشت</a>
    </div>
    <main class="print-page">
        {{ $slot }}
    </main>
</body>
</html>
