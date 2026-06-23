<div dir="rtl" class="space-y-6">
    @if(session('success'))
        <div class="rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-3xl">
                <h2 class="text-xl font-bold text-slate-900">اجرای حقوق ماهانه</h2>
                <p class="mt-1 text-sm leading-7 text-slate-500">
                    دوره را بسازید، کارکرد را از روی <span class="font-semibold text-slate-700">work_logs</span> محاسبه کنید و بعد حقوق، بیمه، مالیات و فیش را تولید کنید.
                    اگر دوره هنوز تایید نشده باشد، می‌توانید آن را حذف کنید و محاسبه را دوباره انجام دهید.
                </p>
            </div>

            <form wire:submit="createPeriod" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 lg:min-w-[640px]">
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">سال</span>
                    <select wire:model.live="year" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        @for($y = verta()->year; $y >= 1400; $y--)
                            <option value="{{ $y }}">{{ toPersianDigits($y) }}</option>
                        @endfor
                    </select>
                </label>

                <label class="block">
                    <span class="text-sm font-medium text-slate-700">ماه</span>
                    <select wire:model.live="month" class="mt-1 w-full rounded-md border-gray-300 text-right">
                        @for($m = 1; $m <= 12; $m++)
                            <option value="{{ $m }}">{{ getPersianMonthName($m) }}</option>
                        @endfor
                    </select>
                </label>

                <label class="block sm:col-span-2 lg:col-span-1">
                    <span class="text-sm font-medium text-slate-700">یادداشت</span>
                    <input type="text" wire:model="notes" class="mt-1 w-full rounded-md border-gray-300 text-right" placeholder="اختیاری">
                </label>

                <button type="submit" wire:loading.attr="disabled" wire:target="createPeriod" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="createPeriod">آماده‌سازی دوره</span>
                    <span wire:loading wire:target="createPeriod">در حال ساخت...</span>
                </button>
            </form>
        </div>

        @error('year') <div class="mt-3 text-sm text-red-600">{{ $message }}</div> @enderror
        @error('month') <div class="mt-1 text-sm text-red-600">{{ $message }}</div> @enderror
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-bold text-slate-900">دوره‌ها</h3>
            <div wire:loading.delay class="text-sm text-slate-500">در حال به‌روزرسانی...</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">دوره</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">بازه</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">وضعیت</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">کارکرد جدید</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">حقوق جدید</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">جمع پرداختی</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">عملیات</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse($periods as $period)
                    <tr wire:key="payroll-period-{{ $period->id }}">
                        <td class="px-3 py-3 font-semibold text-slate-900">{{ $period->persian_title }}</td>
                        <td class="px-3 py-3 text-slate-600">
                            {{ formatJalaliDateSafe($period->starts_at) }} تا {{ formatJalaliDateSafe($period->ends_at) }}
                        </td>
                        <td class="px-3 py-3">
                            @php
                                $statusLabels = [
                                    'draft' => 'پیش‌نویس',
                                    'calculated' => 'محاسبه‌شده',
                                    'approved' => 'تاییدشده',
                                    'closed' => 'بسته‌شده',
                                ];
                                $statusClasses = [
                                    'draft' => 'bg-gray-100 text-gray-700',
                                    'calculated' => 'bg-blue-100 text-blue-700',
                                    'approved' => 'bg-green-100 text-green-700',
                                    'closed' => 'bg-slate-100 text-slate-700',
                                ];
                            @endphp
                            <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $statusClasses[$period->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $statusLabels[$period->status] ?? $period->status }}
                            </span>
                        </td>
                        <td class="px-3 py-3 text-slate-700">{{ number_format($period->monthly_attendances_count) }} نفر</td>
                        <td class="px-3 py-3 text-slate-700">{{ number_format($period->payroll_calculations_count) }} فیش</td>
                        <td class="px-3 py-3 font-semibold text-slate-900">{{ number_format((float) $period->payroll_calculations_sum_net_payable) }}</td>
                        <td class="px-3 py-3">
                            <div class="flex flex-col gap-2 sm:flex-row">
                                <button type="button" wire:click="calculate({{ $period->id }})" wire:confirm="محاسبه مجدد این دوره انجام شود؟" wire:loading.attr="disabled" wire:target="calculate({{ $period->id }})" class="rounded-md bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                                    محاسبه
                                </button>

                                @if(in_array($period->status, ['draft', 'calculated'], true))
                                    <button type="button" wire:click="deletePeriod({{ $period->id }})" wire:confirm="این دوره حذف شود؟ اطلاعات محاسبه‌شده هم پاک می‌شود." wire:loading.attr="disabled" wire:target="deletePeriod({{ $period->id }})" class="rounded-md bg-red-600 px-3 py-2 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-60">
                                        حذف
                                    </button>
                                @endif

                                @if($period->status === 'calculated')
                                    <button type="button" wire:click="approve({{ $period->id }})" wire:confirm="دوره حقوق تایید شود؟" wire:loading.attr="disabled" wire:target="approve({{ $period->id }})" class="rounded-md bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-700 disabled:opacity-60">
                                        تایید
                                    </button>
                                @endif

                                @if($period->status === 'approved')
                                    <button type="button" wire:click="close({{ $period->id }})" wire:confirm="بعد از بستن دوره، محاسبه مجدد انجام نمی‌شود. ادامه می‌دهید؟" wire:loading.attr="disabled" wire:target="close({{ $period->id }})" class="rounded-md bg-slate-700 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:opacity-60">
                                        بستن
                                    </button>
                                @endif

                                @if($period->status === 'closed')
                                    <span class="text-xs font-semibold text-slate-500">دوره بسته شده است</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-3 py-8 text-center text-slate-500">هنوز دوره حقوقی ثبت نشده است.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $periods->links() }}</div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <h3 class="mb-4 text-lg font-bold text-slate-900">آیتم‌های پایه حقوق، مزایا و کسورات</h3>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach($items as $item)
                <div class="rounded-lg border border-gray-200 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-900">{{ $item->title }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $item->code }}</div>
                        </div>
                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $item->calculation_type === 'reference' ? 'bg-slate-100 text-slate-700' : ($item->type === 'earning' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700') }}">
                            {{ $item->calculation_type === 'reference' ? 'مرجع' : ($item->type === 'earning' ? 'مزایا' : 'کسورات') }}
                        </span>
                    </div>
                    <div class="mt-3 text-sm text-slate-700">
                        @if($item->calculation_type === 'percentage')
                            {{ rtrim(rtrim(number_format((float) $item->default_rate, 4), '0'), '.') }} درصد
                        @else
                            {{ number_format((float) $item->default_amount) }} ریال
                        @endif
                    </div>
                    @if($item->source_title)
                        <div class="mt-2 text-xs text-slate-500">{{ $item->source_title }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900">ریز محاسبات دوره انتخاب‌شده</h3>
                <p class="mt-1 text-sm text-slate-500">این جدول خروجی نهایی موتور جدید را برای ماه انتخاب‌شده نشان می‌دهد.</p>
            </div>
            <div class="text-sm text-slate-500">{{ number_format($calculations->count()) }} رکورد</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">پرسنل</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">کارکرد</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">حقوق ناخالص</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">کسورات</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">خالص</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">بستانکاری</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">سند حسابداری</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">پرداخت</th>
                    <th class="px-3 py-3 text-right font-semibold text-slate-700">پیش‌فیش</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                @forelse($calculations as $calculation)
                    @php
                        $paidAmount = (float) ($calculation->payments_sum_amount ?? $calculation->payments->sum('amount'));
                        $remainingAmount = max(0, (float) $calculation->net_payable - $paidAmount);
                        $payableCredit = (float) ($calculation->accountingEntry?->salary_payable_credit ?? $calculation->net_payable);
                    @endphp
                    <tr>
                        <td class="px-3 py-3 font-semibold text-slate-900">{{ $calculation->employee->full_name }}</td>
                        <td class="px-3 py-3 text-slate-700">
                            {{ number_format((float) $calculation->attendance?->normal_hours, 2) }} عادی
                            /
                            {{ number_format((float) $calculation->attendance?->overtime_hours, 2) }} اضافه‌کاری
                        </td>
                        <td class="px-3 py-3 text-slate-700">{{ number_format((float) $calculation->gross_salary) }}</td>
                        <td class="px-3 py-3 text-slate-700">{{ number_format((float) $calculation->total_deductions) }}</td>
                        <td class="px-3 py-3 font-semibold text-slate-900">
                            {{ number_format((float) $calculation->net_payable) }}
                            <div class="mt-1 text-xs {{ $calculation->status === 'failed' ? 'text-red-700' : 'text-green-700' }}">
                                {{ $calculation->status === 'failed' ? 'ناموفق' : 'محاسبه شده' }}
                            </div>
                            @if($calculation->failure_reason)
                                <div class="mt-1 max-w-xs text-xs leading-5 text-red-700">{{ $calculation->failure_reason }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-slate-700">
                            <div class="font-semibold text-slate-900">{{ number_format($payableCredit) }}</div>
                            <div class="mt-1 text-xs text-slate-500">
                                {{ $remainingAmount > 0 ? 'مانده برای پرداخت: ' . number_format($remainingAmount) : 'تسویه شده' }}
                            </div>
                        </td>
                        <td class="px-3 py-3 text-slate-700">
                            {{ $calculation->accountingEntry?->entry_number ?: '-' }}
                            <div class="text-xs text-slate-500">{{ $calculation->accountingEntry?->status ?: 'ثبت نشده' }}</div>
                        </td>
                        <td class="px-3 py-3 text-slate-700">
                            @if($calculation->payments->isNotEmpty())
                                <div class="font-semibold text-emerald-700">{{ number_format($paidAmount) }}</div>
                                <div class="mt-1 text-xs text-slate-500">
                                    سند: {{ $calculation->payments->first()?->accountingDocument?->number ?: '-' }}
                                </div>
                            @else
                                <span class="text-xs text-slate-500">هنوز پرداخت نشده</span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-slate-700">
                            {{ $calculation->payslip?->number ?: '-' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-8 text-center text-slate-500">برای ماه انتخاب‌شده هنوز محاسبه‌ای وجود ندارد.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900">پرداخت حقوق</h3>
                <p class="mt-1 text-sm text-slate-500">پرداخت را جدا از محاسبه ثبت کنید تا سند حسابداری پرداخت فقط بعد از تسویه صادر شود.</p>
            </div>
            <div class="text-sm text-slate-500">
                {{ number_format($calculations->filter(fn ($calculation) => $calculation->status !== 'failed' && ((float) $calculation->net_payable - (float) ($calculation->payments_sum_amount ?? $calculation->payments->sum('amount'))) > 0)->count()) }} مورد باز
            </div>
        </div>

        <div class="space-y-4">
            @forelse($calculations->filter(fn ($calculation) => $calculation->status !== 'failed' && ((float) $calculation->net_payable - (float) ($calculation->payments_sum_amount ?? $calculation->payments->sum('amount'))) > 0) as $calculation)
                @php
                    $paymentAmount = (float) $calculation->net_payable - (float) ($calculation->payments_sum_amount ?? $calculation->payments->sum('amount'));
                @endphp
                <form wire:key="payroll-payment-form-{{ $calculation->id }}" wire:submit.prevent="registerPayment({{ $calculation->id }})" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <div class="font-semibold text-slate-900">{{ $calculation->employee->full_name }}</div>
                            <div class="mt-1 text-sm text-slate-600">
                                دوره {{ $calculation->period?->persian_title }} -
                                مانده پرداخت: <span class="font-semibold text-emerald-700">{{ number_format($paymentAmount) }}</span>
                            </div>
                            <div class="mt-1 text-xs text-slate-500">
                                بستانکاری ثبت‌شده: {{ number_format((float) ($calculation->accountingEntry?->salary_payable_credit ?? $calculation->net_payable)) }}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 xl:min-w-[760px]">
                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">تاریخ پرداخت</span>
                                <input type="text"
                                       wire:model.defer="paymentDrafts.{{ $calculation->id }}.payment_date"
                                       value="{{ data_get($paymentDrafts, $calculation->id . '.payment_date', todayJalaliDate()) }}"
                                       data-jalali-datepicker
                                       inputmode="numeric"
                                       dir="ltr"
                                       placeholder="1405/03/17"
                                       class="mt-1 w-full rounded-md border-gray-300 text-right">
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">روش</span>
                                <select wire:model.defer="paymentDrafts.{{ $calculation->id }}.method" class="mt-1 w-full rounded-md border-gray-300 text-right">
                                    <option value="bank">بانکی</option>
                                    <option value="cash">نقدی</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">مبلغ</span>
                                <input type="number"
                                       step="1"
                                       min="1"
                                       wire:model.defer="paymentDrafts.{{ $calculation->id }}.amount"
                                       value="{{ $paymentAmount }}"
                                       class="mt-1 w-full rounded-md border-gray-300 text-right">
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-slate-700">شماره مرجع</span>
                                <input type="text" wire:model.defer="paymentDrafts.{{ $calculation->id }}.reference_number" class="mt-1 w-full rounded-md border-gray-300 text-right" placeholder="اختیاری">
                            </label>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <label class="block flex-1">
                            <span class="text-sm font-medium text-slate-700">توضیحات</span>
                            <input type="text" wire:model.defer="paymentDrafts.{{ $calculation->id }}.description" class="mt-1 w-full rounded-md border-gray-300 text-right" placeholder="پرداخت کامل / بخشی">
                        </label>

                        <button type="submit" wire:loading.attr="disabled" wire:target="registerPayment({{ $calculation->id }})" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                            <span wire:loading.remove wire:target="registerPayment({{ $calculation->id }})">ثبت پرداخت</span>
                            <span wire:loading wire:target="registerPayment({{ $calculation->id }})">در حال ثبت...</span>
                        </button>
                    </div>
                </form>
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 p-4 text-sm text-slate-500">برای ثبت پرداخت، ابتدا باید محاسبه حقوق انجام شود.</div>
            @endforelse
        </div>
    </div>
</div>

