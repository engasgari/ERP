<x-erp.ui.detail-page

    title="گزارش‌های CRM"

    description="خلاصه عملکرد فروش، سرنخ‌ها و پایپ‌لاین."

    route="crm.reports.index"

>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">

        @foreach([

            ['label' => 'مشتریان', 'value' => number_format($summary['total_customers']), 'pastel' => 'lavender'],

            ['label' => 'کل سرنخ‌ها', 'value' => number_format($summary['total_leads']), 'pastel' => 'peach'],

            ['label' => 'نرخ تبدیل', 'value' => $summary['conversion_rate'].'%', 'pastel' => 'mint'],

            ['label' => 'فرصت باز', 'value' => number_format($summary['open_opportunities']), 'pastel' => 'sky'],

            ['label' => 'برنده', 'value' => number_format($summary['won_opportunities']), 'pastel' => 'sage'],

            ['label' => 'از دست رفته', 'value' => number_format($summary['lost_opportunities']), 'pastel' => 'rose'],

            ['label' => 'ارزش پایپ‌لاین', 'value' => formatMoney($summary['pipeline_value']), 'pastel' => 'blue'],

            ['label' => 'پایپ‌لاین وزنی', 'value' => formatMoney($summary['weighted_pipeline']), 'pastel' => 'indigo'],

        ] as $card)

            <x-crm.stat-card :label="$card['label']" :value="$card['value']" :pastel="$card['pastel']" />

        @endforeach

    </div>



    <div class="grid md:grid-cols-2 gap-6">

        <x-crm.panel title="سرنخ‌ها بر اساس وضعیت" pastel="peach">

            @forelse($leadsByStatus as $status => $total)

                <div class="crm-dashboard-panel__row">

                    <span>{{ $statusLabels[$status] ?? $status }}</span>

                    <span class="font-semibold">{{ number_format($total) }}</span>

                </div>

            @empty

                <x-erp.ui.empty-state message="داده‌ای وجود ندارد." />

            @endforelse

        </x-crm.panel>



        <x-crm.panel title="فرصت‌های باز بر اساس مرحله" pastel="blue">

            @forelse($opportunitiesByStage as $row)

                <div class="crm-dashboard-panel__row">

                    <span>{{ $row['stage'] }}</span>

                    <span class="font-semibold">{{ number_format($row['total']) }} — {{ formatMoney($row['amount']) }}</span>

                </div>

            @empty

                <x-erp.ui.empty-state message="داده‌ای وجود ندارد." />

            @endforelse

        </x-crm.panel>

    </div>

</x-erp.ui.detail-page>

