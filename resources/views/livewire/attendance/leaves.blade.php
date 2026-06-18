<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4">
            <h3 class="text-lg font-bold text-slate-900">ثبت درخواست مرخصی</h3>
            <p class="mt-1 text-sm text-slate-500">درخواست بعد از ثبت در وضعیت انتظار تایید قرار می‌گیرد و فقط مرخصی تایید شده وارد خلاصه کارکرد و حقوق می‌شود.</p>
        </div>

        <form wire:submit="save" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="block">
                <span class="text-sm font-medium text-slate-700">پرسنل</span>
                <select wire:model="employee_id" class="mt-1 w-full rounded-md border-gray-300 text-right">
                    <option value="">انتخاب کنید</option>
                    @foreach($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->employee_code }} - {{ $employee->full_name }}</option>
                    @endforeach
                </select>
                @error('employee_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">نوع درخواست</span>
                <select wire:model.live="request_type" class="mt-1 w-full rounded-md border-gray-300 text-right">
                    <option value="hourly">ساعتی</option>
                    <option value="daily">روزانه</option>
                </select>
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">از تاریخ</span>
                <input wire:model="start_date" placeholder="1404/04/01" class="mt-1 w-full rounded-md border-gray-300 text-right">
                @error('start_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">تا تاریخ</span>
                <input wire:model="end_date" placeholder="برای یک روز خالی بگذارید" class="mt-1 w-full rounded-md border-gray-300 text-right">
            </label>

            @if($request_type === 'hourly')
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">ساعت شروع</span>
                    <input type="time" wire:model="start_time" class="mt-1 w-full rounded-md border-gray-300 text-right">
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">ساعت پایان</span>
                    <input type="time" wire:model="end_time" class="mt-1 w-full rounded-md border-gray-300 text-right">
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">مدت ساعت</span>
                    <input type="number" step="0.25" wire:model="hours" placeholder="در صورت نداشتن ساعت شروع/پایان" class="mt-1 w-full rounded-md border-gray-300 text-right">
                </label>
            @else
                <label class="block">
                    <span class="text-sm font-medium text-slate-700">تعداد روز</span>
                    <input type="number" step="0.5" wire:model="total_days" placeholder="در صورت خالی، خودکار محاسبه می‌شود" class="mt-1 w-full rounded-md border-gray-300 text-right">
                </label>
            @endif

            <label class="block">
                <span class="text-sm font-medium text-slate-700">نوع مرخصی</span>
                <input wire:model="leave_type" class="mt-1 w-full rounded-md border-gray-300 text-right">
            </label>

            <label class="block md:col-span-2 xl:col-span-3">
                <span class="text-sm font-medium text-slate-700">علت</span>
                <input wire:model="reason" class="mt-1 w-full rounded-md border-gray-300 text-right">
            </label>

            <div class="flex items-end">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-60">
                    ثبت درخواست
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h3 class="text-lg font-bold text-slate-900">درخواست‌های مرخصی</h3>
                <p class="mt-1 text-sm text-slate-500">تایید این جدول روی مانده مرخصی و محاسبه حقوق اثر می‌گذارد.</p>
            </div>
            <form wire:submit.prevent class="grid grid-cols-1 gap-2 sm:grid-cols-4 lg:min-w-[680px]">
                <input wire:model.live.debounce.400ms="search" placeholder="جستجوی پرسنل" class="rounded-md border-gray-300 text-right sm:col-span-2">
                <select wire:model.live="status" class="rounded-md border-gray-300 text-right">
                    <option value="">همه وضعیت‌ها</option>
                    <option value="pending">در انتظار تایید</option>
                    <option value="approved">تایید شده</option>
                    <option value="rejected">رد شده</option>
                    <option value="cancelled">لغو شده</option>
                </select>
                <select wire:model.live="type" class="rounded-md border-gray-300 text-right">
                    <option value="">همه انواع</option>
                    <option value="hourly">ساعتی</option>
                    <option value="daily">روزانه</option>
                </select>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full">
                <thead>
                <tr>
                    <th>پرسنل</th>
                    <th>نوع</th>
                    <th>بازه</th>
                    <th>مدت</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($leaves as $leave)
                    <tr wire:key="leave-{{ $leave->id }}">
                        <td>{{ $leave->employee?->full_name }}</td>
                        <td>{{ $leave->request_type === 'daily' ? 'روزانه' : 'ساعتی' }}</td>
                        <td>{{ formatJalaliDateSafe($leave->start_date ?: $leave->leave_date) }} تا {{ formatJalaliDateSafe($leave->end_date ?: $leave->leave_date) }}</td>
                        <td>
                            @if($leave->request_type === 'daily')
                                {{ number_format((float) $leave->total_days, 2) }} روز
                            @else
                                {{ number_format(((int) $leave->duration_minutes) / 60, 2) }} ساعت
                            @endif
                        </td>
                        <td>@include('livewire.attendance.partials.status-badge', ['status' => $leave->status])</td>
                        <td>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                @if($leave->status === 'pending')
                                    <button type="button" wire:click="approve({{ $leave->id }})" class="erp-action-btn erp-action-edit">تایید</button>
                                    <button type="button" wire:click="reject({{ $leave->id }})" class="erp-action-btn erp-action-delete">رد</button>
                                    <button type="button" wire:click="cancel({{ $leave->id }})" class="erp-action-btn">لغو</button>
                                @else
                                    <span class="text-xs text-slate-500">اقدام فعال ندارد</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-6 text-center text-slate-500">درخواستی ثبت نشده است.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $leaves->links() }}</div>
    </div>
</div>
