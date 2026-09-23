<x-erp.ui.list-page
    title="تراکنش‌های مالی"
    description="ثبت و پیگیری هزینه و درآمدهای غیر فاکتوری؛ هزینه‌های اسناد حسابداری دستی (مثل حقوق و مالیات) هم در این لیست محاسبه می‌شوند."
    route="financial-transactions.index"
    :actions="[
        ['label' => 'ثبت تراکنش جدید', 'url' => route('financial-transactions.create'), 'class' => 'erp-action-edit'],
    ]"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row erp-filter-row-5">
                <label class="erp-filter-field">پروژه
                    <select wire:model.live="project_id">
                        <option value="">همه</option>
                        <option value="null">بدون پروژه</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">بانک
                    <select wire:model.live="bank_account_id">
                        <option value="">همه</option>
                        @foreach($bankAccounts as $bankAccount)
                            <option value="{{ $bankAccount->id }}">{{ $bankAccount->bank_name }} - {{ $bankAccount->code }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">صندوق
                    <select wire:model.live="cashbox_id">
                        <option value="">همه</option>
                        @foreach($cashboxes as $cashbox)
                            <option value="{{ $cashbox->id }}">{{ $cashbox->name }} - {{ $cashbox->code }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="erp-filter-field">نوع
                    <select wire:model.live="type">
                        <option value="">همه</option>
                        <option value="income">درآمد</option>
                        <option value="expense">هزینه</option>
                    </select>
                </label>
                <label class="erp-filter-field">دسته
                    <input wire:model.live.debounce.400ms="category" list="financial-categories" placeholder="دسته">
                    <datalist id="financial-categories">
                        @foreach($categories as $category)
                            <option value="{{ $category }}"></option>
                        @endforeach
                    </datalist>
                </label>
            </div>
            <div class="erp-filter-row erp-filter-row-5">
                <label class="erp-filter-field">از تاریخ
                    <input wire:model.live.debounce.500ms="start_date" inputmode="numeric" dir="ltr" placeholder="1404/01/01">
                </label>
                <label class="erp-filter-field">تا تاریخ
                    <input wire:model.live.debounce.500ms="end_date" inputmode="numeric" dir="ltr" placeholder="1404/12/29">
                </label>
                <label class="erp-filter-field">مبلغ از
                    <input wire:model.live.debounce.500ms="amount_min" inputmode="numeric" dir="ltr" placeholder="0">
                </label>
                <label class="erp-filter-field">مبلغ تا
                    <input wire:model.live.debounce.500ms="amount_max" inputmode="numeric" dir="ltr" placeholder="1000000">
                </label>
                <label class="erp-filter-field">شرح
                    <input wire:model.live.debounce.400ms="description" placeholder="جستجو در شرح">
                </label>
            </div>
            <div class="erp-filter-row">
                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                </div>
            </div>
        </x-erp.ui.filter-bar>

        @foreach(array_filter($dateErrors ?? []) as $error)
            <div class="erp-filter-error">{{ $error }}</div>
        @endforeach
    </x-slot>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-3 mb-4">
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <div class="text-xs font-medium text-emerald-600">کل درآمدها</div>
            <div class="mt-1 text-lg font-bold text-emerald-700">{{ formatMoney((float) $summary['total_income']) }} ریال</div>
        </div>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4">
            <div class="text-xs font-medium text-rose-600">کل هزینه‌ها</div>
            <div class="mt-1 text-lg font-bold text-rose-700">{{ formatMoney((float) $summary['total_expense']) }} ریال</div>
            @if(($summary['ledger_expense'] ?? 0) > 0)
                <div class="mt-1 text-[11px] text-rose-500">
                    ثبت‌سیستمی: {{ formatMoney((float) ($summary['registered_expense'] ?? 0)) }}
                    + سند حسابداری: {{ formatMoney((float) $summary['ledger_expense']) }}
                </div>
            @endif
        </div>
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4">
            <div class="text-xs font-medium text-sky-600">سود / زیان خالص</div>
            <div class="mt-1 text-lg font-bold {{ $summary['profit_loss'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ formatMoney((float) $summary['profit_loss']) }} ریال
            </div>
        </div>
    </div>

    <x-erp.ui.data-table
        :headers="['تاریخ', 'پروژه', 'منبع', 'نوع', 'دسته‌بندی', 'مبلغ (ریال)', 'شرح', 'عملیات']"
        empty-message="هنوز تراکنش مالی ثبت نشده است."
        :colspan="8"
    >
        @foreach($items as $transaction)
            <tr wire:key="financial-row-{{ $transaction->row_key }}">
                <td class="text-nowrap">{{ gregorianToJalaliDate($transaction->transaction_date) }}</td>
                <td>
                    @if($transaction->project_id)
                        <a href="{{ route('financial-transactions.project-report', $transaction->project_id) }}" class="text-blue-700 font-semibold">
                            {{ $transaction->project_name ?: ($transaction->project?->name ?? '-') }}
                        </a>
                    @else
                        -
                    @endif
                </td>
                <td>{{ $transaction->source_label }}</td>
                <td>
                    <x-erp.ui.status-badge
                        :label="$transaction->type_label"
                        :tone="$transaction->type === 'income' ? 'success' : 'danger'"
                    />
                    @if(($transaction->row_source ?? '') === 'ledger_expense')
                        <div class="mt-1 text-[10px] text-slate-500">سند حسابداری</div>
                    @endif
                </td>
                <td>{{ $transaction->category ?: '-' }}</td>
                <td class="text-nowrap font-semibold {{ $transaction->type === 'income' ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $transaction->type === 'income' ? '+' : '-' }}{{ formatMoney((float) $transaction->amount) }}
                </td>
                <td>{{ $transaction->description ?: '-' }}</td>
                <td>
                    <x-erp.ui.row-actions>
                        @if(($transaction->row_source ?? '') === 'ledger_expense')
                            @if($transaction->accounting_document_id)
                                <x-erp.ui.row-action
                                    icon="view"
                                    label="مشاهده سند"
                                    :href="route('accounting-documents.show', $transaction->accounting_document_id)"
                                />
                            @endif
                        @else
                            <x-erp.ui.row-action icon="view" label="جزئیات" :href="route('financial-transactions.show', $transaction->id)" />
                            <x-erp.ui.row-action icon="edit" label="ویرایش" :href="route('financial-transactions.edit', $transaction->id)" />
                            <form method="POST" action="{{ route('financial-transactions.destroy', $transaction->id) }}" onsubmit="return confirm('آیا از حذف این تراکنش مطمئن هستید؟')">
                                @csrf
                                @method('DELETE')
                                <x-erp.ui.row-action type="submit" icon="delete" label="حذف" tone="danger" />
                            </form>
                        @endif
                    </x-erp.ui.row-actions>
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>

    <div>{{ $items->links() }}</div>
</x-erp.ui.list-page>
