<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">گردش حساب بانکی</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $bankAccount->code }} - {{ $bankAccount->bank_name }}</p>
                <p class="mt-1 text-xs text-slate-400">
                    تفصیل: {{ $bankAccount->detailAccount?->code ? $bankAccount->detailAccount->code . ' - ' . $bankAccount->detailAccount->title : 'برای این بانک هنوز تفصیل ثبت نشده است' }}
                </p>
            </div>
            <a href="{{ route('bank-accounts.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200">
                بازگشت به حساب‌های بانکی
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-3 py-6 sm:px-5 lg:px-6">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-500">مانده افتتاحیه</div>
                    <div class="mt-2 text-lg font-black text-slate-900">{{ formatMoney((float) ($report['summary']['opening_balance'] ?? 0)) }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-500">جمع بدهکار دوره</div>
                    <div class="mt-2 text-lg font-black text-slate-900">{{ formatMoney((float) ($report['summary']['period_debit'] ?? 0)) }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-500">جمع بستانکار دوره</div>
                    <div class="mt-2 text-lg font-black text-slate-900">{{ formatMoney((float) ($report['summary']['period_credit'] ?? 0)) }}</div>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
                    <div class="text-xs font-bold uppercase tracking-wide text-emerald-700">مانده نهایی بانک</div>
                    <div class="mt-2 text-lg font-black text-emerald-800">{{ formatMoney((float) ($report['summary']['closing_balance'] ?? 0)) }}</div>
                </div>
            </div>

            <form method="get" class="erp-ui-filter-bar mt-5">
                <div class="erp-filter-row bank-statement-filter-row">
                    <label class="erp-filter-field">از تاریخ
                        <x-erp.ui.jalali-date-input name="date_from" :value="request('date_from') ? jalaliDateInputValue(request('date_from')) : ''" placeholder="1404/08/01" class="w-full" />
                    </label>
                    <label class="erp-filter-field">تا تاریخ
                        <x-erp.ui.jalali-date-input name="date_to" :value="request('date_to') ? jalaliDateInputValue(request('date_to')) : ''" placeholder="1404/08/30" class="w-full" />
                    </label>
                    <label class="erp-filter-field">جستجو
                        <input type="text" name="search" value="{{ request('search') }}" placeholder="شماره سند، شرح، طرف حساب">
                    </label>
                    <label class="erp-filter-field">طرف حساب
                        <select name="party_id">
                            <option value="">همه</option>
                            @foreach($parties ?? [] as $party)
                                <option value="{{ $party->id }}" @selected(request('party_id') == $party->id)>{{ $party->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field">پروژه
                        <select name="project_id">
                            <option value="">همه</option>
                            @foreach($projects ?? [] as $project)
                                <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="erp-filter-actions">
                        <button type="submit" class="erp-filter-submit">اعمال فیلتر</button>
                        <a href="{{ route('bank-accounts.statement', ['bankAccount' => $bankAccount->id]) }}" class="erp-filter-reset">پاک کردن</a>
                    </div>
                </div>
            </form>
        </section>

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-black text-slate-900">گردش حساب بانکی</h3>
                    <p class="mt-1 text-sm text-slate-500">تراکنش‌های ثبت‌شده روی این حساب بانکی به همراه طرف حساب و پروژه</p>
                </div>
                <div class="text-sm font-bold text-slate-600">تعداد ردیف: {{ formatMoney((int) ($report['summary']['line_count'] ?? 0)) }}</div>
            </div>

            <div class="overflow-x-auto">
                <table class="erp-ui-data-table min-w-full">
                    <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>شماره سند</th>
                        <th>طرف حساب</th>
                        <th>پروژه</th>
                        <th>شرح</th>
                        <th>بدهکار (ریال)</th>
                        <th>بستانکار (ریال)</th>
                        <th>مانده جاری</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse(($report['sections'][0]['rows'] ?? []) as $row)
                        <tr>
                            <td>{{ $row['date'] ?? '-' }}</td>
                            <td class="text-nowrap">{{ $row['document_number'] ?? '-' }}</td>
                            <td>{{ $row['party'] ?? '-' }}</td>
                            <td>{{ $row['project'] ?? '-' }}</td>
                            <td>
                                <x-erp.ui.bank-statement-description
                                    :primary="$row['description_primary'] ?? null"
                                    :secondary="$row['description_secondary'] ?? null"
                                    :description="$row['description'] ?? null"
                                />
                            </td>
                            <td class="text-nowrap">{{ formatMoney((float) ($row['debit'] ?? 0)) }}</td>
                            <td class="text-nowrap">{{ formatMoney((float) ($row['credit'] ?? 0)) }}</td>
                            <td class="text-nowrap font-bold">{{ formatMoney((float) ($row['running_balance'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-8 text-center text-slate-500">تراکنشی برای این بانک در بازه انتخاب‌شده یافت نشد.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <style>
        .erp-shell .bank-statement-filter-row {
            grid-template-columns: minmax(0, 0.82fr) minmax(0, 0.82fr) minmax(0, 1.1fr) minmax(0, 0.95fr) minmax(0, 0.95fr) auto;
        }

        .erp-shell .bank-statement-filter-row .erp-filter-actions {
            flex-wrap: nowrap;
        }

        @media (max-width: 1023px) {
            .erp-shell .bank-statement-filter-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .erp-shell .bank-statement-filter-row .erp-filter-actions {
                grid-column: 1 / -1;
            }
        }
    </style>
</x-app-layout>
