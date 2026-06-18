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
                        <section class="mb-3 rounded bg-gray-50 p-3">
                            <h3 class="mb-2 font-bold">{{ $group }}</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                @foreach($groupPermissions as $permission)
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->name === 'admin' || in_array($permission->id, old('permissions', $selectedPermissions))) @disabled($role->name === 'admin')>
                                        <span>{{ $permission->title }}</span>
                                    </label>
                                @endforeach
                            </div>
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
