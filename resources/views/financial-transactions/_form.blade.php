@php
    $isEdit = $isEdit ?? false;
    $action = $action ?? route('financial-transactions.store');
    $buttonLabel = $buttonLabel ?? ($isEdit ? 'ذخیره تغییرات' : 'ثبت تراکنش');
    $backRoute = $backRoute ?? route('financial-transactions.index');
    $transaction = $financialTransaction ?? null;
    $selectedType = old('type', $transaction?->type ?? 'expense');
    $selectedChartAccountId = old('chart_account_id', $codingSelection['chart_account_id'] ?? null);
    $selectedDetailAccountId = old('detail_account_id', $codingSelection['detail_account_id'] ?? null);
    $selectedCategory = old('category', $transaction?->category);
    $selectedCodingGroup = $codingGroups[$selectedType] ?? ['subsidiaries' => [], 'details_by_subsidiary' => []];
    $initialDetails = $selectedChartAccountId
        ? ($selectedCodingGroup['details_by_subsidiary'][(string) $selectedChartAccountId] ?? [])
        : [];
@endphp

<form method="POST" action="{{ $action }}" class="financial-transaction-form">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <section class="ft-card">
        <header class="ft-card-head">
            <h3>اطلاعات سند</h3>
            <p>نوع، مبلغ، تاریخ و محل پرداخت/دریافت</p>
        </header>
        <div class="ft-card-body ft-grid ft-grid-header">
            <label class="ft-field">
                <span>نوع *</span>
                <select id="type" name="type" required>
                    <option value="">انتخاب نوع</option>
                    <option value="income" @selected($selectedType === 'income')>درآمد</option>
                    <option value="expense" @selected($selectedType === 'expense')>هزینه</option>
                </select>
            </label>

            <label class="ft-field">
                <span>مبلغ (ریال) *</span>
                <input type="number" id="amount" name="amount" value="{{ old('amount', $transaction?->amount) }}" required step="1000">
            </label>

            <label class="ft-field">
                <span>تاریخ *</span>
                <input type="text" id="transaction_date" name="transaction_date" inputmode="numeric" dir="ltr" placeholder="1404/08/01" value="{{ $isEdit ? jalaliDateInputValue(old('transaction_date'), $transaction?->transaction_date) : (old('transaction_date') ? jalaliDateInputValue(old('transaction_date')) : todayJalaliDate()) }}" required>
            </label>

            <label class="ft-field">
                <span>پروژه</span>
                <select name="project_id">
                    <option value="">بدون پروژه</option>
                    @foreach($projects as $project)
                        <option value="{{ $project->id }}" @selected(old('project_id', $transaction?->project_id) == $project->id)>{{ $project->name }}</option>
                    @endforeach
                </select>
            </label>

            <label class="ft-field">
                <span>بانک</span>
                <select id="bank_account_id" name="bank_account_id">
                    <option value="">انتخاب بانک</option>
                    @foreach($bankAccounts as $bankAccount)
                        <option value="{{ $bankAccount->id }}" @selected(old('bank_account_id', $transaction?->bank_account_id) == $bankAccount->id)>{{ $bankAccount->bank_name }} - {{ $bankAccount->code }}</option>
                    @endforeach
                </select>
            </label>

            <label class="ft-field">
                <span>صندوق</span>
                <select id="cashbox_id" name="cashbox_id">
                    <option value="">انتخاب صندوق</option>
                    @foreach($cashboxes as $cashbox)
                        <option value="{{ $cashbox->id }}" @selected(old('cashbox_id', $transaction?->cashbox_id) == $cashbox->id)>{{ $cashbox->name }} - {{ $cashbox->code }}</option>
                    @endforeach
                </select>
            </label>
        </div>
    </section>

    <section class="ft-card">
        <header class="ft-card-head">
            <h3>کدینگ و شرح</h3>
            <p>ابتدا معین را انتخاب کنید، سپس تفصیل همان معین نمایش داده می‌شود</p>
        </header>
        <div class="ft-card-body ft-grid ft-grid-body">
            <label class="ft-field">
                <span>دسته‌بندی (معین) *</span>
                <select id="chart_account_id" name="chart_account_id" required>
                    <option value="">انتخاب دسته‌بندی</option>
                    @foreach($selectedCodingGroup['subsidiaries'] ?? [] as $subsidiary)
                        <option value="{{ $subsidiary['id'] }}" @selected((string) $selectedChartAccountId === (string) $subsidiary['id'])>{{ $subsidiary['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <label class="ft-field">
                <span>تفصیل *</span>
                <select id="detail_account_id" name="detail_account_id" required @disabled(! $selectedChartAccountId)>
                    @if($selectedChartAccountId)
                        <option value="">انتخاب تفصیل</option>
                        @foreach($initialDetails as $detail)
                            <option value="{{ $detail['id'] }}" @selected((string) $selectedDetailAccountId === (string) $detail['id'])>{{ $detail['label'] }}</option>
                        @endforeach
                    @else
                        <option value="">ابتدا دسته‌بندی (معین) را انتخاب کنید</option>
                    @endif
                </select>
            </label>

            <label class="ft-field">
                <span>شماره مرجع</span>
                <input type="text" id="reference_number" name="reference_number" value="{{ old('reference_number', $transaction?->reference_number) }}" placeholder="شماره رسید / فاکتور">
            </label>

            <label class="ft-field ft-field-wide">
                <span>شرح</span>
                <input type="text" id="description" name="description" value="{{ old('description', $transaction?->description) }}" placeholder="اجاره، ناهار پرسنل، درآمد متفرقه">
            </label>
        </div>

        <input type="hidden" id="category" name="category" value="{{ $selectedCategory }}">
    </section>

    <div class="ft-actions">
        <button type="submit" class="erp-action-btn erp-action-edit">{{ $buttonLabel }}</button>
        <a href="{{ $backRoute }}" class="erp-action-btn erp-action-detail text-center">انصراف</a>
    </div>
</form>

<style>
    .financial-transaction-form {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .financial-transaction-form .ft-card {
        overflow: hidden;
        border-radius: 0.75rem;
        border: 1px solid #e2e8f0;
        background: #fff;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    }

    .financial-transaction-form .ft-card-head {
        border-bottom: 1px solid #e2e8f0;
        background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
        padding: 0.65rem 0.85rem;
    }

    .financial-transaction-form .ft-card-head h3 {
        margin: 0;
        font-size: 0.875rem;
        font-weight: 800;
        color: #0f172a;
    }

    .financial-transaction-form .ft-card-head p {
        margin: 0.15rem 0 0;
        font-size: 0.72rem;
        color: #64748b;
    }

    .financial-transaction-form .ft-card-body {
        padding: 0.75rem 0.85rem 0.85rem;
    }

    .financial-transaction-form .ft-grid {
        display: grid;
        gap: 0.65rem 0.75rem;
    }

    .financial-transaction-form .ft-grid-header {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .financial-transaction-form .ft-grid-body {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    @media (min-width: 768px) {
        .financial-transaction-form .ft-grid-header {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .financial-transaction-form .ft-grid-body {
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .financial-transaction-form .ft-field-wide {
            grid-column: span 2;
        }
    }

    .financial-transaction-form .ft-field {
        display: block;
        min-width: 0;
    }

    .financial-transaction-form .ft-field > span {
        display: block;
        margin-bottom: 0.2rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: #475569;
    }

    .financial-transaction-form .ft-field input,
    .financial-transaction-form .ft-field select,
    .financial-transaction-form .ft-field .erp-search-select__input {
        width: 100%;
        border-radius: 0.375rem;
        border: 1px solid #cbd5e1;
        padding: 0.35rem 0.5rem;
        font-size: 0.8125rem;
        line-height: 1.25rem;
        background: #fff;
    }

    .financial-transaction-form .ft-field input:focus,
    .financial-transaction-form .ft-field select:focus,
    .financial-transaction-form .ft-field .erp-search-select__input:focus {
        outline: 2px solid transparent;
        border-color: #64748b;
        box-shadow: 0 0 0 1px #64748b;
    }

    .financial-transaction-form .ft-field select:disabled,
    .financial-transaction-form .ft-field .erp-search-select__input:disabled {
        background: #f8fafc;
        color: #94a3b8;
        cursor: not-allowed;
    }

    .financial-transaction-form .ft-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
</style>

<script>
    (function () {
        const codingGroups = @json($codingGroups);
        let initialChartAccountId = @json($selectedChartAccountId);
        let initialDetailAccountId = @json($selectedDetailAccountId);

        function initFinancialTransactionForm() {
            const form = document.querySelector('.financial-transaction-form:not([data-ft-bound])');
            if (!form) {
                return;
            }

            form.dataset.ftBound = '1';

            const typeSelect = form.querySelector('#type');
            const chartSelect = form.querySelector('#chart_account_id');
            const detailSelect = form.querySelector('#detail_account_id');
            const categoryInput = form.querySelector('#category');

            if (!typeSelect || !chartSelect || !detailSelect || !categoryInput) {
                return;
            }

            function currentGroup() {
                return codingGroups[typeSelect.value] || null;
            }

            function detailsForSubsidiary(group, subsidiaryId) {
                if (!group || !subsidiaryId) {
                    return [];
                }

                const map = group.details_by_subsidiary || {};

                return map[String(subsidiaryId)] || map[subsidiaryId] || [];
            }

            function syncCategory() {
                const detailValue = window.ErpSearchSelect?.getValue(window.ErpSearchSelect.findBySelectId('detail_account_id'))
                    || detailSelect.value;
                const detailOption = Array.from(detailSelect.options).find((option) => option.value === detailValue);

                if (!detailOption || !detailOption.value) {
                    const chartValue = window.ErpSearchSelect?.getValue(window.ErpSearchSelect.findBySelectId('chart_account_id'))
                        || chartSelect.value;
                    const chartOption = Array.from(chartSelect.options).find((option) => option.value === chartValue);
                    categoryInput.value = chartOption?.textContent?.trim() || '';

                    return;
                }

                categoryInput.value = detailOption.textContent.trim();
            }

            function applySelectOptions(selectEl, placeholderHtml, options, selectedValue = null) {
                selectEl.innerHTML = placeholderHtml;

                options.forEach((optionData) => {
                    const option = document.createElement('option');
                    option.value = optionData.id;
                    option.textContent = optionData.label;
                    selectEl.appendChild(option);
                });

                if (selectedValue) {
                    selectEl.value = String(selectedValue);
                }

                window.ErpSearchSelect?.refreshFromSelect(selectEl);

                const wrapper = window.ErpSearchSelect?.findBySelectId(selectEl.id);

                if (wrapper && selectedValue) {
                    window.ErpSearchSelect.setValue(wrapper, selectedValue);
                }
            }

            function renderDetails(useInitialSelection = false) {
                const group = currentGroup();
                const subsidiaryId = window.ErpSearchSelect?.getValue(window.ErpSearchSelect.findBySelectId('chart_account_id'))
                    || chartSelect.value;
                const details = detailsForSubsidiary(group, subsidiaryId);
                const detailWrapper = window.ErpSearchSelect?.findBySelectId('detail_account_id');

                detailSelect.disabled = ! subsidiaryId;
                window.ErpSearchSelect?.setDisabled(detailWrapper, ! subsidiaryId);

                if (! subsidiaryId) {
                    applySelectOptions(
                        detailSelect,
                        '<option value="">ابتدا دسته‌بندی (معین) را انتخاب کنید</option>',
                        [],
                    );
                    categoryInput.value = '';
                    detailSelect.required = true;

                    return;
                }

                let selectedDetailId = null;

                if (useInitialSelection && initialDetailAccountId) {
                    selectedDetailId = initialDetailAccountId;
                    initialDetailAccountId = null;
                } else if (details.length === 1) {
                    selectedDetailId = details[0].id;
                }

                applySelectOptions(
                    detailSelect,
                    '<option value="">انتخاب تفصیل</option>',
                    details,
                    selectedDetailId,
                );

                syncCategory();
            }

            function renderSubsidiaries(useInitialSelection = false) {
                const group = currentGroup();
                let selectedChartId = null;

                if (useInitialSelection && initialChartAccountId) {
                    selectedChartId = initialChartAccountId;
                    initialChartAccountId = null;
                }

                applySelectOptions(
                    chartSelect,
                    '<option value="">انتخاب دسته‌بندی</option>',
                    group?.subsidiaries || [],
                    selectedChartId,
                );

                renderDetails(useInitialSelection);
            }

            typeSelect.addEventListener('change', function () {
                renderSubsidiaries(false);
            });

            chartSelect.addEventListener('change', function () {
                renderDetails(false);
            });

            detailSelect.addEventListener('change', syncCategory);

            renderSubsidiaries(true);
        }

        document.addEventListener('DOMContentLoaded', initFinancialTransactionForm);
        document.addEventListener('livewire:navigated', initFinancialTransactionForm);
    })();
</script>
