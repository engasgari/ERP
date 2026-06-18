<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">{{ $order->exists ? 'ویرایش سفارش تولید' : 'ثبت سفارش تولید' }}</h2></x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl rounded-lg bg-white p-6 shadow">
            @if($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-3 text-sm font-semibold text-red-700">
                    @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ $order->exists ? route('production-orders.update', $order) : route('production-orders.store') }}" class="space-y-4">
                @csrf
                @if($order->exists) @method('PUT') @endif

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        پروژه *
                        <select name="project_id" required class="rounded-md border-slate-300">
                            <option value="">انتخاب پروژه</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected(old('project_id', $order->project_id) == $project->id)>{{ $project->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        محصول *
                        <select name="item_id" required class="rounded-md border-slate-300">
                            <option value="">انتخاب محصول</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @selected(old('item_id', $order->item_id) == $product->id)>{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        فرمول ساخت
                        <select name="bom_version_id" class="rounded-md border-slate-300">
                            <option value="">بدون BOM</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" @selected(old('bom_version_id', $order->bom_version_id) == $bom->id)>{{ $bom->item?->name }} - نسخه {{ $bom->version_number }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        تعداد تولید *
                        <input name="quantity" type="number" step="0.001" min="0.001" dir="ltr" required value="{{ old('quantity', $order->quantity ?: 1) }}" class="rounded-md border-slate-300">
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        وضعیت
                        <select name="status" class="rounded-md border-slate-300">
                            <option value="draft" @selected(old('status', $order->status) === 'draft')>پیش‌نویس</option>
                            <option value="approved" @selected(old('status', $order->status) === 'approved')>تایید شده</option>
                            <option value="in_progress" @selected(old('status', $order->status) === 'in_progress')>در حال تولید</option>
                            <option value="testing" @selected(old('status', $order->status) === 'testing')>کنترل کیفیت</option>
                            <option value="completed" @selected(old('status', $order->status) === 'completed')>تکمیل شده</option>
                            <option value="closed" @selected(old('status', $order->status) === 'closed')>بسته شده</option>
                        </select>
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        شروع برنامه‌ای
                        <input name="planned_start_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ jalaliDateInputValue(old('planned_start_date'), $order->planned_start_date) }}" class="rounded-md border-slate-300">
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        پایان برنامه‌ای
                        <input name="planned_end_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ jalaliDateInputValue(old('planned_end_date'), $order->planned_end_date) }}" class="rounded-md border-slate-300">
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        شروع واقعی
                        <input name="actual_start_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ jalaliDateInputValue(old('actual_start_date'), $order->actual_start_date) }}" class="rounded-md border-slate-300">
                    </label>
                    <label class="grid gap-1 text-sm font-bold text-slate-600">
                        پایان واقعی
                        <input name="actual_end_date" inputmode="numeric" dir="ltr" placeholder="1403/03/17" value="{{ jalaliDateInputValue(old('actual_end_date'), $order->actual_end_date) }}" class="rounded-md border-slate-300">
                    </label>
                </div>

                <label class="grid gap-1 text-sm font-bold text-slate-600">
                    توضیحات
                    <textarea name="description" rows="3" class="rounded-md border-slate-300">{{ old('description', $order->description) }}</textarea>
                </label>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('production-orders.index') }}" class="rounded-md bg-slate-500 px-4 py-2 text-sm font-bold text-white">بازگشت</a>
                    <button class="rounded-md bg-blue-600 px-4 py-2 text-sm font-bold text-white">ذخیره سفارش</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
