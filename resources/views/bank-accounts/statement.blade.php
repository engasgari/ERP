<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">صورتحساب تراکنش‌های بانکی</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $bankAccount->code }} - {{ $bankAccount->bank_name }}</p>
                <p class="mt-1 text-xs text-slate-400">
                    تفصیل: {{ $bankAccount->detailAccount?->code ? $bankAccount->detailAccount->code . ' - ' . $bankAccount->detailAccount->title : 'برای این بانک هنوز تفصیل ثبت نشده است' }}
                </p>
            </div>
            <a href="{{ route('bank-accounts.index') }}" class="inline-flex items-center rounded-lg bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200">
                بازگشت به تعریف بانک
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-6 px-3 py-6 sm:px-5 lg:px-6">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-500">مانده افتتاحیه</div>
                    <div class="mt-2 text-lg font-black text-slate-900">{{ number_format((float) ($report['summary']['opening_balance'] ?? 0)) }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-500">جمع بدهکار دوره</div>
                    <div class="mt-2 text-lg font-black text-slate-900">{{ number_format((float) ($report['summary']['period_debit'] ?? 0)) }}</div>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-500">جمع بستانکار دوره</div>
                    <div class="mt-2 text-lg font-black text-slate-900">{{ number_format((float) ($report['summary']['period_credit'] ?? 0)) }}</div>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-200">
                    <div class="text-xs font-bold uppercase tracking-wide text-emerald-700">مانده نهایی بانک</div>
                    <div class="mt-2 text-lg font-black text-emerald-800">{{ number_format((float) ($report['summary']['closing_balance'] ?? 0)) }}</div>
                </div>
            </div>

            <form method="get" class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                <label class="text-sm font-bold text-slate-700">از تاریخ
                    <input type="text" name="date_from" value="{{ request('date_from') ? jalaliDateInputValue(request('date_from')) : '' }}" class="mt-1 w-full rounded-lg border-slate-300" placeholder="1404/08/01">
                </label>
                <label class="text-sm font-bold text-slate-700">تا تاریخ
                    <input type="text" name="date_to" value="{{ request('date_to') ? jalaliDateInputValue(request('date_to')) : '' }}" class="mt-1 w-full rounded-lg border-slate-300" placeholder="1404/08/30">
                </label>
                <label class="text-sm font-bold text-slate-700">جستجو
                    <input type="text" name="search" value="{{ request('search') }}" class="mt-1 w-full rounded-lg border-slate-300" placeholder="شماره سند، شرح، حساب...">
                </label>
                <label class="text-sm font-bold text-slate-700">حساب کل
                    <select name="account_id" class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">همه</option>
                        @foreach($accounts ?? [] as $account)
                            <option value="{{ $account->id }}" @selected(request('account_id') == $account->id)>{{ chartAccountDisplayLabel($account) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-bold text-slate-700">طرف حساب
                    <select name="party_id" class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">همه</option>
                        @foreach($parties ?? [] as $party)
                            <option value="{{ $party->id }}" @selected(request('party_id') == $party->id)>{{ $party->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-bold text-slate-700">پروژه
                    <select name="project_id" class="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">همه</option>
                        @foreach($projects ?? [] as $project)
                            <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end gap-2 xl:col-span-5">
                    <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-bold text-white hover:bg-blue-700" type="submit">اعمال فیلتر</button>
                    <a href="{{ route('bank-accounts.statement', ['bankAccount' => $bankAccount->id]) }}" class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-bold text-slate-700 hover:bg-slate-200">پاک کردن</a>
                </div>
            </form>
        </section>

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-black text-slate-900">گردش حساب بانکی</h3>
                    <p class="mt-1 text-sm text-slate-500">سطرهای سندهای حسابداری مرتبط با این بانک و مانده جاری هر سطر</p>
                </div>
                <div class="text-sm font-bold text-slate-600">تعداد ردیف: {{ number_format((int) ($report['summary']['line_count'] ?? 0)) }}</div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full border-collapse text-sm">
                    <thead>
                    <tr class="border-b border-slate-200 bg-slate-50 text-right text-slate-600">
                        <th class="px-3 py-3 font-bold">تاریخ</th>
                        <th class="px-3 py-3 font-bold">شماره سند</th>
                        <th class="px-3 py-3 font-bold">حساب</th>
                        <th class="px-3 py-3 font-bold">تفصیل</th>
                        <th class="px-3 py-3 font-bold">طرف حساب</th>
                        <th class="px-3 py-3 font-bold">پروژه</th>
                        <th class="px-3 py-3 font-bold">شرح</th>
                        <th class="px-3 py-3 font-bold">بدهکار</th>
                        <th class="px-3 py-3 font-bold">بستانکار</th>
                        <th class="px-3 py-3 font-bold">مانده جاری</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse(($report['sections'][0]['rows'] ?? []) as $row)
                        <tr class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-3 py-2">{{ $row['date'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $row['document_number'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $row['account'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $row['detail_account'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $row['party'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $row['project'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $row['description'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ number_format((float) ($row['debit'] ?? 0)) }}</td>
                            <td class="px-3 py-2">{{ number_format((float) ($row['credit'] ?? 0)) }}</td>
                            <td class="px-3 py-2 font-bold">{{ number_format((float) ($row['running_balance'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center text-slate-500">تراکنشی برای این بانک در بازه انتخاب‌شده یافت نشد.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>

