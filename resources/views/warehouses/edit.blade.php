<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('پروژه ها') }}
        </h2>
    </x-slot>
    <div class="py-12">
    <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto">
        <h2 class="text-2xl font-bold mb-6">ویرایش انبار</h2>

        <form method="POST" action="{{ route('warehouses.update', $warehouse) }}">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">نام انبار *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $warehouse->name) }}" required
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('name')
                <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror
            </div>

            <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">توضیحات</label>
                <textarea id="description" name="description" rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('description', $warehouse->description) }}</textarea>
                @error('description')
                <span class="text-red-500 text-sm">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-center mb-6">
                <input type="checkbox" id="is_active" name="is_active" value="1"
                       {{ old('is_active', $warehouse->is_active) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="is_active" class="mr-2 text-sm text-gray-700">فعال</label>
            </div>

            <div class="flex gap-4">
                <a href="{{ route('warehouses.index') }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded transition duration-200">
                    انصراف
                </a>
                <button type="submit"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded transition duration-200">
                    به‌روزرسانی انبار
                </button>
            </div>
        </form>

        <!-- بخش حذف انبار -->
        <div class="mt-8 pt-6 border-t border-gray-200">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <h3 class="font-semibold text-red-800 mb-2">حذف انبار</h3>
                <p class="text-sm text-red-700 mb-4">
                    توجه: با حذف این انبار، تمام تراکنش‌های مربوطه نیز حذف خواهند شد.
                    این عمل غیرقابل بازگشت است.
                </p>
                <form action="{{ route('warehouses.destroy', $warehouse) }}" method="POST"
                      onsubmit="return confirm('آیا از حذف این انبار مطمئن هستید؟ تمام تراکنش‌های مربوطه نیز حذف خواهند شد.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded text-sm transition duration-200">
                        حذف انبار
                    </button>
                </form>
            </div>
        </div>
    </div>
    </div>
</x-app-layout>
