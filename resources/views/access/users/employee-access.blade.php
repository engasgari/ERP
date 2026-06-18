<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">دسترسی کارمندها</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h2 class="text-xl font-bold">دسترسی‌های {{ $user->name }}</h2>
                <a href="{{ route('access.users.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-3 py-2 rounded">بازگشت</a>
            </div>

            <form method="POST" action="{{ route('access.users.employee-access.update', $user) }}">
                @csrf
                @method('PUT')
                <div class="overflow-x-auto">
                    <table class="erp-ui-data-table w-full">
                        <thead>
                        <tr>
                            <th>کارمند</th>
                            <th>مشاهده کارکرد</th>
                            <th>مدیریت کارکرد</th>
                            <th>مشاهده حقوق</th>
                            <th>مدیریت حقوق</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($employees as $employee)
                            @php($access = $accesses->get($employee->id))
                            <tr>
                                <td>
                                    {{ $employee->full_name }}
                                    <input type="hidden" name="employees[{{ $employee->id }}][employee_id]" value="{{ $employee->id }}">
                                </td>
                                <td><input type="checkbox" name="employees[{{ $employee->id }}][can_view_work_logs]" value="1" @checked($access?->can_view_work_logs)></td>
                                <td><input type="checkbox" name="employees[{{ $employee->id }}][can_manage_work_logs]" value="1" @checked($access?->can_manage_work_logs)></td>
                                <td><input type="checkbox" name="employees[{{ $employee->id }}][can_view_salaries]" value="1" @checked($access?->can_view_salaries)></td>
                                <td><input type="checkbox" name="employees[{{ $employee->id }}][can_manage_salaries]" value="1" @checked($access?->can_manage_salaries)></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <button class="mt-4 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" type="submit">ذخیره دسترسی‌ها</button>
            </form>
        </div>
    </div>
</x-app-layout>
