<details class="mt-5 rounded-xl border border-slate-200 bg-slate-50 open:bg-white" open>
    <summary class="cursor-pointer px-4 py-3 text-sm font-bold text-slate-700">فیلترها</summary>
    <x-erp.ui.filter-bar method="GET" action="{{ route('sales-reports.show', ['report' => $reportKey]) }}" class="border-t border-slate-200 p-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        @if(request()->filled('return_to'))
            <input type="hidden" name="return_to" value="{{ request('return_to') }}">
        @endif
        @if(request()->filled('from'))
            <input type="hidden" name="from" value="{{ request('from') }}">
        @endif
        <label class="text-sm font-bold text-slate-700 xl:col-span-4">بازه سریع
            <select name="date_preset" class="mt-1 w-full rounded-lg border-slate-300" onchange="if(this.value){this.form.querySelector('[name=date_from]').value='';this.form.querySelector('[name=date_to]').value='';}">
                <option value="">بازه دلخواه</option>
                @foreach(['today' => 'امروز', 'this_week' => 'این هفته', 'this_month' => 'این ماه', 'last_month' => 'ماه قبل', 'last_3_months' => 'سه ماه اخیر', 'this_year' => 'امسال', 'current_fiscal_year' => 'سال مالی جاری'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('date_preset') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-bold text-slate-700">از تاریخ
            <input type="text" name="date_from" value="{{ request('date_from') ? jalaliDateInputValue(request('date_from')) : '' }}" class="mt-1 w-full rounded-lg border-slate-300">
        </label>
        <label class="text-sm font-bold text-slate-700">تا تاریخ
            <input type="text" name="date_to" value="{{ request('date_to') ? jalaliDateInputValue(request('date_to')) : '' }}" class="mt-1 w-full rounded-lg border-slate-300">
        </label>
        <label class="text-sm font-bold text-slate-700">سال مالی
            <select name="fiscal_year_id" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="">همه</option>
                @foreach($fiscalYears as $year)
                    <option value="{{ $year->id }}" @selected(request('fiscal_year_id') == $year->id)>{{ $year->title }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-bold text-slate-700">مشتری
            <select name="party_id" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="">همه</option>
                @foreach($parties as $party)
                    <option value="{{ $party->id }}" @selected(request('party_id') == $party->id)>{{ $party->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-bold text-slate-700">فروشنده
            <select name="created_by" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="">همه</option>
                @foreach($salespeople as $person)
                    <option value="{{ $person->id }}" @selected(request('created_by') == $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-bold text-slate-700">محصول
            <x-erp.ui.item-search-select
                name="item_id"
                :value="request('item_id')"
                :items="$items"
                inputClass="mt-1 w-full rounded-lg border-slate-300"
                class="mt-1 block"
            />
        </label>
        <label class="text-sm font-bold text-slate-700">گروه محصول
            <select name="category" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="">همه</option>
                @foreach($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-bold text-slate-700">پروژه
            <select name="project_id" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="">همه</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </label>
        @if($reportKey === 'sales')
            <label class="text-sm font-bold text-slate-700">وضعیت صورتحساب
                <select name="status" class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">همه (به‌جز لغوشده)</option>
                    @foreach(['draft' => 'موقت', 'confirmed' => 'تأیید شده', 'cancelled' => 'لغوشده'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm font-bold text-slate-700">وضعیت پرداخت
                <select name="payment_status" class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">همه</option>
                    <option value="settled" @selected(request('payment_status') === 'settled')>تسویه شده</option>
                    <option value="unsettled" @selected(request('payment_status') === 'unsettled')>باز</option>
                </select>
            </label>
        @endif
        <label class="text-sm font-bold text-slate-700">جستجو
            <input type="text" name="search" value="{{ request('search') }}" class="mt-1 w-full rounded-lg border-slate-300" placeholder="شماره، مشتری...">
        </label>
        <label class="text-sm font-bold text-slate-700">مرتب‌سازی
            <select name="sort" class="mt-1 w-full rounded-lg border-slate-300">
                @php
                    $sortOptions = match($reportKey) {
                        'by-customer' => ['total_amount' => 'بیشترین فروش', 'invoice_count' => 'بیشترین تعداد', 'outstanding_amount' => 'بیشترین بدهی', 'profit' => 'بیشترین سود'],
                        'by-product' => ['net_amount' => 'بیشترین مبلغ', 'quantity_sold' => 'بیشترین تعداد', 'profit' => 'بیشترین سود'],
                        'returns-discounts' => ['discount_amount' => 'بیشترین تخفیف'],
                        default => ['invoice_date' => 'تاریخ', 'total_amount' => 'مبلغ', 'number' => 'شماره', 'party' => 'مشتری'],
                    };
                @endphp
                @foreach($sortOptions as $value => $label)
                    <option value="{{ $value }}" @selected(request('sort', array_key_first($sortOptions)) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-sm font-bold text-slate-700">جهت
            <select name="direction" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="desc" @selected(request('direction', 'desc') === 'desc')>نزولی</option>
                <option value="asc" @selected(request('direction') === 'asc')>صعودی</option>
            </select>
        </label>
        <label class="text-sm font-bold text-slate-700">تعداد در صفحه
            <input type="number" name="per_page" value="{{ request('per_page', 25) }}" class="mt-1 w-full rounded-lg border-slate-300" min="10" max="200">
        </label>
        @if($reportKey === 'returns-discounts')
            <input type="hidden" name="tab" value="{{ $tab ?? request('tab', 'discounts') }}">
        @endif
        <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-4">
            <button class="erp-action-btn erp-action-detail" type="submit">اعمال فیلتر</button>
            <a href="{{ route('sales-reports.show', ['report' => $reportKey]) }}" class="erp-action-btn erp-action-detail">پاک کردن</a>
        </div>
    </x-erp.ui.filter-bar>
</details>
