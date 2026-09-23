<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold text-xl">تنظیمات شماره‌گذاری اسناد</h2>
            <a href="{{ route('company-settings.edit') }}" class="text-xs text-slate-500 hover:text-slate-800">تنظیمات شرکت</a>
        </div>
    </x-slot>

    <div class="numbering-settings-page bg-white rounded-lg shadow-md p-4 space-y-3">
        <form method="GET" action="{{ route('numbering-settings.index') }}" class="erp-ui-filter-bar !mb-0 !p-3">
            <div class="erp-filter-row erp-filter-row-3">
                <label class="erp-filter-field md:col-span-2">
                    سال مالی
                    <select name="fiscal_year_id">
                        @foreach($fiscalYears as $year)
                            <option value="{{ $year->id }}" @selected($selectedFiscalYearId == $year->id)>
                                {{ $year->title }} ({{ $year->jalali_year }})
                            </option>
                        @endforeach
                    </select>
                </label>
                <div class="erp-filter-actions">
                    <button type="submit" class="erp-action-btn erp-action-detail">اعمال</button>
                </div>
            </div>
            <p class="mt-2 text-[0.68rem] leading-5 text-slate-500">
                «شماره بعدی» = عددی که سند بعدی می‌گیرد. مثال: آخرین فاکتور
                <span dir="ltr" class="font-mono">SI-00058</span>
                → شماره بعدی
                <span dir="ltr" class="font-mono">59</span>
            </p>
        </form>

        <form method="POST" action="{{ route('numbering-settings.update') }}" class="space-y-3">
            @csrf
            @method('PUT')
            <input type="hidden" name="fiscal_year_id" value="{{ $selectedFiscalYearId }}">

            <div class="overflow-x-auto">
                <table class="erp-ui-data-table numbering-settings-table w-full min-w-[720px]">
                    <colgroup>
                        <col style="width: 26%">
                        <col style="width: 10%">
                        <col style="width: 7%">
                        <col style="width: 9%">
                        <col style="width: 10%">
                        <col style="width: 14%">
                        <col style="width: 8%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>نوع سند</th>
                            <th>پیشوند</th>
                            <th>رقم</th>
                            <th>آخرین</th>
                            <th>بعدی</th>
                            <th>نمونه</th>
                            <th title="استفاده مجدد شماره حذف‌شده">بازیافت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $lastScope = null; @endphp
                        @foreach($rows as $index => $row)
                            @if($lastScope !== $row['scope'])
                                <tr class="numbering-settings-section">
                                    <td colspan="7">
                                        {{ $row['scope'] === 'fiscal_year' ? 'اسناد سالیانه' : 'کدهای سراسری' }}
                                    </td>
                                </tr>
                                @php $lastScope = $row['scope']; @endphp
                            @endif
                            <tr>
                                <td>
                                    <input type="hidden" name="rows[{{ $index }}][document_key]" value="{{ $row['document_key'] }}">
                                    <span class="font-bold text-slate-800">{{ $row['label'] }}</span>
                                </td>
                                <td>
                                    <input
                                        name="rows[{{ $index }}][prefix]"
                                        value="{{ old('rows.' . $index . '.prefix', $row['prefix']) }}"
                                        class="numbering-field numbering-field--prefix"
                                        dir="ltr"
                                    >
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        min="1"
                                        max="12"
                                        name="rows[{{ $index }}][padding]"
                                        value="{{ old('rows.' . $index . '.padding', $row['padding']) }}"
                                        class="numbering-field numbering-field--num"
                                    >
                                </td>
                                <td class="font-mono text-slate-500" dir="ltr">{{ $row['last_used'] ?: '—' }}</td>
                                <td>
                                    <input
                                        type="number"
                                        min="1"
                                        name="rows[{{ $index }}][next_number]"
                                        value="{{ old('rows.' . $index . '.next_number', $row['next_number']) }}"
                                        class="numbering-field numbering-field--num"
                                    >
                                </td>
                                <td class="font-mono text-slate-700" dir="ltr">{{ $row['preview'] }}</td>
                                <td class="text-center">
                                    <input type="hidden" name="rows[{{ $index }}][reuse_deleted_numbers]" value="0">
                                    <input
                                        type="checkbox"
                                        name="rows[{{ $index }}][reuse_deleted_numbers]"
                                        value="1"
                                        class="numbering-checkbox"
                                        @checked(old('rows.' . $index . '.reuse_deleted_numbers', $row['reuse_deleted_numbers']))
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($errors->any())
                <div class="rounded-md bg-red-50 px-3 py-2 text-xs font-bold text-red-700">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3">
                <button type="submit" form="numbering-sync-form" class="erp-action-btn erp-action-detail text-[0.68rem]">همگام‌سازی از اسناد</button>
                <button type="submit" class="erp-action-btn erp-action-edit">ذخیره</button>
            </div>
        </form>

        <form id="numbering-sync-form" method="POST" action="{{ route('numbering-settings.sync') }}" class="hidden">
            @csrf
            <input type="hidden" name="fiscal_year_id" value="{{ $selectedFiscalYearId }}">
        </form>
    </div>

    <style>
        .numbering-settings-page .numbering-settings-table {
            border-spacing: 0;
        }

        .numbering-settings-page .numbering-settings-table th,
        .numbering-settings-page .numbering-settings-table td {
            padding: 0.28rem 0.4rem !important;
            vertical-align: middle;
        }

        .numbering-settings-page .numbering-settings-section td {
            padding: 0.45rem 0.4rem 0.2rem !important;
            border-bottom: 0;
            background: #f8fafc;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 800;
        }

        .numbering-settings-page .numbering-field {
            min-height: 1.65rem;
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 0.35rem;
            background: #fff;
            padding: 0.12rem 0.35rem;
            font-size: 0.72rem;
            line-height: 1.2;
        }

        .numbering-settings-page .numbering-field--prefix {
            max-width: 4.5rem;
        }

        .numbering-settings-page .numbering-field--num {
            max-width: 3.25rem;
            text-align: center;
        }

        .numbering-settings-page .numbering-checkbox {
            width: 0.9rem;
            height: 0.9rem;
            margin: 0;
        }
    </style>
</x-app-layout>
