<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('تراکنش های مالی') }}
        </h2>
    </x-slot>
    <div class="py-12">
    <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto">
        <h2 class="text-2xl font-bold mb-6">ثبت تراکنش مالی جدید</h2>

        <form method="POST" action="{{ route('financial-transactions.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="project_id" class="block text-sm font-medium text-gray-700 mb-2">پروژه *</label>
                    <select id="project_id" name="project_id" required
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">انتخاب پروژه</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-2">نوع تراکنش *</label>
                    <select id="type" name="type" required
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">انتخاب نوع</option>
                        <option value="income">درآمد</option>
                        <option value="expense">هزینه</option>
                    </select>
                </div>
            </div>

            <div class="mb-4">
                <label for="category" class="block text-sm font-medium text-gray-700 mb-2">دسته‌بندی *</label>
                <select id="category" name="category" required
                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">انتخاب دسته‌بندی</option>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">مبلغ (ریال) *</label>
                    <input type="number" id="amount" name="amount" required step="1000"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="transaction_date" class="block text-sm font-medium text-gray-700 mb-2">تاریخ تراکنش *</label>
                    <input type="text" id="transaction_date" name="transaction_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ old('transaction_date') ? jalaliDateInputValue(old('transaction_date')) : todayJalaliDate() }}" required
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="mb-4">
                <label for="reference_number" class="block text-sm font-medium text-gray-700 mb-2">شماره مرجع</label>
                <input type="text" id="reference_number" name="reference_number"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="شماره فاکتور، شماره قرارداد و...">
            </div>

            <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">شرح تراکنش</label>
                <textarea id="description" name="description" rows="3"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="توضیحات مربوط به تراکنش..."></textarea>
            </div>

            <div class="flex gap-4">
                <a href="{{ route('financial-transactions.index') }}"
                   class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded transition duration-200">
                    انصراف
                </a>
                <button type="submit"
                        class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded transition duration-200">
                    ثبت تراکنش
                </button>
            </div>
        </form>
    </div>

    <script>
        // تغییر دسته‌بندی‌ها بر اساس نوع تراکنش
        const typeSelect = document.getElementById('type');
        const categorySelect = document.getElementById('category');
        const categories = @json($categories);

        typeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            categorySelect.innerHTML = '<option value="">انتخاب دسته‌بندی</option>';

            if (selectedType && categories[selectedType]) {
                categories[selectedType].forEach(category => {
                    const option = document.createElement('option');
                    option.value = category;
                    option.textContent = category;
                    categorySelect.appendChild(option);
                });
            }
        });

        // تنظیم تاریخ امروز به صورت پیش‌فرض
        if (!document.getElementById('transaction_date').value) {
            document.getElementById('transaction_date').value = '{{ todayJalaliDate() }}';
        }
    </script>

    </div>
</x-app-layout>
