<div dir="rtl" class="space-y-6">
    @include('livewire.partials.flash')

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4">
            <h3 class="text-lg font-bold text-slate-900">تخصیص گروه کاری به پرسنل</h3>
            <p class="mt-1 text-sm text-slate-500">گروه کاری مشخص می‌کند هر کارمند با کدام شیفت و تقویم محاسبه حضور و غیاب شود.</p>
        </div>

        <form wire:submit="save" class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
            <label class="block xl:col-span-2">
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
                <span class="text-sm font-medium text-slate-700">گروه کاری</span>
                <select wire:model="work_group_id" class="mt-1 w-full rounded-md border-gray-300 text-right">
                    <option value="">انتخاب کنید</option>
                    @foreach($workGroups as $group)
                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                    @endforeach
                </select>
                @error('work_group_id') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">شروع</span>
                <input wire:model="start_date" placeholder="1404/04/01" class="mt-1 w-full rounded-md border-gray-300 text-right">
                @error('start_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <label class="block">
                <span class="text-sm font-medium text-slate-700">پایان</span>
                <input wire:model="end_date" placeholder="برای تخصیص فعال خالی بگذارید" class="mt-1 w-full rounded-md border-gray-300 text-right">
                @error('end_date') <span class="text-xs text-red-600">{{ $message }}</span> @enderror
            </label>

            <div class="flex items-end xl:col-start-5">
                <button type="submit" class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">ثبت تخصیص</button>
            </div>
        </form>
    </div>

    <div class="rounded-lg bg-white p-4 shadow-md sm:p-6">
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-bold text-slate-900">سوابق تخصیص</h3>
            <input wire:model.live.debounce.400ms="search" placeholder="جستجوی پرسنل یا گروه" class="rounded-md border-gray-300 text-right sm:w-80">
        </div>

        <div class="overflow-x-auto">
            <table class="erp-ui-data-table min-w-full">
                <thead>
                <tr>
                    <th>پرسنل</th>
                    <th>گروه کاری</th>
                    <th>شیفت</th>
                    <th>تقویم</th>
                    <th>شروع</th>
                    <th>پایان</th>
                    <th>عملیات</th>
                </tr>
                </thead>
                <tbody>
                @forelse($assignments as $assignment)
                    <tr wire:key="assignment-{{ $assignment->id }}">
                        <td>{{ $assignment->employee?->full_name }}</td>
                        <td class="font-semibold text-slate-900">{{ $assignment->workGroup?->name }}</td>
                        <td>{{ $assignment->workGroup?->shift?->name ?: '-' }}</td>
                        <td>{{ $assignment->workGroup?->calendar?->name ?: '-' }}</td>
                        <td>{{ formatJalaliDateSafe($assignment->start_date) }}</td>
                        <td>{{ $assignment->end_date ? formatJalaliDateSafe($assignment->end_date) : 'فعال' }}</td>
                        <td>
                            <div class="flex flex-col gap-2 sm:flex-row">
                                @if(! $assignment->end_date)
                                    <button type="button" wire:click="closeAssignment({{ $assignment->id }})" class="erp-action-btn erp-action-edit">بستن</button>
                                @endif
                                <button type="button" wire:click="delete({{ $assignment->id }})" wire:confirm="تخصیص حذف شود؟" class="erp-action-btn erp-action-delete">حذف</button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 text-center text-slate-500">تخصیصی ثبت نشده است.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $assignments->links() }}</div>
    </div>
</div>
