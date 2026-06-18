<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    @if(! $employee)
        <div class="rounded-lg bg-white p-6 shadow-md">
            <h3 class="text-lg font-bold text-slate-900">سلف‌سرویس پرسنلی</h3>
            <p class="mt-2 text-sm leading-7 text-slate-600">
                برای کاربر فعلی پرونده پرسنلی متصل پیدا نشد. مدیر سیستم می‌تواند از بخش دسترسی‌ها، کارمند مجاز را به کاربر وصل کند یا ایمیل کاربر را با ایمیل پرونده پرسنلی یکسان کند.
            </p>
        </div>
    @else
        <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-xl font-bold text-slate-900">{{ $employee->full_name }}</h3>
                    <p class="mt-1 text-sm text-slate-500">کد پرسنلی: {{ $employee->employee_code ?: $employee->personnel_code ?: '-' }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm font-semibold text-slate-700">{{ $employee->employment_status }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
                <h3 class="mb-4 text-lg font-bold text-slate-900">درخواست مرخصی</h3>
                <form wire:submit="requestLeave" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <select wire:model.live="leave_request_type" class="rounded-md border-gray-300 text-right">
                        <option value="hourly">ساعتی</option>
                        <option value="daily">روزانه</option>
                    </select>
                    <input wire:model="leave_start_date" placeholder="تاریخ شروع 1404/04/01" class="rounded-md border-gray-300 text-right">
                    <input wire:model="leave_end_date" placeholder="تاریخ پایان" class="rounded-md border-gray-300 text-right">
                    @if($leave_request_type === 'hourly')
                        <input type="time" wire:model="leave_start_time" class="rounded-md border-gray-300 text-right">
                        <input type="time" wire:model="leave_end_time" class="rounded-md border-gray-300 text-right">
                        <input type="number" step="0.25" wire:model="leave_hours" placeholder="مدت ساعت" class="rounded-md border-gray-300 text-right">
                    @else
                        <input type="number" step="0.5" wire:model="leave_total_days" placeholder="تعداد روز" class="rounded-md border-gray-300 text-right">
                    @endif
                    <input wire:model="leave_reason" placeholder="علت" class="rounded-md border-gray-300 text-right sm:col-span-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 sm:col-span-2">ثبت مرخصی</button>
                </form>
            </div>

            <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
                <h3 class="mb-4 text-lg font-bold text-slate-900">درخواست ماموریت</h3>
                <form wire:submit="requestMission" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <select wire:model.live="mission_request_type" class="rounded-md border-gray-300 text-right">
                        <option value="hourly">ساعتی</option>
                        <option value="daily">روزانه</option>
                    </select>
                    <input wire:model="mission_destination" placeholder="مقصد" class="rounded-md border-gray-300 text-right">
                    <input wire:model="mission_start_date" placeholder="تاریخ شروع 1404/04/01" class="rounded-md border-gray-300 text-right">
                    <input wire:model="mission_end_date" placeholder="تاریخ پایان" class="rounded-md border-gray-300 text-right">
                    @if($mission_request_type === 'hourly')
                        <input type="time" wire:model="mission_start_time" class="rounded-md border-gray-300 text-right">
                        <input type="time" wire:model="mission_end_time" class="rounded-md border-gray-300 text-right">
                        <input type="number" step="0.25" wire:model="mission_hours" placeholder="مدت ساعت" class="rounded-md border-gray-300 text-right">
                    @else
                        <input type="number" step="0.5" wire:model="mission_total_days" placeholder="تعداد روز" class="rounded-md border-gray-300 text-right">
                    @endif
                    <input wire:model="mission_description" placeholder="شرح" class="rounded-md border-gray-300 text-right sm:col-span-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 sm:col-span-2">ثبت ماموریت</button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
                <h3 class="mb-4 text-lg font-bold text-slate-900">مانده مرخصی</h3>
                @forelse($balances as $balance)
                    <div class="mb-3 rounded-md border border-slate-200 p-3">
                        <div class="font-semibold text-slate-900">{{ $balance->year }}</div>
                        <div class="mt-1 text-sm text-slate-600">مانده: {{ number_format((float) $balance->remaining_days, 2) }} روز</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">مانده‌ای ثبت نشده است.</p>
                @endforelse
            </div>

            <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
                <h3 class="mb-4 text-lg font-bold text-slate-900">کارکردهای اخیر</h3>
                @forelse($summaries as $summary)
                    <div class="mb-3 rounded-md border border-slate-200 p-3 text-sm">
                        <div class="font-semibold text-slate-900">{{ getPersianMonthName($summary->month) }} {{ $summary->year }}</div>
                        <div class="mt-1 text-slate-600">کارکرد: {{ number_format($summary->worked_minutes / 60, 2) }} ساعت</div>
                        <div class="text-slate-600">غیبت: {{ number_format($summary->absence_minutes / 60, 2) }} ساعت</div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">کارکردی ثبت نشده است.</p>
                @endforelse
            </div>

            <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
                <h3 class="mb-4 text-lg font-bold text-slate-900">فیش‌های اخیر</h3>
                @forelse($payslips as $calculation)
                    <div class="mb-3 rounded-md border border-slate-200 p-3 text-sm">
                        <div class="font-semibold text-slate-900">{{ $calculation->period?->persian_title ?: '-' }}</div>
                        <div class="mt-1 text-slate-600">خالص: {{ number_format((float) $calculation->net_payable) }}</div>
                        @if($calculation->payslip)
                            <a class="mt-2 inline-block text-blue-600" href="{{ route('payslips.print', $calculation->payslip) }}" target="_blank">مشاهده فیش</a>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">فیشی ثبت نشده است.</p>
                @endforelse
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
                <h3 class="mb-4 text-lg font-bold text-slate-900">درخواست‌های مرخصی اخیر</h3>
                @forelse($leaves as $leave)
                    <div class="mb-3 flex items-center justify-between rounded-md border border-slate-200 p-3 text-sm">
                        <span>{{ formatJalaliDateSafe($leave->start_date ?: $leave->leave_date) }}</span>
                        @include('livewire.attendance.partials.status-badge', ['status' => $leave->status])
                    </div>
                @empty
                    <p class="text-sm text-slate-500">درخواستی ثبت نشده است.</p>
                @endforelse
            </div>

            <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
                <h3 class="mb-4 text-lg font-bold text-slate-900">درخواست‌های ماموریت اخیر</h3>
                @forelse($missions as $mission)
                    <div class="mb-3 flex items-center justify-between rounded-md border border-slate-200 p-3 text-sm">
                        <span>{{ formatJalaliDateSafe($mission->start_date ?: $mission->mission_date) }} - {{ $mission->destination ?: 'ماموریت' }}</span>
                        @include('livewire.attendance.partials.status-badge', ['status' => $mission->status])
                    </div>
                @empty
                    <p class="text-sm text-slate-500">درخواستی ثبت نشده است.</p>
                @endforelse
            </div>
        </div>
    @endif
</div>
