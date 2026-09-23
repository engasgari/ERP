@if($forPdf ?? false)
<style>
    @font-face {
        font-family: 'vazirmatn';
        font-style: normal;
        font-weight: 400;
        src: url('{{ $pdfFontRegular ?? \App\Support\PersianPdf::fontPath('Vazirmatn-Regular.ttf') }}') format('truetype');
    }

    @font-face {
        font-family: 'vazirmatn';
        font-style: normal;
        font-weight: 700;
        src: url('{{ $pdfFontBold ?? \App\Support\PersianPdf::fontPath('Vazirmatn-Bold.ttf') }}') format('truetype');
    }

    html {
        direction: rtl;
    }

    body.pdf-document,
    .pdf-document {
        direction: rtl;
        text-align: right;
        unicode-bidi: embed;
        font-family: vazirmatn, DejaVu Sans, sans-serif !important;
        font-size: 12px;
        line-height: 1.65;
        word-spacing: 0.02em;
    }

    .pdf-document *,
    body.pdf-document * {
        font-family: vazirmatn, DejaVu Sans, sans-serif !important;
    }

    .pdf-document table {
        direction: rtl;
        width: 100%;
        border-collapse: collapse;
        table-layout: auto;
    }

    .pdf-document th,
    .pdf-document td {
        direction: rtl;
        text-align: right;
        vertical-align: top;
        padding: 5px 8px;
        line-height: 1.65;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    .pdf-document th {
        font-weight: 700;
    }

    .pdf-data-table th,
    .pdf-data-table td {
        border: 1px solid #374151;
    }

    .pdf-data-table th {
        background: #f3f4f6;
        text-align: center;
    }

    .pdf-data-table td.pdf-num {
        direction: ltr;
        text-align: left;
        unicode-bidi: embed;
        white-space: nowrap;
    }

    .pdf-summary-table td {
        border: 1px solid #d1d5db;
    }

    .pdf-summary-table .pdf-summary-label {
        background: #f9fafb;
        color: #4b5563;
        font-weight: 700;
        width: 35%;
    }

    .pdf-summary-table .pdf-summary-value {
        font-weight: 800;
    }

    .pdf-section-title {
        margin: 14px 0 8px;
        font-size: 14px;
        font-weight: 800;
    }

    .pdf-subtitle {
        margin: 0 0 10px;
        color: #4b5563;
        font-size: 11px;
    }

    .pdf-ltr,
    .pdf-num {
        direction: ltr !important;
        text-align: left !important;
        unicode-bidi: embed !important;
    }

    .pdf-note {
        margin: 10px 0;
        padding: 8px;
        border: 1px solid #d1d5db;
        background: #f9fafb;
        line-height: 1.7;
    }
</style>
@else
<link rel="stylesheet" href="{{ asset('vendor/fonts/vazirmatn/vazirmatn-font-face.css') }}">
@endif
