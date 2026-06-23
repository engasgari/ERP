<x-app-layout>
    @php
        $isSale = $invoice->direction === 'sale';
        $documentTitle = $invoice->document_type === 'proforma'
            ? 'پیش فاکتور فروش کالا و خدمات'
            : ($isSale ? 'صورتحساب فروش کالا و خدمات' : 'صورتحساب خرید کالا و خدمات');
        $partyLabel = $isSale ? 'خریدار' : 'فروشنده';
        $warehouseLabel = $invoice->direction === 'purchase' ? 'انبار ورودی' : 'انبار خروجی';
        $inventoryDocument = $invoice->inventoryDocuments->first();
        $rowBaseTotal = $invoice->lines->sum(fn ($line) => (float) $line->quantity * (float) $line->unit_price);
        $discountTotal = (float) $invoice->discount_amount;
        $taxTotal = (float) $invoice->tax_amount;
        $finalTotal = (float) $invoice->total_amount;
        $emptyRows = max(0, 8 - $invoice->lines->count());
        $statusLabel = $invoice->settled_at ? 'تسویه شده' : ($invoice->status === 'confirmed' ? 'تایید نهایی' : 'ثبت موقت');
    @endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl">پیش نمایش و چاپ فاکتور</h2>
    </x-slot>

        <div class="invoice-print-actions">
            <a href="{{ route('invoices.index') }}">بازگشت به فهرست</a>
            <a href="{{ route('invoices.edit', $invoice) }}">ویرایش فاکتور</a>
            <a href="{{ route('invoices.print', $invoice) }}" target="_blank" data-no-spa>چاپ نهایی</a>
            <a href="{{ route('invoices.pdf', $invoice) }}" data-no-spa>PDF</a>
        <a href="{{ route('invoices.excel', $invoice) }}" data-no-spa>Excel</a>
        <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" onsubmit="return confirm('فاکتور و همه سندهای مالی و انبار وابسته حذف شوند؟')">
            @csrf
            @method('DELETE')
            <button type="submit">حذف فاکتور</button>
        </form>

        @if($invoice->document_type === 'proforma')
            <form method="POST" action="{{ route('invoices.convert', $invoice) }}">
                @csrf
                <button type="submit">تبدیل به فاکتور</button>
            </form>
        @elseif($invoice->status === 'draft')
            <form method="POST" action="{{ route('invoices.confirm', $invoice) }}">
                @csrf
                <button type="submit" class="primary">{{ $invoice->direction === 'purchase' ? 'تایید نهایی و ثبت رسید انبار' : 'تایید نهایی و ثبت حواله انبار' }}</button>
            </form>
        @elseif($invoice->status === 'confirmed' && ! $invoice->settled_at)
            <form method="POST" action="{{ route('invoices.settle', $invoice) }}">
                @csrf
                <button type="submit" class="primary">تسویه فاکتور</button>
            </form>
        @elseif($invoice->settled_at)
            <form method="POST" action="{{ route('invoices.unsettle', $invoice) }}">
                @csrf
                <button type="submit" class="primary">خروج از تسویه</button>
            </form>
        @endif
    </div>

    @error('inventory')
        <div class="invoice-inventory-error">{{ $message }}</div>
    @enderror

    <section class="tax-invoice-sheet">
        <div class="tax-invoice-watermark">{{ $invoice->settled_at ? 'تسویه شده' : ($invoice->status === 'confirmed' ? '' : 'ثبت موقت') }}</div>

        <header class="tax-invoice-header">
            <div class="tax-invoice-title">
                <div class="tax-invoice-bismillah">بسمه تعالی</div>
                <h1>{{ $documentTitle }}</h1>
            </div>
            <div class="tax-meta-box">
                <div>
                    <span>شماره فاکتور</span>
                    <strong>{{ $invoice->number }}</strong>
                </div>
                <div>
                    <span>تاریخ</span>
                    <strong>{{ gregorianToJalaliDate($invoice->invoice_date) }}</strong>
                </div>
                <div>
                    <span>پروژه</span>
                    <strong>{{ $invoice->project?->code ? $invoice->project->code . ' - ' . $invoice->project->name : '-' }}</strong>
                </div>
                <div>
                    <span>وضعیت</span>
                    <strong>{{ $statusLabel }}</strong>
                </div>
            </div>
        </header>

        <table class="tax-party-table">
            <thead>
            <tr>
                <th colspan="8">مشخصات فروشنده</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <th>نام شخص حقیقی / حقوقی</th>
                <td colspan="3">{{ $company?->company_name ?? config('app.name', 'ERP') }}</td>
                <th>شناسه ملی</th>
                <td>{{ $company?->national_id ?: '-' }}</td>
                <th>شماره اقتصادی</th>
                <td>{{ $company?->economic_code ?: '-' }}</td>
            </tr>
            <tr>
                <th>نشانی کامل</th>
                <td colspan="3">{{ $company?->address ?: '-' }}</td>
                <th>کد پستی</th>
                <td>{{ $company?->postal_code ?: '-' }}</td>
                <th>تلفن</th>
                <td>{{ $company?->phone ?: '-' }}</td>
            </tr>
            </tbody>
        </table>

        <table class="tax-party-table">
            <thead>
            <tr>
                <th colspan="8">مشخصات {{ $partyLabel }}</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <th>نام شخص حقیقی / حقوقی</th>
                <td colspan="3">{{ $invoice->party?->name ?: 'طرف حساب حذف‌شده' }}</td>
                <th>شناسه / کد ملی</th>
                <td>{{ $invoice->party?->national_id ?: '-' }}</td>
                <th>شماره اقتصادی</th>
                <td>{{ $invoice->party?->economic_code ?: '-' }}</td>
            </tr>
            <tr>
                <th>نشانی کامل</th>
                <td colspan="5">{{ $invoice->party?->address ?: '-' }}</td>
                <th>تلفن</th>
                <td>{{ $invoice->party?->phone ?: ($invoice->party?->mobile ?: '-') }}</td>
            </tr>
            </tbody>
        </table>

        <table class="tax-lines-table">
            <thead>
            <tr class="tax-section-row">
                <th colspan="12">مشخصات کالا یا خدمات مورد معامله</th>
            </tr>
            <tr>
                <th>ردیف</th>
                <th>کد کالا / خدمت</th>
                <th>شرح کالا یا خدمت</th>
                <th>واحد</th>
                <th>تعداد / مقدار</th>
                <th>مبلغ واحد (ریال)</th>
                <th>مبلغ کل (ریال)</th>
                <th>تخفیف</th>
                <th>مبلغ پس از تخفیف (ریال)</th>
                <th>نرخ مالیات</th>
                <th>مالیات و عوارض</th>
                <th>جمع نهایی</th>
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
                    <td>{{ $line->item->code }}</td>
                    <td class="tax-line-title">
                        <strong>{{ $line->item->name }}</strong>
                        @if($line->description)
                            <span>{{ $line->description }}</span>
                        @endif
                    </td>
                    <td>{{ $line->item->unit?->name ?: '-' }}</td>
                    <td>{{ number_format((float) $line->quantity, 3) }}</td>
                    <td>{{ number_format((float) $line->unit_price) }}</td>
                    <td>{{ number_format($base) }}</td>
                    <td>{{ number_format((float) $line->discount_amount) }}</td>
                    <td>{{ number_format($afterDiscount) }}</td>
                    <td>{{ number_format((float) $line->tax_rate, 2) }}٪</td>
                    <td>{{ number_format((float) $line->tax_amount) }}</td>
                    <td>{{ number_format((float) $line->line_total) }}</td>
                </tr>
            @endforeach

            @for($i = 0; $i < $emptyRows; $i++)
                <tr class="tax-empty-row">
                    <td>{{ $invoice->lines->count() + $i + 1 }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
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
                <th colspan="6">جمع کل</th>
                <th>{{ number_format($rowBaseTotal) }}</th>
                <th>{{ number_format($discountTotal) }}</th>
                <th>{{ number_format(max($rowBaseTotal - $discountTotal, 0)) }}</th>
                <th></th>
                <th>{{ number_format($taxTotal) }}</th>
                <th>{{ number_format($finalTotal) }}</th>
            </tr>
            </tfoot>
        </table>

        <div class="tax-invoice-bottom">
        <table class="tax-extra-table">
                <tr>
                    <th>وضعیت فاکتور</th>
                    <td colspan="3">{{ $invoice->settled_at ? 'تسویه شده' : ($invoice->status === 'confirmed' ? 'باز' : 'موقت') }}</td>
                </tr>
                @if($invoice->settled_at)
                    <tr>
                        <th>تاریخ تسویه</th>
                        <td colspan="3">{{ gregorianToJalaliDate($invoice->settled_at) }}</td>
                    </tr>
                    <tr>
                        <th>تسویه کننده</th>
                        <td colspan="3">{{ $invoice->settledBy?->name ?: '-' }}</td>
                    </tr>
                @endif
                <tr>
                    <th>توضیحات</th>
                    <td colspan="3">{{ $invoice->description ?: '-' }}</td>
                </tr>
                <tr>
                    <th>جمع کل به حروف</th>
                    <td colspan="3">{{ persianNumberToWords($finalTotal) }} ریال</td>
                </tr>
                <tr>
                    <th>شرایط و نحوه پرداخت</th>
                    <td colspan="3">نقدی / اعتباری طبق توافق طرفین</td>
                </tr>
            </table>

            <div class="tax-signatures">
                <div>
                    <strong>مهر و امضای فروشنده</strong>
                    <span></span>
                </div>
                <div>
                    <strong>مهر و امضای خریدار</strong>
                    <span></span>
                </div>
            </div>
        </div>
    </section>

    <style>
        .invoice-print-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            justify-content: flex-end;
            margin-bottom: 1rem;
        }

        .invoice-print-actions a,
        .invoice-print-actions button {
            border: 0;
            border-radius: .35rem;
            background: #475569;
            color: #fff;
            padding: .55rem .85rem;
            font-size: .78rem;
            font-weight: 800;
            text-decoration: none;
        }

        .invoice-print-actions button.primary {
            background: #2563eb;
        }

        .invoice-inventory-error {
            max-width: 29.7cm;
            margin: 0 auto 1rem;
            border-radius: .4rem;
            background: #fef2f2;
            color: #b91c1c;
            padding: .75rem 1rem;
            font-size: .85rem;
            font-weight: 800;
        }

        .tax-invoice-sheet {
            position: relative;
            max-width: 29.7cm;
            min-height: 20.9cm;
            margin: 0 auto;
            background: #fff;
            border: 1.8px solid #111827;
            padding: .45cm;
            color: #111827;
            box-shadow: 0 14px 38px rgba(15, 23, 42, .10);
            font-size: .66rem;
            line-height: 1.55;
        }

        .tax-invoice-watermark {
            position: absolute;
            inset: 40% 0 auto;
            transform: rotate(-18deg);
            color: rgba(220, 38, 38, .10);
            font-size: 4.5rem;
            font-weight: 900;
            text-align: center;
            pointer-events: none;
        }

        .tax-invoice-header {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 5.2cm;
            gap: .35rem;
            align-items: stretch;
            margin-bottom: .25rem;
        }

        .tax-invoice-header > * {
            min-width: 0;
        }

        .tax-invoice-title {
            display: grid;
            place-items: center;
            border: 1.5px solid #111827;
            padding: .18rem;
            text-align: center;
        }

        .tax-invoice-bismillah {
            font-weight: 900;
            font-size: .68rem;
        }

        .tax-invoice-title h1 {
            margin: .05rem 0;
            color: #111827;
            font-size: 1.05rem;
            font-weight: 900;
            line-height: 1.2;
        }

        .tax-party-table,
        .tax-lines-table,
        .tax-extra-table {
            width: 100%;
            max-width: 100%;
            border-collapse: collapse !important;
            border-spacing: 0;
            border: 0 !important;
            border-radius: 0 !important;
            overflow: visible !important;
            font-size: inherit;
        }

        .tax-party-table th,
        .tax-party-table td,
        .tax-lines-table th,
        .tax-lines-table td,
        .tax-extra-table th,
        .tax-extra-table td {
            border: 1px solid #111827 !important;
            padding: .15rem .2rem !important;
            color: #111827 !important;
            vertical-align: middle;
        }

        .tax-party-table thead th,
        .tax-lines-table thead th,
        .tax-lines-table tfoot th,
        .tax-extra-table th {
            background: #e5e7eb !important;
            font-weight: 900;
            text-align: center;
        }

        .tax-meta-box {
            display: grid;
            grid-template-rows: repeat(4, minmax(0, 1fr));
            width: 100%;
            min-width: 0;
            max-width: 100%;
            border: 1px solid #111827;
            overflow: hidden;
        }

        .tax-meta-box div {
            display: grid;
            grid-template-columns: 1.72cm minmax(0, 1fr);
            min-width: 0;
            border-bottom: 1px solid #111827;
        }

        .tax-meta-box div:last-child {
            border-bottom: 0;
        }

        .tax-meta-box span,
        .tax-meta-box strong {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
            padding: .12rem .16rem;
            color: #111827;
            font-size: .58rem;
            line-height: 1.35;
            text-align: center;
        }

        .tax-meta-box span {
            border-left: 1px solid #111827;
            background: #e5e7eb;
            font-weight: 900;
        }

        .tax-meta-box strong {
            direction: ltr;
            overflow-wrap: anywhere;
            word-break: break-word;
            white-space: normal;
            font-weight: 900;
        }

        .tax-party-table {
            margin-bottom: .25rem;
        }

        .tax-party-table tbody th,
        .tax-extra-table th {
            width: 3.2cm;
            background: #f3f4f6 !important;
            font-weight: 900;
            text-align: center;
        }

        .tax-lines-table {
            table-layout: fixed;
        }

        .tax-section-row th {
            background: #d1d5db !important;
            font-size: .72rem;
        }

        .tax-lines-table th,
        .tax-lines-table td {
            text-align: center;
        }

        .tax-lines-table th:nth-child(1) { width: .75cm; }
        .tax-lines-table th:nth-child(2) { width: 1.55cm; }
        .tax-lines-table th:nth-child(3) { width: 6.7cm; }
        .tax-lines-table th:nth-child(4) { width: .9cm; }
        .tax-lines-table th:nth-child(5) { width: 1.05cm; }
        .tax-lines-table th:nth-child(6),
        .tax-lines-table th:nth-child(7),
        .tax-lines-table th:nth-child(8),
        .tax-lines-table th:nth-child(9),
        .tax-lines-table th:nth-child(11),
        .tax-lines-table th:nth-child(12) { width: 2.05cm; }
        .tax-lines-table th:nth-child(10) { width: 1.3cm; }

        .tax-line-title {
            text-align: right !important;
        }

        .tax-line-title strong,
        .tax-line-title span {
            display: block;
        }

        .tax-line-title span {
            margin-top: .05rem;
            color: #475569;
            font-size: .58rem;
        }

        .tax-empty-row td {
            height: .62cm;
        }

        .tax-lines-table tfoot th {
            font-size: .68rem;
        }

        .tax-invoice-bottom {
            display: grid;
            grid-template-columns: 1fr 8.2cm;
            gap: .35rem;
            margin-top: .25rem;
            align-items: stretch;
        }

        .tax-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .25rem;
        }

        .tax-signatures div {
            display: grid;
            grid-template-rows: auto 1fr;
            min-height: 2.25cm;
            border: 1px solid #111827;
        }

        .tax-signatures strong {
            display: block;
            border-bottom: 1px solid #111827;
            background: #f3f4f6;
            padding: .15rem;
            text-align: center;
            font-size: .68rem;
            font-weight: 900;
        }

        @media screen and (max-width: 900px) {
            .tax-invoice-header {
                grid-template-columns: 1fr;
            }

            .tax-meta-box {
                max-width: 100%;
            }
        }

        @page {
            size: A4 landscape;
            margin: 5mm;
        }

        @media print {
            * {
                box-shadow: none !important;
                text-shadow: none !important;
            }

            html,
            body {
                background: #fff !important;
                width: 297mm !important;
                min-width: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
            }

            .erp-top-nav,
            header,
            .invoice-print-actions,
            .invoice-inventory-error,
            .tax-invoice-sheet ~ * {
                display: none !important;
            }

            .erp-shell,
            .erp-shell main,
            .erp-shell > main,
            main {
                display: block !important;
                width: 287mm !important;
                max-width: none !important;
                padding: 0 !important;
                margin: 0 !important;
                background: #fff !important;
                overflow: visible !important;
            }

            .tax-invoice-sheet {
                display: block !important;
                width: 287mm !important;
                max-width: 287mm !important;
                min-width: 287mm !important;
                min-height: 200mm !important;
                margin: 0 auto !important;
                border: 1.5px solid #111827;
                box-shadow: none;
                padding: 3mm;
                background: #fff !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .tax-invoice-header {
                grid-template-columns: minmax(0, 1fr) 5cm;
            }

            .tax-party-table,
            .tax-lines-table,
            .tax-extra-table {
                border-collapse: collapse !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .tax-lines-table {
                table-layout: fixed !important;
            }

            .tax-lines-table th,
            .tax-lines-table td,
            .tax-party-table th,
            .tax-party-table td,
            .tax-extra-table th,
            .tax-extra-table td {
                padding: 1.6mm 1.8mm !important;
            }

            .tax-invoice-sheet,
            .tax-invoice-sheet * {
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
        }
    </style>

    <script>
        function printTaxInvoice() {
            document.body.classList.add('printing-tax-invoice');
            window.print();
        }

        window.addEventListener('afterprint', () => {
            document.body.classList.remove('printing-tax-invoice');
        });
    </script>
</x-app-layout>
