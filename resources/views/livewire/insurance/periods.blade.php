<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="max-w-3xl">
                <h2 class="text-xl font-bold text-slate-900">دوره‌های بیمه تأمین اجتماعی</h2>
                <p class="mt-1 text-sm leading-7 text-slate-500">
                    بدهی بیمه پس از محاسبه حقوق همگام‌سازی می‌شود. جریمه تأخیر و سایر مبالغ را قبل از پرداخت ثبت کنید.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:min-w-[420px]">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">سال</span>
                    <select wire:model.live="year" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        @for($y = verta()->year; $y >= 1400; $y--)
                            <option value="{{ $y }}">{{ toPersianDigits($y) }}</option>
                        @endfor
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">وضعیت بدهی</span>
                    <select wire:model.live="statusFilter" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        <option value="">همه</option>
                        <option value="unpaid">پرداخت‌نشده</option>
                        <option value="partial">پرداخت جزئی</option>
                        <option value="settled">تسویه‌شده</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button type="button" wire:click="syncAll" wire:confirm="بدهی بیمه از محاسبات حقوق همگام‌سازی شود؟" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                همگام‌سازی از حقوق
            </button>
            <a href="{{ route('insurance.payments.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                پرداخت بیمه
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-lg bg-white p-4 shadow-md">
            <div class="text-xs text-slate-500">اصل بیمه</div>
            <div class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney($summary['principal']) }}</div>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-md">
            <div class="text-xs text-slate-500">جریمه</div>
            <div class="mt-1 text-lg font-bold text-amber-700">{{ formatMoney($summary['penalty']) }}</div>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-md">
            <div class="text-xs text-slate-500">سایر</div>
            <div class="mt-1 text-lg font-bold text-slate-900">{{ formatMoney($summary['other']) }}</div>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-md">
            <div class="text-xs text-slate-500">پرداخت‌شده</div>
            <div class="mt-1 text-lg font-bold text-green-700">{{ formatMoney($summary['paid']) }}</div>
        </div>
        <div class="rounded-lg bg-white p-4 shadow-md">
            <div class="text-xs text-slate-500">مانده</div>
            <div class="mt-1 text-lg font-bold text-red-700">{{ formatMoney($summary['balance']) }}</div>
        </div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full text-sm">
                <thead>
                <tr>
                    <th>دوره</th>
                    <th>اصل</th>
                    <th>جریمه</th>
                    <th>سایر</th>
                    <th>پرداخت‌شده</th>
                    <th>مانده</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($periods as $period)
                    @php
                        $liability = $period->liability;
                        $statusLabels = ['unpaid' => 'پرداخت‌نشده', 'partial' => 'پرداخت جزئی', 'settled' => 'تسویه‌شده'];
                        $isEditing = $liability && $editingLiabilityId === $liability->id;
                    @endphp
                    <tr>
                        <td>{{ $period->persian_title }}</td>
                        <td>{{ $liability ? formatMoney((float) $liability->principal_amount) : '-' }}</td>
                        <td>{{ $liability ? formatMoney((float) $liability->penalty_amount) : '-' }}</td>
                        <td>{{ $liability ? formatMoney((float) $liability->other_amount) : '-' }}</td>
                        <td>{{ $liability ? formatMoney((float) $liability->paid_amount) : '-' }}</td>
                        <td>{{ $liability ? formatMoney((float) $liability->balance_amount) : '-' }}</td>
                        <td>
                            @if($liability)
                                <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $liability->status === 'settled' ? 'bg-green-100 text-green-800' : ($liability->status === 'partial' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800') }}">
                                    {{ $statusLabels[$liability->status] ?? $liability->status }}
                                </span>
                            @else
                                -
                            @endif
                        </td>
                        <td>
                            @if($liability && ! $isEditing)
                                @if((float) $liability->paid_amount <= 0.009)
                                    <button type="button" wire:click="startEdit({{ $liability->id }})" class="text-blue-600 hover:underline">جریمه/سایر</button>
                                @else
                                    <span class="text-xs text-slate-400">قفل</span>
                                @endif
                            @elseif($isEditing)
                                <div class="space-y-2 min-w-[220px]">
                                    <input wire:model="penaltyAmount" type="number" min="0" step="1" class="w-full rounded-md border-gray-300 text-right" placeholder="جریمه">
                                    <input wire:model="otherAmount" type="number" min="0" step="1" class="w-full rounded-md border-gray-300 text-right" placeholder="سایر">
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="saveManualAmounts" class="rounded bg-green-600 px-2 py-1 text-xs text-white">ذخیره</button>
                                        <button type="button" wire:click="cancelEdit" class="rounded border px-2 py-1 text-xs">انصراف</button>
                                    </div>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-8 text-center text-slate-500">دوره بیمه‌ای برای این سال یافت نشد. ابتدا حقوق را محاسبه کنید یا همگام‌سازی را اجرا کنید.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $periods->links() }}</div>
    </div>
</div>
