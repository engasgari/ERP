<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">ویرایش تراکنش مالی</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6 max-w-2xl mx-auto">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-2xl font-bold">ویرایش تراکنش</h2>
                <a href="{{ route('financial-transactions.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded transition duration-200">بازگشت</a>
            </div>

            <form method="POST" action="{{ route('financial-transactions.update', $financialTransaction) }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="project_id" class="block text-sm font-medium text-gray-700 mb-2">پروژه *</label>
                        <select id="project_id" name="project_id" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected(old('project_id', $financialTransaction->project_id) == $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700 mb-2">نوع *</label>
                        <select id="type" name="type" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                            <option value="income" @selected(old('type', $financialTransaction->type) === 'income')>درآمد</option>
                            <option value="expense" @selected(old('type', $financialTransaction->type) === 'expense')>هزینه</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-2">دسته بندی *</label>
                        <input id="category" name="category" value="{{ old('category', $financialTransaction->category) }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>

                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">مبلغ (ریال) *</label>
                        <input type="number" id="amount" name="amount" value="{{ old('amount', $financialTransaction->amount) }}" required step="1000" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="transaction_date" class="block text-sm font-medium text-gray-700 mb-2">تاریخ *</label>
                        <input type="text" id="transaction_date" name="transaction_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ jalaliDateInputValue(old('transaction_date'), $financialTransaction->transaction_date) }}" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>

                    <div>
                        <label for="reference_number" class="block text-sm font-medium text-gray-700 mb-2">شماره مرجع</label>
                        <input id="reference_number" name="reference_number" value="{{ old('reference_number', $financialTransaction->reference_number) }}" class="w-full border border-gray-300 rounded-md px-3 py-2">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">شرح</label>
                    <textarea id="description" name="description" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2">{{ old('description', $financialTransaction->description) }}</textarea>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded transition duration-200">ذخیره تغییرات</button>
                    <a href="{{ route('financial-transactions.index') }}" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded transition duration-200">انصراف</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
