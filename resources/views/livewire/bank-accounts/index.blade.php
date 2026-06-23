<div class="bg-white rounded-lg shadow-md p-3 p-md-4 space-y-4">
    <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-3">
        <h3 class="text-lg font-bold mb-0">تعریف بانک</h3>
        <span class="text-sm text-slate-500">برای ثبت حساب‌های بانکی و استفاده در دریافت/پرداخت</span>
    </div>

    @include('livewire.partials.flash')

    @if ($errors->any())
        <div class="rounded-md bg-red-50 p-3 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form wire:submit.prevent="save" class="erp-ui-filter-bar">
        <div class="row g-2 g-md-3 align-items-end">
            <label class="erp-filter-field col-12 col-md-6 col-lg-2">کد<input wire:model="form.code" dir="ltr"></label>
            <label class="erp-filter-field col-12 col-md-6 col-lg-3">نام بانک<input wire:model="form.bank_name"></label>
            <label class="erp-filter-field col-12 col-md-6 col-lg-3">شعبه<input wire:model="form.branch"></label>
            <label class="erp-filter-field col-12 col-md-6 col-lg-2">شماره حساب<input wire:model="form.account_number" dir="ltr"></label>
            <label class="erp-filter-field col-12 col-md-6 col-lg-2">شبا<input wire:model="form.iban" dir="ltr"></label>
            <label class="erp-filter-field col-12 col-md-6 col-lg-2">کارت<input wire:model="form.card_number" dir="ltr"></label>
            <label class="erp-filter-field col-12 col-md-4 col-lg-2">ارز<input wire:model="form.currency" dir="ltr"></label>
            <label class="erp-filter-field col-12 col-md-4 col-lg-2">مانده افتتاحیه<input type="number" step="0.01" wire:model="form.opening_balance" dir="ltr"></label>
            <label class="erp-filter-field col-12 col-md-6 col-lg-3">حساب کل<select wire:model="form.chart_account_id"><option value="">بدون حساب</option>@foreach($accounts as $account)<option value="{{ $account->id }}">{{ chartAccountDisplayLabel($account) }}</option>@endforeach</select></label>
            <label class="erp-filter-field col-12 col-md-4 col-lg-2">وضعیت<select wire:model="form.is_active"><option value="1">فعال</option><option value="0">غیرفعال</option></select></label>
            <div class="col-12 col-lg-3 d-grid d-sm-flex gap-2">
                <button class="erp-action-btn erp-action-edit">ذخیره</button>
                <button type="button" wire:click="cancel" class="erp-action-btn">جدید</button>
            </div>
        </div>
    </form>

    <form wire:submit.prevent class="erp-ui-filter-bar">
        <div class="row g-2 g-md-3 align-items-end">
            <label class="erp-filter-field col-12 col-md-9">جستجو
                <input wire:model.live.debounce.400ms="search" placeholder="کد، نام بانک، شعبه، حساب یا شبا">
            </label>
            <label class="erp-filter-field col-12 col-md-3">وضعیت
                <select wire:model.live="is_active">
                    <option value="">همه</option>
                    <option value="1">فعال</option>
                    <option value="0">غیرفعال</option>
                </select>
            </label>
            <div class="col-12 col-md-3 d-grid">
                <button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلترها</button>
            </div>
        </div>
    </form>

    <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>

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
                        <div class="font-semibold text-slate-900">{{ $bank->bank_name }}</div>
                        <div class="mt-1 text-xs text-slate-500">
                            <a class="text-blue-700" href="{{ route('bank-accounts.statement', ['bankAccount' => $bank->id]) }}" wire:navigate>صورتحساب این بانک</a>
                        </div>
                    </td>
                    <td>{{ $bank->branch ?: '-' }}</td>
                    <td class="text-nowrap">{{ $bank->account_number ?: '-' }}</td>
                    <td class="text-nowrap">{{ $bank->iban ?: '-' }}</td>
                    <td class="text-nowrap">{{ number_format((float) $bank->opening_balance) }}</td>
                    <td>{{ chartAccountDisplayLabel($bank->account) }}</td>
                    <td>{{ chartAccountDisplayLabel($bank->detailAccount) }}</td>
                    <td>
                        <x-erp.ui.status-badge :label="$bank->is_active ? 'فعال' : 'غیرفعال'" :tone="$bank->is_active ? 'success' : 'neutral'" />
                    </td>
                    <td>
                        <div class="d-grid d-sm-flex gap-2">
                            <button onclick="event.stopPropagation()" wire:click="edit({{ $bank->id }})" class="erp-action-btn erp-action-edit">ویرایش</button>
                            <button onclick="event.stopPropagation()" wire:click="delete({{ $bank->id }})" wire:confirm="این حساب بانکی حذف شود؟" wire:loading.attr="disabled" wire:target="delete({{ $bank->id }})" class="erp-action-btn erp-action-delete">حذف</button>
                        </div>
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
</div>
