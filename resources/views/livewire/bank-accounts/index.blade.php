<x-erp.ui.list-page
    title="حساب‌های بانکی"
    description="مدیریت حساب‌های بانکی و استفاده در دریافت و پرداخت."
    route="bank-accounts.index"
>
    <x-slot name="filters">
        <div class="flex flex-col gap-3">
            <div class="flex justify-end">
                <button type="button" wire:click="openCreate" class="erp-action-btn erp-action-edit">تعریف بانک جدید</button>
            </div>

            <x-erp.ui.filter-bar wire:submit.prevent>
                <div class="erp-filter-row bank-accounts-filter-row">
                    <label class="erp-filter-field">جستجو
                        <input wire:model.live.debounce.400ms="search" placeholder="کد، نام بانک، شعبه، حساب یا شبا">
                    </label>
                    <label class="erp-filter-field">وضعیت
                        <select wire:model.live="is_active">
                            <option value="">همه</option>
                            <option value="1">فعال</option>
                            <option value="0">غیرفعال</option>
                        </select>
                    </label>
                    <div class="erp-filter-actions">
                        <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
                    </div>
                </div>
            </x-erp.ui.filter-bar>
        </div>
    </x-slot>

    <div class="table-responsive overflow-x-auto">
        <table class="erp-ui-data-table min-w-full">
            <thead>
                <tr>
                    <th>کد</th>
                    <th>نام بانک</th>
                    <th>شعبه</th>
                    <th>شماره حساب</th>
                    <th>شبا</th>
                    <th>مانده افتتاحیه</th>
                    <th>حساب کل</th>
                    <th>تفصیل</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
            @forelse($banks as $bank)
                <tr wire:key="bank-{{ $bank->id }}" class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('bank-accounts.statement', ['bankAccount' => $bank->id]) }}'">
                    <td class="text-nowrap">{{ $bank->code }}</td>
                    <td>
                        <a
                            href="{{ route('bank-accounts.statement', ['bankAccount' => $bank->id]) }}"
                            wire:navigate
                            class="erp-table-text-link"
                            onclick="event.stopPropagation()"
                        >{{ $bank->bank_name }}</a>
                    </td>
                    <td>{{ $bank->branch ?: '-' }}</td>
                    <td class="text-nowrap">{{ $bank->account_number ?: '-' }}</td>
                    <td class="text-nowrap">{{ $bank->iban ?: '-' }}</td>
                    <td class="text-nowrap">{{ formatMoney((float) $bank->opening_balance) }}</td>
                    <td>{{ chartAccountDisplayLabel($bank->account) }}</td>
                    <td>{{ chartAccountDisplayLabel($bank->detailAccount) }}</td>
                    <td>
                        <x-erp.ui.status-badge :label="$bank->is_active ? 'فعال' : 'غیرفعال'" :tone="$bank->is_active ? 'success' : 'neutral'" />
                    </td>
                    <td>
                        <x-erp.ui.row-actions>
                            <x-erp.ui.row-action
                                icon="edit"
                                label="ویرایش"
                                wire:click="edit({{ $bank->id }})"
                                onclick="event.stopPropagation()"
                            />
                            <x-erp.ui.row-action
                                icon="delete"
                                label="حذف"
                                tone="danger"
                                wire:click="delete({{ $bank->id }})"
                                wire:confirm="این حساب بانکی حذف شود؟"
                                wire:loading.attr="disabled"
                                wire:target="delete({{ $bank->id }})"
                                onclick="event.stopPropagation()"
                            />
                        </x-erp.ui.row-actions>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center py-6 text-slate-500">رکوردی ثبت نشده است.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $banks->links() }}</div>

    @if($showFormModal)
        <x-erp.ui.details-modal :title="$editingId ? 'ویرایش حساب بانکی' : 'تعریف بانک جدید'">
            <x-slot name="close">
                <button type="button" class="erp-modal-close" wire:click="closeFormModal">×</button>
            </x-slot>

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-3 text-sm text-red-700 mb-4">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form id="bank-account-form" wire:submit.prevent="save" class="space-y-4">
                <div class="erp-modal-grid">
                    <label class="erp-filter-field">کد
                        <input wire:model="form.code" dir="ltr">
                    </label>
                    <label class="erp-filter-field">نام بانک
                        <input wire:model="form.bank_name">
                    </label>
                    <label class="erp-filter-field">شعبه
                        <input wire:model="form.branch">
                    </label>
                    <label class="erp-filter-field">شماره حساب
                        <input wire:model="form.account_number" dir="ltr">
                    </label>
                    <label class="erp-filter-field">شبا
                        <input wire:model="form.iban" dir="ltr">
                    </label>
                    <label class="erp-filter-field">کارت
                        <input wire:model="form.card_number" dir="ltr">
                    </label>
                    <label class="erp-filter-field">ارز
                        <input wire:model="form.currency" dir="ltr">
                    </label>
                    <label class="erp-filter-field">مانده افتتاحیه
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            wire:model.blur="form.opening_balance"
                            dir="ltr"
                            data-erp-money="0"
                            placeholder="0"
                        >
                        <span class="mt-1 block text-xs font-normal text-slate-500">موجودی ابتدای دوره — در صورتحساب بانک اعمال می‌شود</span>
                    </label>
                    <label class="erp-filter-field md:col-span-2">حساب کل
                        <select wire:model="form.chart_account_id">
                            <option value="">بدون حساب</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}">{{ chartAccountDisplayLabel($account) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field">وضعیت
                        <select wire:model="form.is_active">
                            <option value="1">فعال</option>
                            <option value="0">غیرفعال</option>
                        </select>
                    </label>
                </div>
            </form>

            <x-slot name="footer">
                <div class="flex justify-end gap-2">
                    <button type="button" wire:click="closeFormModal" class="erp-action-btn">انصراف</button>
                    <button type="submit" form="bank-account-form" class="erp-action-btn erp-action-edit">ذخیره</button>
                </div>
            </x-slot>
        </x-erp.ui.details-modal>
    @endif

    <style>
        .erp-shell .erp-table-text-link {
            display: inline !important;
            min-height: 0 !important;
            border: 0 !important;
            border-radius: 0 !important;
            background: transparent !important;
            box-shadow: none !important;
            padding: 0 !important;
            font: inherit !important;
            font-weight: inherit !important;
            color: inherit !important;
            text-decoration: none !important;
            transform: none !important;
        }

        .erp-shell .erp-table-text-link:hover,
        .erp-shell .erp-table-text-link:focus {
            color: #1d4ed8 !important;
            text-decoration: underline !important;
            background: transparent !important;
            border: 0 !important;
            box-shadow: none !important;
            transform: none !important;
        }

        .erp-shell .bank-accounts-filter-row {
            grid-template-columns: minmax(0, 1fr) minmax(0, 0.75fr) auto;
        }

        @media (max-width: 767px) {
            .erp-shell .bank-accounts-filter-row {
                grid-template-columns: 1fr;
            }

            .erp-shell .bank-accounts-filter-row .erp-filter-actions {
                grid-column: 1 / -1;
            }
        }
    </style>
</x-erp.ui.list-page>
