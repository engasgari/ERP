<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">ثبت سند حسابداری</h2></x-slot>

    <form method="post" action="{{ $document->exists ? route('accounting-documents.update', $document) : route('accounting-documents.store') }}" class="space-y-5 rounded-2xl border border-slate-200 bg-slate-50/80 p-5 shadow-sm md:p-6">
        @csrf
        @if($document->exists) @method('PUT') @endif

        @include('livewire.partials.flash')

        @if($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 shadow-sm">
                <div class="mb-2 font-bold">لطفاً خطاهای زیر را اصلاح کنید:</div>
                <ul class="list-disc space-y-1 ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <label class="block text-sm font-medium text-slate-700">
                    شماره سند
                    <input name="number" value="{{ old('number', $document->number) }}" placeholder="ثبت خودکار در صورت خالی بودن" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </label>

                <label class="block text-sm font-medium text-slate-700">
                    تاریخ سند
                    <input type="text" name="document_date" inputmode="numeric" dir="ltr" placeholder="1405/03/19" value="{{ old('document_date') ? jalaliDateInputValue(old('document_date')) : jalaliDateInputValue(null, $document->document_date ?: now()) }}" required class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </label>

                <label class="block text-sm font-medium text-slate-700">
                    وضعیت
                    <select name="status" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="draft" @selected(old('status', $document->status ?: 'draft') === 'draft')>پیش‌نویس</option>
                        <option value="posted" @selected(old('status', $document->status) === 'posted')>ثبت قطعی</option>
                    </select>
                </label>

                <label class="block text-sm font-medium text-slate-700">
                    ارز
                    <input name="currency" value="{{ old('currency', $document->currency ?: 'IRR') }}" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
            <label class="block text-sm font-medium text-slate-700">
                شرح سند
                <textarea name="description" rows="2" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('description', $document->description) }}</textarea>
            </label>
        </section>

        @php
            $lines = old('lines', $document->lines->toArray() ?: [
                ['debit' => 0, 'credit' => 0],
                ['debit' => 0, 'credit' => 0],
            ]);
        @endphp

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                <div class="text-sm font-medium text-slate-700">ردیف‌های سند</div>
                <div class="flex flex-wrap gap-2 text-xs font-medium">
                    <span class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-emerald-700">بدهکار</span>
                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-amber-700">بستانکار</span>
                    <span class="rounded-full border border-slate-200 bg-white px-3 py-1 text-slate-600">نمایش فشرده برای موبایل و دسکتاپ</span>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200">
                <table class="erp-ui-data-table min-w-[1120px] table-fixed md:min-w-[1280px]">
                    <thead class="sticky top-0 bg-slate-100">
                    <tr>
                        <th class="w-[120px] md:w-[135px] bg-slate-100">حساب</th>
                        <th class="w-[110px] md:w-[125px] bg-slate-100">تفصیل</th>
                        <th class="w-[140px] md:w-[160px] bg-slate-100">شخص/شرکت</th>
                        <th class="w-[140px] md:w-[160px] bg-slate-100">پروژه</th>
                        <th class="bg-slate-100">شرح ردیف</th>
                        <th class="w-[170px] md:w-[190px] bg-emerald-50 text-emerald-800">بدهکار (ریال)</th>
                        <th class="w-[170px] md:w-[190px] bg-amber-50 text-amber-800">بستانکار (ریال)</th>
                    </tr>
                    </thead>
                    <tbody>
                    @for($i = 0; $i < max(8, count($lines)); $i++)
                        @php($line = $lines[$i] ?? [])
                        <tr class="align-top odd:bg-white even:bg-slate-50/60 hover:bg-slate-50">
                            <td>
                                <select name="lines[{{ $i }}][chart_account_id]" class="w-full max-w-[135px] rounded-md border-slate-300 bg-white px-2 py-2 text-[11px] shadow-sm focus:border-primary-500 focus:ring-primary-500 md:max-w-[150px] md:px-3 md:text-sm">
                                    <option value="">انتخاب حساب</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" @selected(($line['chart_account_id'] ?? null) == $account->id)>{{ chartAccountDisplayLabel($account) }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $i }}][detail_account_id]" class="w-full max-w-[125px] rounded-md border-slate-300 bg-white px-2 py-2 text-[11px] shadow-sm focus:border-primary-500 focus:ring-primary-500 md:max-w-[140px] md:px-3 md:text-sm">
                                    <option value="">انتخاب تفصیل</option>
                                    @foreach($accounts as $account)
                                        @if($account->level === 'detail')
                                            <option value="{{ $account->id }}" @selected(($line['detail_account_id'] ?? null) == $account->id)>{{ chartAccountDisplayLabel($account) }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $i }}][party_id]" class="w-full rounded-md border-slate-300 bg-white px-2 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 md:px-3 md:text-sm">
                                    <option value="">بدون شخص</option>
                                    @foreach($parties as $party)
                                        <option value="{{ $party->id }}" @selected(($line['party_id'] ?? null) == $party->id)>{{ $party->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $i }}][project_id]" class="w-full rounded-md border-slate-300 bg-white px-2 py-2 text-xs shadow-sm focus:border-primary-500 focus:ring-primary-500 md:px-3 md:text-sm">
                                    <option value="">بدون پروژه</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}" @selected(($line['project_id'] ?? null) == $project->id)>{{ $project->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input name="lines[{{ $i }}][description]" value="{{ $line['description'] ?? '' }}" class="w-full rounded-md border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="lines[{{ $i }}][debit]" value="{{ $line['debit'] ?? 0 }}" class="w-full rounded-md border-emerald-300 bg-emerald-50 px-3 py-3 text-left text-base font-extrabold tracking-wide text-emerald-900 shadow-sm ring-1 ring-emerald-100 focus:border-emerald-500 focus:ring-emerald-500">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="lines[{{ $i }}][credit]" value="{{ $line['credit'] ?? 0 }}" class="w-full rounded-md border-amber-300 bg-amber-50 px-3 py-3 text-left text-base font-extrabold tracking-wide text-amber-900 shadow-sm ring-1 ring-amber-100 focus:border-amber-500 focus:ring-amber-500">
                            </td>
                        </tr>
                    @endfor
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:p-5">
            <label class="block text-sm font-medium text-slate-700">
                یادداشت
                <textarea name="notes" rows="2" class="mt-1 w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">{{ old('notes', $document->notes) }}</textarea>
            </label>
        </section>

        <div class="flex flex-wrap gap-3">
            <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white shadow-sm transition duration-200 hover:bg-blue-700">ذخیره سند</button>
        </div>
    </form>
</x-app-layout>

