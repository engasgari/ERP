<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">مدیریت نقش‌ها</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-4xl mx-auto">
            <h2 class="mb-5 text-xl font-bold">{{ $role->exists ? 'ویرایش نقش' : 'نقش جدید' }}</h2>

            <form method="POST" action="{{ $role->exists ? route('access.roles.update', $role) : route('access.roles.store') }}">
                @csrf
                @if($role->exists)
                    @method('PUT')
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="grid gap-1 text-sm font-bold text-gray-700">
                        کلید نقش
                        <input name="name" value="{{ old('name', $role->name) }}" required dir="ltr" @disabled($role->is_system) class="rounded border-gray-300">
                        @if($role->is_system)
                            <input type="hidden" name="name" value="{{ $role->name }}">
                        @endif
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-gray-700">
                        عنوان
                        <input name="title" value="{{ old('title', $role->title) }}" required class="rounded border-gray-300">
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-gray-700 md:col-span-2">
                        توضیحات
                        <textarea name="description" class="rounded border-gray-300">{{ old('description', $role->description) }}</textarea>
                    </label>
                </div>

                <div class="mt-5">
                    <div class="mb-2 text-sm font-bold text-gray-700">مجوزها</div>
                    @foreach($permissions as $group => $groupPermissions)
                        @php
                            $primaryKeys = match ($group) {
                                'بازرگانی' => \App\Support\CommercePermissions::primaryKeys(),
                                'انبار' => \App\Support\InventoryPermissions::primaryKeys(),
                                'پروژه‌ها' => \App\Support\ProjectPermissions::primaryKeys(),
                                'پرسنل' => \App\Support\EmployeePermissions::primaryKeys(),
                                'حضور و غیاب' => \App\Support\AttendancePermissions::primaryKeys(),
                                'حقوق و دستمزد' => \App\Support\PayrollPermissions::primaryKeys(),
                                'کارکرد' => \App\Support\WorklogPermissions::primaryKeys(),
                                'منابع انسانی' => \App\Support\HrPermissions::primaryKeys(),
                                'خزانه' => \App\Support\TreasuryPermissions::primaryKeys(),
                                default => [],
                            };
                            $actionHint = match ($group) {
                                'بازرگانی' => 'مجوزهای جزئی فاکتور و قیمت‌گذاری',
                                'انبار' => 'مجوزهای جزئی اسناد و تعریف انبار',
                                'پروژه‌ها' => 'مجوزهای جزئی پروژه',
                                'پرسنل' => 'مجوزهای جزئی پرسنل',
                                'حضور و غیاب' => 'مجوزهای جزئی حضور، مرخصی و ماموریت',
                                'حقوق و دستمزد' => 'مجوزهای جزئی محاسبه، تایید، پرداخت و بیمه',
                                'کارکرد' => 'مجوزهای جزئی ثبت، ویرایش و تنظیمات کارکرد',
                                'منابع انسانی' => 'مجوزهای جزئی احکام، قرارداد و ساختار سازمانی',
                                'خزانه' => 'مجوزهای جزئی تراکنش خزانه و حساب شرکا',
                                default => null,
                            };
                            $primaryPermissions = $primaryKeys !== []
                                ? $groupPermissions
                                    ->filter(fn ($permission) => in_array($permission->key, $primaryKeys, true))
                                    ->sortBy(fn ($permission) => array_search($permission->key, $primaryKeys, true))
                                    ->values()
                                : collect();
                            $actionPermissions = $primaryKeys !== []
                                ? $groupPermissions
                                    ->reject(fn ($permission) => in_array($permission->key, $primaryKeys, true))
                                    ->sortBy('title')
                                    ->values()
                                : $groupPermissions;
                        @endphp
                        <section class="mb-3 rounded bg-gray-50 p-3">
                            <h3 class="mb-2 font-bold">{{ $group }}</h3>
                            @if($primaryKeys !== [])
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    @foreach($primaryPermissions as $permission)
                                        <label class="flex items-center gap-2 text-sm font-semibold">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->name === 'admin' || in_array($permission->id, old('permissions', $selectedPermissions))) @disabled($role->name === 'admin')>
                                            <span>{{ $permission->title }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @if($actionPermissions->isNotEmpty())
                                    <div class="mt-5 border-t border-gray-200 pt-4">
                                        <div class="mb-2 text-xs font-medium text-gray-500">{{ $actionHint }}</div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                            @foreach($actionPermissions as $permission)
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->name === 'admin' || in_array($permission->id, old('permissions', $selectedPermissions))) @disabled($role->name === 'admin')>
                                                    <span>{{ $permission->title }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @else
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    @foreach($actionPermissions as $permission)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->name === 'admin' || in_array($permission->id, old('permissions', $selectedPermissions))) @disabled($role->name === 'admin')>
                                            <span>{{ $permission->title }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endforeach
                </div>

                <div class="mt-6 flex gap-2">
                    <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" type="submit">ذخیره</button>
                    <a href="{{ route('access.roles.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">بازگشت</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
