@php
    $maxTrend = collect($charts['monthly_trend'] ?? [])->max('value') ?: 1;
    $maxCategory = collect($charts['by_category'] ?? [])->max('value') ?: 1;
    $maxCustomer = collect($charts['top_customers'] ?? [])->max('value') ?: 1;
    $maxSalesperson = collect($charts['salespeople'] ?? [])->max('value') ?: 1;
@endphp

@if(! empty($summary))
    <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
        @foreach([
            'sales_today' => 'فروش امروز',
            'sales_this_month' => 'فروش این ماه',
            'sales_fiscal_year' => ($summary_labels['sales_fiscal_year'] ?? null) ?: 'کل فروش سال مالی جاری',
            'invoice_count' => 'تعداد صورتحساب این ماه',
            'fiscal_year_invoice_count' => isset($fiscal_year['title']) ? 'تعداد صورتحساب سال مالی ' . $fiscal_year['title'] : 'تعداد صورتحساب سال مالی',
            'paid_amount' => 'مبلغ وصول‌شده (این ماه)',
            'outstanding_amount' => 'مانده مطالبات',
            'gross_profit' => 'سود ناخالص (سال مالی)',
            'margin_percent' => 'حاشیه سود (سال مالی)',
        ] as $key => $label)
            @if(array_key_exists($key, $summary))
                <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
                    <div class="text-xs font-bold text-slate-500">{{ $label }}</div>
                    <div class="mt-2 text-lg font-black text-slate-900">
                        @if($key === 'margin_percent')
                            {{ number_format((float) $summary[$key], 1) }}%
                        @elseif($key === 'invoice_count')
                            {{ number_format((int) $summary[$key]) }}
                        @else
                            {{ formatMoney((float) $summary[$key]) }}
                        @endif
                    </div>
                </div>
            @endif
        @endforeach
    </section>
@endif

<div class="grid gap-6 xl:grid-cols-2">
    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-4 text-base font-black text-slate-900">روند فروش ماهانه</h3>
        <div class="space-y-2">
            @forelse($charts['monthly_trend'] ?? [] as $point)
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-16 shrink-0 font-mono text-slate-500">{{ $point['label'] }}</div>
                    <div class="h-3 flex-1 rounded-full bg-slate-100">
                        <div class="h-3 rounded-full bg-primary-500" style="width: {{ min(100, ($point['value'] / $maxTrend) * 100) }}%"></div>
                    </div>
                    <div class="w-28 shrink-0 text-left font-mono text-xs">{{ formatMoney($point['value']) }}</div>
                </div>
            @empty
                <p class="text-sm text-slate-500">داده‌ای یافت نشد.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-4 text-base font-black text-slate-900">فروش بر اساس گروه کالا</h3>
        <div class="space-y-2">
            @forelse($charts['by_category'] ?? [] as $point)
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-24 shrink-0 truncate" title="{{ $point['label'] }}">{{ $point['label'] }}</div>
                    <div class="h-3 flex-1 rounded-full bg-slate-100">
                        <div class="h-3 rounded-full bg-emerald-500" style="width: {{ min(100, ($point['value'] / $maxCategory) * 100) }}%"></div>
                    </div>
                    <div class="w-28 shrink-0 text-left font-mono text-xs">{{ formatMoney($point['value']) }}</div>
                </div>
            @empty
                <p class="text-sm text-slate-500">داده‌ای یافت نشد.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-4 text-base font-black text-slate-900">۱۰ مشتری برتر</h3>
        <div class="space-y-2">
            @forelse($charts['top_customers'] ?? [] as $point)
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-32 shrink-0 truncate" title="{{ $point['label'] }}">{{ $point['label'] }}</div>
                    <div class="h-3 flex-1 rounded-full bg-slate-100">
                        <div class="h-3 rounded-full bg-amber-500" style="width: {{ min(100, ($point['value'] / $maxCustomer) * 100) }}%"></div>
                    </div>
                    <div class="w-28 shrink-0 text-left font-mono text-xs">{{ formatMoney($point['value']) }}</div>
                </div>
            @empty
                <p class="text-sm text-slate-500">داده‌ای یافت نشد.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
        <h3 class="mb-4 text-base font-black text-slate-900">عملکرد فروشندگان</h3>
        <div class="space-y-2">
            @forelse($charts['salespeople'] ?? [] as $point)
                <div class="flex items-center gap-3 text-sm">
                    <div class="w-32 shrink-0 truncate" title="{{ $point['label'] }}">{{ $point['label'] }}</div>
                    <div class="h-3 flex-1 rounded-full bg-slate-100">
                        <div class="h-3 rounded-full bg-violet-500" style="width: {{ min(100, ($point['value'] / $maxSalesperson) * 100) }}%"></div>
                    </div>
                    <div class="w-28 shrink-0 text-left font-mono text-xs">{{ formatMoney($point['value']) }}</div>
                </div>
            @empty
                <p class="text-sm text-slate-500">داده‌ای یافت نشد.</p>
            @endforelse
        </div>
    </section>
</div>
