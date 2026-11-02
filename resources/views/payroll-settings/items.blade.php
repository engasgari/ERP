@php
    $toneClasses = [
        'reference' => 'bg-slate-100 text-slate-700',
        'earning' => 'bg-green-50 text-green-700',
        'deduction' => 'bg-red-50 text-red-700',
    ];
    $toneLabels = [
        'reference' => 'مرجع',
        'earning' => 'مزایا',
        'deduction' => 'کسورات',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800">تنظیمات حقوق و دستمزد</h2>
    </x-slot>

    <x-erp.ui.detail-page
        title="آیتم‌های پایه حقوق، مزایا و کسورات"
        description="فهرست آیتم‌های محاسبه حقوق. این بخش از صفحه دوره‌ها جدا شده و اینجا برای مرور تنظیمات در دسترس است."
        :actions="[
            ['label' => 'تنظیمات حسابداری حقوق', 'url' => route('payroll.accounting-settings.index'), 'class' => 'erp-action-detail'],
            ['label' => 'دوره‌های حقوق', 'url' => route('payroll.periods.index'), 'class' => 'erp-action-detail'],
        ]"
    >
        <div class="space-y-6">
            @foreach($itemGroups as $groupKey => $group)
                <section>
                    <h3 class="mb-3 text-base font-bold text-slate-900">{{ $group['label'] }}</h3>
                    @if($group['items']->isEmpty())
                        <div class="rounded-lg border border-dashed border-slate-200 px-4 py-6 text-sm text-slate-500">
                            موردی در این گروه ثبت نشده است.
                        </div>
                    @else
                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                            @foreach($group['items'] as $item)
                                <div class="rounded-lg border border-gray-200 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div>
                                            <div class="font-semibold text-slate-900">{{ $item->title }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $item->code }}</div>
                                        </div>
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $toneClasses[$groupKey] }}">
                                            {{ $toneLabels[$groupKey] }}
                                        </span>
                                    </div>
                                    <div class="mt-3 text-sm text-slate-700">
                                        @if($item->calculation_type === 'percentage')
                                            {{ rtrim(rtrim(formatMoney((float) $item->default_rate, 4), '0'), '.') }} درصد
                                        @else
                                            {{ formatMoney((float) $item->default_amount) }} ریال
                                        @endif
                                    </div>
                                    @if($item->source_title)
                                        <div class="mt-2 text-xs text-slate-500">{{ $item->source_title }}</div>
                                    @endif
                                    <div class="mt-3 flex flex-wrap gap-2 text-[11px] text-slate-500">
                                        @if($item->taxable)
                                            <span class="rounded bg-slate-50 px-2 py-0.5">مشمول مالیات</span>
                                        @endif
                                        @if($item->insurable)
                                            <span class="rounded bg-slate-50 px-2 py-0.5">مشمول بیمه</span>
                                        @endif
                                        @if($item->is_statutory)
                                            <span class="rounded bg-slate-50 px-2 py-0.5">قانونی</span>
                                        @endif
                                        @unless($item->is_active)
                                            <span class="rounded bg-amber-50 px-2 py-0.5 text-amber-700">غیرفعال</span>
                                        @endunless
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endforeach
        </div>
    </x-erp.ui.detail-page>
</x-app-layout>
