<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">مدیریت کاربران</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-3xl mx-auto">
            <h2 class="mb-5 text-xl font-bold">{{ $user->exists ? 'ویرایش کاربر' : 'کاربر جدید' }}</h2>

            <form method="POST" action="{{ $user->exists ? route('access.users.update', $user) : route('access.users.store') }}">
                @csrf
                @if($user->exists)
                    @method('PUT')
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="grid gap-1 text-sm font-bold text-gray-700">
                        نام
                        <input name="name" value="{{ old('name', $user->name) }}" required class="rounded border-gray-300">
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-gray-700">
                        ایمیل
                        <input name="email" value="{{ old('email', $user->email) }}" required type="email" dir="ltr" class="rounded border-gray-300">
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-gray-700 md:col-span-2">
                        رمز عبور {{ $user->exists ? '(در صورت نیاز به تغییر پر کنید)' : '' }}
                        <input name="password" type="password" {{ $user->exists ? '' : 'required' }} class="rounded border-gray-300">
                    </label>
                </div>

                <div class="mt-5">
                    <div class="mb-2 text-sm font-bold text-gray-700">نقش‌ها</div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                        @foreach($roles as $role)
                            <label class="flex items-center gap-2 rounded bg-gray-50 px-3 py-2 text-sm">
                                <input type="checkbox" name="roles[]" value="{{ $role->id }}" @checked(in_array($role->id, old('roles', $selectedRoles)))>
                                <span>{{ $role->title }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="mt-6 flex gap-2">
                    <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded" type="submit">ذخیره</button>
                    <a href="{{ route('access.users.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">بازگشت</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
