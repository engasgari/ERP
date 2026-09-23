<x-erp.ui.detail-page

    title="داشبورد CRM"

    description="نمای کلی مشتریان، سرنخ‌ها، فرصت‌ها و وظایف."

    route="crm.dashboard"

>

    <x-crm.shortcut-bar :shortcuts="$shortcuts" />

    <div class="crm-dashboard-stats">

        @foreach($statCards as $card)

            <x-crm.stat-card
                :label="$card['label']"
                :value="$card['value']"
                :pastel="$card['pastel']"
                :href="$card['href']"
            />

        @endforeach

    </div>



    <div class="grid md:grid-cols-2 gap-6 mt-6">

        <x-crm.panel
            title="سرنخ‌ها بر اساس منبع"
            pastel="lavender"
            :href="auth()->user()?->hasPermission('crm.leads.view') ? route('crm.leads.index') : null"
        >

            @forelse($charts['leads_by_source'] as $row)

                <div class="crm-dashboard-panel__row">

                    <span>{{ $row->source?->title ?? 'بدون منبع' }}</span>

                    <span class="font-semibold">{{ number_format($row->total) }}</span>

                </div>

            @empty

                <x-erp.ui.empty-state message="داده‌ای برای نمایش وجود ندارد." />

            @endforelse

        </x-crm.panel>



        <x-crm.panel
            title="فرصت‌های باز بر اساس مرحله"
            pastel="sky"
            :href="auth()->user()?->hasPermission('crm.opportunities.view') ? route('crm.pipeline.index') : null"
        >

            @forelse($charts['opportunities_by_stage'] as $row)

                <div class="crm-dashboard-panel__row">

                    <span>{{ $row->stage?->name ?? 'بدون مرحله' }}</span>

                    <span class="font-semibold">{{ number_format($row->total) }} — {{ formatMoney((float) $row->amount) }}</span>

                </div>

            @empty

                <x-erp.ui.empty-state message="داده‌ای برای نمایش وجود ندارد." />

            @endforelse

        </x-crm.panel>

    </div>

</x-erp.ui.detail-page>

