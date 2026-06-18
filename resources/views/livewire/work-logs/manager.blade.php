<div>
    @if(session('success'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="mb-4 flex gap-2 flex-wrap justify-end">
        <a href="{{ route('worklog.import.view') }}" wire:navigate class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition duration-200">
            ورود اکسل
        </a>
        <a href="{{ route('worklog.template') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition duration-200">
            فایل نمونه
        </a>
        <a href="{{ route('worklog.export') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition duration-200">
            خروجی اکسل
        </a>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">کارکرد پرسنل</h2>
            <a href="{{ route('work-logs.create') }}" wire:navigate class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition duration-200">
                ثبت کارکرد جدید
            </a>
        </div>

        <div class="erp-ui-filter-bar">
            <div class="erp-filter-row erp-filter-row-5">
                <label class="erp-filter-field">
                    جست‌وجوی پرسنل
                    <input type="text" wire:model.live.debounce.400ms="employee"
                           placeholder="نام، نام خانوادگی، کد ملی">
                </label>

                <label class="erp-filter-field">
                    پروژه
                    <select wire:model.live="project">
                        <option value="">همه پروژه‌ها</option>
                        @foreach($projects as $projectItem)
                            <option value="{{ $projectItem->id }}">{{ $projectItem->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="erp-filter-field">
                    از تاریخ
                    <input type="text" wire:model.live.debounce.500ms="startDateFa"
                           inputmode="numeric" dir="ltr"
                           placeholder="1403/01/01">
                </label>

                <label class="erp-filter-field">
                    تا تاریخ
                    <input type="text" wire:model.live.debounce.500ms="endDateFa"
                           inputmode="numeric" dir="ltr"
                           placeholder="1403/12/29">
                </label>

                <div class="erp-filter-actions">
                    <button type="button" wire:click="clearFilters" class="erp-filter-reset">
                        پاک کردن
                    </button>
                </div>
            </div>
        </div>

        <div wire:loading.delay wire:target="employee,project,startDateFa,endDateFa,clearFilters,updateProject,removeWorkLog" class="mb-3 text-sm text-slate-500">
            در حال به‌روزرسانی...
        </div>

        @if($workLogs->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full border-collapse border border-gray-300">
                    <thead class="bg-gray-200">
                    <tr>
                        <th class="border border-gray-300 p-3">تاریخ</th>
                        <th class="border border-gray-300 p-3">پرسنل</th>
                        <th class="border border-gray-300 p-3">پروژه</th>
                        <th class="border border-gray-300 p-3">ساعت کار</th>
                        <th class="border border-gray-300 p-3">زمان کار</th>
                        <th class="border border-gray-300 p-3">عملیات</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($workLogs as $workLog)
                        <tr class="hover:bg-gray-50" wire:key="work-log-{{ $workLog->id }}">
                            <td class="border border-gray-300 p-3">{{ verta($workLog->work_date)->format('Y/m/d') }}</td>
                            <td class="border border-gray-300 p-3">{{ $workLog->employee->full_name }}</td>
                            <td class="border border-gray-300 p-3 min-w-48">
                                <select wire:change="updateProject({{ $workLog->id }}, $event.target.value)" class="w-full border border-gray-300 rounded-lg px-2 py-1">
                                    @foreach($projects as $projectItem)
                                        <option value="{{ $projectItem->id }}" @selected($workLog->project_id == $projectItem->id)>
                                            {{ $projectItem->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="border border-gray-300 p-3 font-semibold">{{ number_format($workLog->hours, 1) }} ساعت</td>
                            <td class="border border-gray-300 p-3">{{ $workLog->time_range }}</td>
                            <td class="border border-gray-300 p-3">
                                <div class="flex gap-2">
                                    <a href="{{ route('work-logs.show', $workLog) }}" wire:navigate class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                        جزئیات
                                    </a>
                                    <a href="{{ route('work-logs.edit', $workLog) }}" wire:navigate class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                        ویرایش
                                    </a>
                                    <button type="button"
                                            wire:click="removeWorkLog({{ $workLog->id }})"
                                            wire:confirm="آیا از حذف این کارکرد مطمئن هستید؟"
                                            wire:loading.attr="disabled"
                                            wire:target="removeWorkLog({{ $workLog->id }})"
                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                        <span wire:loading.remove wire:target="removeWorkLog({{ $workLog->id }})">حذف</span>
                                        <span wire:loading wire:target="removeWorkLog({{ $workLog->id }})">...</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $workLogs->links() }}
            </div>
        @else
            <div class="text-center py-8">
                <p class="text-gray-500 text-lg">کارکردی پیدا نشد.</p>
                <a href="{{ route('work-logs.create') }}" wire:navigate class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded transition duration-200">
                    ثبت اولین کارکرد
                </a>
            </div>
        @endif
    </div>
</div>
