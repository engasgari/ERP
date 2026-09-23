@php
    $statusLabels = ['open' => 'باز', 'closed' => 'بسته'];
    $editing = $isCreating || filled($editingYear);
    $formAction = $isCreating
        ? route('fiscal-periods.store')
        : ($editingYear ? route('fiscal-periods.update', $editingYear) : null);
@endphp

<x-erp.ui.list-page
    title="دوره‌های مالی"
    description="تعریف سال مالی، بستن سال با اسناد اختتامیه/افتتاحیه و بازگشایی سال‌های قبلی."
    route="fiscal-periods.index"
    :actions="[
        ['label' => 'تعریف سال مالی', 'url' => route('fiscal-periods.index', ['edit' => 'new']), 'class' => 'erp-action-edit'],
    ]"
>
    @if($editing && $formAction)
        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 md:p-5 mb-4 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="font-bold text-slate-900">{{ $isCreating ? 'تعریف سال مالی جدید' : 'ویرایش سال مالی ' . $editingYear->jalali_year }}</h3>
                    <p class="mt-1 text-sm text-slate-500">تاریخ شروع و پایان را وارد کنید. بستن و بازگشایی سال فقط از جدول زیر انجام می‌شود.</p>
                </div>
                <a href="{{ route('fiscal-periods.index') }}" class="erp-action-btn erp-action-detail">انصراف</a>
            </div>

            @if($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    <div class="mb-2 font-bold">لطفاً خطاهای فرم را بررسی کنید:</div>
                    <ul class="list-disc space-y-1 ps-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ $formAction }}" class="erp-filter-row items-end">
                @csrf
                @if(! $isCreating)
                    @method('PUT')
                @endif

                <label class="erp-filter-field md:col-span-2">عنوان
                    <input type="text" name="title" value="{{ old('title', $editingYear?->title) }}" placeholder="مثلاً دوره مالی ۱۴۰۵">
                </label>
                <label class="erp-filter-field">تاریخ شروع
                    <x-erp.ui.jalali-date-input name="start_date" :value="old('start_date', $editingYear ? gregorianToJalaliDate($editingYear->start_date) : '')" placeholder="1405/01/01" required class="w-full" />
                </label>
                <label class="erp-filter-field">تاریخ پایان
                    <x-erp.ui.jalali-date-input name="end_date" :value="old('end_date', $editingYear ? gregorianToJalaliDate($editingYear->end_date) : '')" placeholder="1405/12/29" required class="w-full" />
                </label>
                <label class="erp-filter-field">واحد پول
                    <input type="text" name="currency" value="{{ old('currency', $editingYear?->currency ?: 'IRR') }}">
                </label>
                @if($isCreating)
                    <input type="hidden" name="status" value="open">
                    <label class="erp-filter-field">وضعیت
                        <input type="text" value="باز" disabled class="bg-slate-100">
                    </label>
                @else
                    <label class="erp-filter-field">وضعیت
                        <input type="text" value="{{ $statusLabels[$editingYear->status] ?? $editingYear->status }}" disabled class="bg-slate-100">
                    </label>
                    <input type="hidden" name="status" value="{{ $editingYear->status }}">
                @endif
                <div class="erp-filter-actions">
                    <button type="submit" class="erp-action-btn erp-action-edit">{{ $isCreating ? 'ثبت سال مالی' : 'ذخیره تغییرات' }}</button>
                </div>
            </form>
        </div>
    @endif

    <x-erp.ui.data-table
        :headers="['سال', 'عنوان', 'از تاریخ', 'تا تاریخ', 'ارز', 'وضعیت', 'فعال', 'عملیات']"
        empty-message="هنوز سال مالی ثبت نشده است."
        :colspan="8"
    >
        @foreach($items as $year)
            @php
                $period = $year->periods->first();
                $statusTone = $year->status === 'open' ? 'success' : 'neutral';
            @endphp
            <tr wire:key="fiscal-year-{{ $year->id }}">
                <td class="text-nowrap font-semibold">{{ $year->jalali_year }}</td>
                <td>{{ $year->title }}</td>
                <td class="text-nowrap">{{ gregorianToJalaliDate($year->start_date) }}</td>
                <td class="text-nowrap">{{ gregorianToJalaliDate($year->end_date) }}</td>
                <td>{{ $year->currency }}</td>
                <td>
                    <x-erp.ui.status-badge
                        :label="$statusLabels[$year->status] ?? $year->status"
                        :tone="$statusTone"
                    />
                </td>
                <td>
                    @if($period?->is_active)
                        <x-erp.ui.status-badge label="فعال" tone="info" />
                    @else
                        -
                    @endif
                </td>
                <td>
                    <x-erp.ui.row-actions>
                        <x-erp.ui.row-action icon="edit" label="ویرایش" :href="route('fiscal-periods.index', ['edit' => $year->id])" />
                        @if($period)
                            @if($period->status === 'open')
                                <form method="POST" action="{{ route('fiscal-periods.close', $period) }}"
                                      onsubmit="return confirm('سال مالی {{ $year->jalali_year }} بسته شود؟ اسناد اختتامیه و افتتاحیه سال بعد صادر می‌شود.');">
                                    @csrf
                                    <x-erp.ui.row-action type="submit" icon="close" label="بستن سال" tone="danger" />
                                </form>
                            @else
                                <form method="POST" action="{{ route('fiscal-periods.reopen', $period) }}"
                                      onsubmit="return confirm('سال {{ $year->jalali_year }} بازگشایی شود؟ سال‌های بعدی بدون عملیات حذف می‌شوند.');">
                                    @csrf
                                    <x-erp.ui.row-action type="submit" icon="reopen" label="بازگشایی" />
                                </form>
                            @endif
                        @endif
                        @if($fiscalPeriodService->canDeleteYear($year))
                            <form method="POST" action="{{ route('fiscal-periods.destroy', $year) }}"
                                  onsubmit="return confirm('سال مالی {{ $year->jalali_year }} حذف شود؟');">
                                @csrf
                                @method('DELETE')
                                <x-erp.ui.row-action type="submit" icon="delete" label="حذف" tone="danger" />
                            </form>
                        @endif
                    </x-erp.ui.row-actions>
                </td>
            </tr>
        @endforeach
    </x-erp.ui.data-table>
</x-erp.ui.list-page>
