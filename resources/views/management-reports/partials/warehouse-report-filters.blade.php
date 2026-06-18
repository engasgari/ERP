<x-erp.ui.filter-bar method="GET" :action="$action">
    <div class="erp-filter-row erp-filter-row-5">
        <label class="erp-filter-field">
            انبار
            <select name="warehouse_id">
                <option value="">همه</option>
                @foreach($warehouses as $warehouse)
                    <option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>
                        {{ $warehouse->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="erp-filter-field">
            کالا
            <input name="item_name" value="{{ request('item_name') }}" placeholder="نام کالا">
        </label>

        <label class="erp-filter-field">
            دسته
            <input name="category" value="{{ request('category') }}" list="{{ $reportKey }}-categories" placeholder="دسته">
        </label>
        <datalist id="{{ $reportKey }}-categories">
            @foreach($categories as $category)
                <option value="{{ $category }}"></option>
            @endforeach
        </datalist>

        <label class="erp-filter-field">
            پروژه
            <select name="project_id">
                <option value="">همه</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" @selected(request('project_id') == $project->id)>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </label>

        <label class="erp-filter-field">
            نوع
            <select name="type">
                <option value="">همه</option>
                <option value="receipt" @selected(in_array(request('type'), ['receipt', 'in'], true))>رسید انبار</option>
                <option value="issue" @selected(in_array(request('type'), ['issue', 'out'], true))>حواله خروج</option>
                <option value="consumption" @selected(request('type') === 'consumption')>حواله مصرف</option>
            </select>
        </label>
    </div>

    <div class="erp-filter-row erp-filter-row-5">
        <label class="erp-filter-field">
            از تاریخ
            <input name="start_date" value="{{ request('start_date') ? jalaliDateInputValue(request('start_date')) : '' }}" inputmode="numeric" dir="ltr" placeholder="1403/01/01">
        </label>

        <label class="erp-filter-field">
            تا تاریخ
            <input name="end_date" value="{{ request('end_date') ? jalaliDateInputValue(request('end_date')) : '' }}" inputmode="numeric" dir="ltr" placeholder="1403/12/29">
        </label>

        <label class="erp-filter-field">
            مرجع
            <input name="reference_number" value="{{ request('reference_number') }}" placeholder="شماره مرجع">
        </label>

        <label class="erp-filter-field">
            توضیحات
            <input name="description" value="{{ request('description') }}" placeholder="جستجو">
        </label>

        <div class="erp-filter-actions">
            @isset($showOnlyAvailable)
                <label class="flex min-h-8 items-center gap-2 rounded bg-white px-2 text-xs font-bold text-gray-600">
                    <input type="checkbox" name="only_available" value="1" @checked(request()->boolean('only_available'))>
                    فقط مثبت
                </label>
            @endisset

            <x-filter-actions :reset-route="$action" />
        </div>
    </div>
</x-erp.ui.filter-bar>

@foreach(array_filter($dateErrors ?? []) as $error)
    <div class="erp-filter-error">{{ $error }}</div>
@endforeach
