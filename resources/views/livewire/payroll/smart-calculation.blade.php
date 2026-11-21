<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h2 class="text-xl font-bold text-slate-900">محاسبه هوشمند حقوق</h2>
                <p class="mt-1 max-w-3xl text-sm leading-7 text-slate-500">
                    لیست کارمندان فعال را می‌گیرد، در صورت نیاز کارکرد ماه را بر اساس تقویم کاری (بدون توجه به تردد دستگاه) کامل می‌کند،
                    سپس حقوق را طوری ثبت می‌کند که خالص هر نفر برابر مبلغ هدف شما باشد. بیمه سهم کارمند از روی مزایای مشمول حفظ می‌شود.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('payroll.periods.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    دوره‌های حقوق
                </a>
                <a href="{{ route('insurance.payments.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    پرداخت بیمه
                </a>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">سال</span>
                <select wire:model.live="year" class="mt-1 w-full rounded-md border-gray-300 text-right">
                    @foreach($years as $y)
                        <option value="{{ $y }}">{{ toPersianDigits($y) }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-medium text-slate-700">ماه</span>
                <select wire:model.live="month" class="mt-1 w-full rounded-md border-gray-300 text-right">
                    @foreach($months as $m)
                        <option value="{{ $m }}">{{ toPersianDigits($m) }} — {{ getPersianMonthName($m) }}</option>
                    @endforeach
                </select>
            </label>
            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                <div class="text-xs text-slate-500">روزهای کاری تقویم</div>
                <div class="mt-1 text-lg font-bold text-slate-900">{{ toPersianDigits($workdayCount ?? 0) }}</div>
            </div>
            <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
                <div class="text-xs text-slate-500">وضعیت دوره</div>
                <div class="mt-1 text-sm font-semibold text-slate-900">
                    {{ $period?->title ?? 'هنوز ساخته نشده' }}
                    @if($period)
                        <span class="text-slate-500">({{ $period->status }})</span>
                    @endif
                </div>
            </div>
        </div>

        <label class="mt-4 flex items-start gap-3 rounded-md border border-blue-100 bg-blue-50 px-3 py-3 text-sm text-slate-700">
            <input type="checkbox" wire:model.live="useCalendarAttendance" class="mt-1 rounded border-gray-300 text-blue-600">
            <span>
                <span class="font-semibold">کارکرد کامل بر اساس تقویم</span>
                <span class="mt-1 block text-slate-500">ترددهای ثبت‌شده نادیده گرفته می‌شوند و همه روزهای کاری ماه با ۸ ساعت منظور می‌گردد.</span>
            </span>
        </label>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="selectAll(true)" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">انتخاب همه</button>
                <button type="button" wire:click="selectAll(false)" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">حذف انتخاب</button>
            </div>
            <div class="flex flex-wrap items-end gap-2">
                <label class="block">
                    <span class="text-xs font-medium text-slate-600">اعمال خالص یکسان (ریال) روی انتخاب‌شده‌ها</span>
                    <input type="text" inputmode="numeric" wire:model="defaultNet" placeholder="مثلاً 250000000" class="mt-1 w-56 rounded-md border-gray-300 text-right" />
                </label>
                <button
                    type="button"
                    wire:click="applyDefaultNet"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    اعمال مبلغ
                </button>
                <button
                    type="button"
                    wire:click="run"
                    wire:confirm="محاسبه هوشمند برای افراد انتخاب‌شده اجرا شود؟ کارکرد تقویمی و خالص هدف در دوره ثبت می‌شود."
                    wire:loading.attr="disabled"
                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="run">اجرای محاسبه هوشمند</span>
                    <span wire:loading wire:target="run">در حال محاسبه...</span>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-3 py-3 text-right font-semibold">انتخاب</th>
                        <th class="px-3 py-3 text-right font-semibold">کد</th>
                        <th class="px-3 py-3 text-right font-semibold">نام</th>
                        <th class="px-3 py-3 text-right font-semibold">خالص فعلی دوره</th>
                        <th class="px-3 py-3 text-right font-semibold">خالص هدف (ریال)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $index => $row)
                        <tr wire:key="emp-{{ $row['employee_id'] }}">
                            <td class="px-3 py-3">
                                <input type="checkbox" wire:model.live="rows.{{ $index }}.selected" class="rounded border-gray-300 text-blue-600">
                            </td>
                            <td class="px-3 py-3 text-slate-600">{{ $row['code'] ? toPersianDigits($row['code']) : '—' }}</td>
                            <td class="px-3 py-3 font-medium text-slate-900">{{ $row['name'] }}</td>
                            <td class="px-3 py-3 text-slate-600">
                                {{ $row['current_net'] !== null ? formatMoney($row['current_net']) : '—' }}
                            </td>
                            <td class="px-3 py-3">
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    wire:model.defer="rows.{{ $index }}.target_net"
                                    class="w-full min-w-[10rem] rounded-md border-gray-300 text-right"
                                    placeholder="250000000"
                                >
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-10 text-center text-slate-500">کارمند فعالی یافت نشد.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
