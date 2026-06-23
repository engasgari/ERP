@php
    $isEdit = $isEdit ?? false;
    $action = $action ?? route('financial-transactions.store');
    $buttonLabel = $buttonLabel ?? ($isEdit ? 'ذخیره تغییرات' : 'ثبت تراکنش');
    $backRoute = $backRoute ?? route('financial-transactions.index');
    $transaction = $financialTransaction ?? null;
    $selectedType = old('type', $transaction?->type ?? 'expense');
    $selectedCategory = old('category', $transaction?->category);
    $selectedChartAccountId = old('chart_account_id', $codingSelection['chart_account_id'] ?? null);
    $selectedDetailAccountId = old('detail_account_id', $codingSelection['detail_account_id'] ?? null);
    $selectedCodingGroup = $codingGroups[$selectedType] ?? ['categories' => [], 'details' => [], 'main' => null];
    $selectedCodingCategories = $selectedCodingGroup['categories'] ?? [];
    $selectedCodingDetails = $selectedCodingGroup['details'] ?? [];
    $selectedCodingMain = $selectedCodingGroup['main'] ?? null;
    $selectedChartAccountId = $selectedChartAccountId ?? ($selectedCodingMain['id'] ?? null);
@endphp

<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <section class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">نوع *</span>
                <select id="type" name="type" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">انتخاب نوع</option>
                    <option value="income" @selected($selectedType === 'income')>درآمد</option>
                    <option value="expense" @selected($selectedType === 'expense')>هزینه</option>
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">مبلغ (ریال) *</span>
                <input type="number" id="amount" name="amount" value="{{ old('amount', $transaction?->amount) }}" required step="1000" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">تاریخ *</span>
                <input type="text" id="transaction_date" name="transaction_date" inputmode="numeric" dir="ltr" placeholder="1404/08/01" value="{{ $isEdit ? jalaliDateInputValue(old('transaction_date'), $transaction?->transaction_date) : (old('transaction_date') ? jalaliDateInputValue(old('transaction_date')) : todayJalaliDate()) }}" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">پروژه</span>
                <select name="project_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">بدون پروژه</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id', $transaction?->project_id) == $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">بانک</span>
                <select id="bank_account_id" name="bank_account_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">انتخاب بانک</option>
                    @foreach($bankAccounts as $bankAccount)
                        <option value="{{ $bankAccount->id }}" @selected(old('bank_account_id', $transaction?->bank_account_id) == $bankAccount->id)>{{ $bankAccount->bank_name }} - {{ $bankAccount->code }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">صندوق</span>
                <select id="cashbox_id" name="cashbox_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">انتخاب صندوق</option>
                    @foreach($cashboxes as $cashbox)
                        <option value="{{ $cashbox->id }}" @selected(old('cashbox_id', $transaction?->cashbox_id) == $cashbox->id)>{{ $cashbox->name }} - {{ $cashbox->code }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
            <label class="block">
                <span class="mb-1 block text-sm font-medium text-gray-700">دسته‌بندی *</span>
                <select id="category" name="category" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">انتخاب دسته‌بندی</option>
                    @forelse($selectedCodingCategories as $category)
                        <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>
                    @empty
                        <option value="" disabled>برای این نوع داده‌ای تعریف نشده است</option>
                    @endforelse
                </select>
            </label>

            <label class="block md:col-span-2">
                <span class="mb-1 block text-sm font-medium text-gray-700">تفصیل *</span>
                <select id="detail_account_id" name="detail_account_id" required class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm">
                    <option value="">انتخاب تفصیل</option>
                    @forelse($selectedCodingDetails as $detail)
                        <option value="{{ $detail['id'] }}" data-parent-id="{{ $detail['parent_id'] }}" @selected((string) $selectedDetailAccountId === (string) $detail['id'])>{{ $detail['label'] }}</option>
                    @empty
                        <option value="" disabled>تفصیلی برای این نوع تعریف نشده است</option>
                    @endforelse
                </select>
            </label>

            <input type="hidden" id="chart_account_id" name="chart_account_id" value="{{ $selectedChartAccountId }}">

            <label class="block md:col-span-1">
                <span class="mb-1 block text-sm font-medium text-gray-700">شماره مرجع</span>
                <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number', $transaction?->reference_number) }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" placeholder="شماره رسید / فاکتور">
            </label>

            <label class="block md:col-span-2">
                <span class="mb-1 block text-sm font-medium text-gray-700">شرح</span>
                <textarea id="description" name="description" rows="2" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm" placeholder="اجاره، ناهار پرسنل، درآمد متفرقه">{{ old('description', $transaction?->description) }}</textarea>
            </label>
        </div>
    </section>

    <div class="flex flex-wrap gap-3">
        <a href="{{ $backRoute }}" class="rounded-md bg-gray-500 px-4 py-2 text-sm text-white transition duration-200 hover:bg-gray-600">انصراف</a>
        <button type="submit" class="rounded-md bg-blue-500 px-4 py-2 text-sm text-white transition duration-200 hover:bg-blue-600">{{ $buttonLabel }}</button>
    </div>
</form>

<script>
    const typeSelect = document.getElementById('type');
    const chartInput = document.getElementById('chart_account_id');
    const categorySelect = document.getElementById('category');
    const detailSelect = document.getElementById('detail_account_id');
    const codingGroups = @json($codingGroups);
    let initialCategory = @json($selectedCategory);
    let initialChartAccountId = @json($selectedChartAccountId);
    let initialDetailAccountId = @json($selectedDetailAccountId);

    function renderCoding(selectedType, useInitialSelection = false) {
        const group = selectedType ? codingGroups[selectedType] : null;

        categorySelect.innerHTML = '<option value="">انتخاب دسته‌بندی</option>';
        detailSelect.innerHTML = '<option value="">انتخاب تفصیل</option>';

        if (!group || !group.main) {
            chartInput.value = '';
            return;
        }

        (group.categories || []).forEach((category) => {
            const option = document.createElement('option');
            option.value = category;
            option.textContent = category;
            categorySelect.appendChild(option);
        });

        categorySelect.value = useInitialSelection && initialCategory ? initialCategory : (group.categories?.[0] || '');

        (group.details || []).forEach((detail) => {
            const option = document.createElement('option');
            option.value = detail.id;
            option.textContent = detail.label;
            option.dataset.parentId = detail.parent_id || '';
            detailSelect.appendChild(option);
        });

        applyDetailForCategory(useInitialSelection);
        initialCategory = null;
        initialChartAccountId = null;
        initialDetailAccountId = null;
    }

    function applyDetailForCategory(useInitialSelection = false) {
        const selectedType = typeSelect.value;
        const group = selectedType ? codingGroups[selectedType] : null;
        if (!group || !group.main) {
            chartInput.value = '';
            return;
        }

        chartInput.value = group.main.id;

        const selectedCategory = categorySelect.value;
        const matchingDetailId = group.detail_by_category?.[selectedCategory] || null;

        if (useInitialSelection && initialDetailAccountId) {
            detailSelect.value = initialDetailAccountId;
        } else if (matchingDetailId) {
            detailSelect.value = matchingDetailId;
        } else if (detailSelect.options.length > 1 && !detailSelect.value) {
            detailSelect.selectedIndex = 1;
        }
    }

    typeSelect.addEventListener('change', function () {
        renderCoding(this.value);
    });

    categorySelect.addEventListener('change', function () {
        applyDetailForCategory();
    });

    detailSelect.addEventListener('change', function () {
        const selectedOption = this.options[this.selectedIndex];
        chartInput.value = selectedOption?.dataset?.parentId || chartInput.value;
    });

    renderCoding(typeSelect.value, true);
</script>

