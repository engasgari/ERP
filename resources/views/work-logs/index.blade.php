<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">لیست کارکرد</h2>
    </x-slot>

    <div class="mb-4 flex gap-2 flex-wrap justify-end">
        @can('worklogs.manage')
            <a href="{{ route('worklog.import.view') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition duration-200">
                ورود اکسل
            </a>
        @endcan
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
            @can('worklogs.manage')
                <a href="{{ route('work-logs.create') }}" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition duration-200">
                    ثبت کارکرد جدید
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('work-logs.index') }}" class="erp-ui-filter-bar">
            <div class="erp-filter-row erp-filter-row-5">
                <label class="erp-filter-field">
                    پرسنل
                    <input name="employee" value="{{ request('employee') }}" placeholder="نام، نام خانوادگی، کد ملی">
                </label>

                <label class="erp-filter-field">
                    پروژه
                    <select name="project_id">
                        <option value="">همه پروژه‌ها</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(request('project_id', request('project')) == $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="erp-filter-field">
                    از تاریخ
                    <input name="start_date" value="{{ request('start_date') ? jalaliDateInputValue(request('start_date')) : jalaliDateInputValue(request('start_date_fa')) }}" inputmode="numeric" dir="ltr" placeholder="1403/01/01">
                </label>

                <label class="erp-filter-field">
                    تا تاریخ
                    <input name="end_date" value="{{ request('end_date') ? jalaliDateInputValue(request('end_date')) : jalaliDateInputValue(request('end_date_fa')) }}" inputmode="numeric" dir="ltr" placeholder="1403/12/29">
                </label>

                <label class="erp-filter-field">
                    ساعت از
                    <input name="hours_min" value="{{ request('hours_min') }}" inputmode="decimal" dir="ltr" placeholder="0">
                </label>
            </div>

            <div class="erp-filter-row erp-filter-row-5">
                <label class="erp-filter-field">
                    ساعت تا
                    <input name="hours_max" value="{{ request('hours_max') }}" inputmode="decimal" dir="ltr" placeholder="8">
                </label>

                <x-filter-actions :reset-route="route('work-logs.index')" class="erp-filter-actions-full" />
            </div>
        </form>

        @foreach(array_filter($dateErrors ?? []) as $error)
            <div class="erp-filter-error">{{ $error }}</div>
        @endforeach

        @if($workLogs->count() > 0)
            <div class="overflow-x-auto">
                <table class="erp-ui-data-table w-full border-collapse border border-gray-300">
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
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-300 p-3">{{ verta($workLog->work_date)->format('Y/m/d') }}</td>
                            <td class="border border-gray-300 p-3">{{ $workLog->employee->full_name }}</td>
                            <td class="border border-gray-300 p-3 min-w-48">
                                @can('worklogs.manage')
                                    <form method="POST" action="{{ route('work-logs.update', $workLog) }}">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="employee_id" value="{{ $workLog->employee_id }}">
                                        <input type="hidden" name="work_date" value="{{ gregorianToJalaliDate($workLog->work_date) }}">
                                        <input type="hidden" name="start_time" value="{{ substr((string) $workLog->start_time, 0, 5) }}">
                                        <input type="hidden" name="end_time" value="{{ substr((string) $workLog->end_time, 0, 5) }}">
                                        <input type="hidden" name="description" value="{{ $workLog->description }}">
                                        <select name="project_id" onchange="this.form.submit()">
                                            @foreach($projects as $project)
                                                <option value="{{ $project->id }}" @selected($workLog->project_id == $project->id)>
                                                    {{ $project->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                @else
                                    {{ $workLog->project?->name ?: '-' }}
                                @endcan
                            </td>
                            <td class="border border-gray-300 p-3 font-semibold">{{ number_format($workLog->hours, 1) }} ساعت</td>
                            <td class="border border-gray-300 p-3">{{ $workLog->time_range }}</td>
                            <td class="border border-gray-300 p-3">
                                <div class="flex gap-2 flex-wrap">
                                    <a href="{{ route('work-logs.show', $workLog) }}" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                        جزئیات
                                    </a>
                                    @can('worklogs.manage')
                                        <a href="{{ route('work-logs.edit', $workLog) }}" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm transition duration-200">
                                            ویرایش
                                        </a>
                                        <form action="{{ route('work-logs.destroy', $workLog) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm transition duration-200"
                                                    onclick="return confirm('آیا از حذف این کارکرد مطمئن هستید؟')">
                                                حذف
                                            </button>
                                        </form>
                                    @endcan
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
                @can('worklogs.manage')
                    <a href="{{ route('work-logs.create') }}" class="mt-4 inline-block bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded transition duration-200">
                        ثبت اولین کارکرد
                    </a>
                @endcan
            </div>
        @endif
    </div>
</x-app-layout>
