<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>چاپ فاکتور {{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            height: 100%;
            background: #e2e8f0;
            font-family: Tahoma, Vazirmatn, Arial, sans-serif;
        }
        .toolbar {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: #0f172a;
            color: #fff;
        }
        .toolbar strong {
            margin-left: auto;
            font-size: 13px;
            font-weight: 700;
        }
        .toolbar button,
        .toolbar a {
            border: 0;
            border-radius: 6px;
            background: #38bdf8;
            color: #0f172a;
            padding: 8px 14px;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            cursor: pointer;
        }
        .toolbar button.secondary,
        .toolbar a.secondary {
            background: #334155;
            color: #fff;
        }
        .frame-wrap {
            height: calc(100% - 52px);
            padding: 10px;
        }
        iframe {
            width: 100%;
            height: 100%;
            border: 1px solid #94a3b8;
            border-radius: 8px;
            background: #fff;
        }
        @media print {
            .toolbar { display: none !important; }
            .frame-wrap { height: 100%; padding: 0; }
            iframe { border: 0; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <strong>چاپ نهایی فاکتور {{ $invoice->number }}</strong>
        @if(!empty($backUrl))
            <a class="secondary" href="{{ $backUrl }}">بازگشت</a>
        @else
            <a class="secondary" href="#" onclick="window.close(); return false;">بستن</a>
        @endif
        <button type="button" id="print-btn">چاپ</button>
    </div>
    <div class="frame-wrap">
        <iframe
            id="invoice-print-frame"
            title="پیش‌نمایش چاپ فاکتور"
            src="data:application/pdf;base64,{{ $pdfBase64 }}"
        ></iframe>
    </div>
    <script>
        const frame = document.getElementById('invoice-print-frame');
        const printBtn = document.getElementById('print-btn');

        function printFrame() {
            try {
                frame.contentWindow.focus();
                frame.contentWindow.print();
            } catch (e) {
                window.print();
            }
        }

        printBtn.addEventListener('click', printFrame);
        frame.addEventListener('load', () => {
            window.setTimeout(printFrame, 500);
        });
    </script>
</body>
</html>
