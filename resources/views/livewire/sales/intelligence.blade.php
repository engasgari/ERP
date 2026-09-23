@props([])

<div class="sales-intel" wire:loading.class="sales-intel--loading">
    <header class="sales-intel__hero">
        <div>
            <p class="sales-intel__eyebrow">هوش فروش ERP</p>
            <h1 class="sales-intel__title">داشبورد فروش</h1>
            <p class="sales-intel__meta">
                @if(! empty($dashboard['fiscal_year']['title']))
                    <span>سال مالی {{ $dashboard['fiscal_year']['title'] }}</span>
                @endif
                <span>{{ formatJalaliDateTime($dashboard['generated_at']) }}</span>
            </p>
        </div>
        <div class="sales-intel__actions">
            <a href="{{ $dashboard['links']['sales'] }}" wire:navigate class="sales-intel__link">گزارش فروش</a>
            <a href="{{ $dashboard['links']['profitability'] }}" wire:navigate class="sales-intel__link">سودآوری</a>
            <a href="{{ $dashboard['links']['receivables'] }}" wire:navigate class="sales-intel__link">مطالبات</a>
        </div>
    </header>

    @if(! empty($dashboard['alerts']))
        <section class="sales-intel__alerts" aria-label="هشدارهای هوشمند">
            @foreach($dashboard['alerts'] as $alert)
                @if(! empty($alert['url']))
                    <a href="{{ $alert['url'] }}" wire:navigate class="sales-intel__alert sales-intel__alert--{{ $alert['tone'] }}">
                @else
                    <div class="sales-intel__alert sales-intel__alert--{{ $alert['tone'] }}">
                @endif
                    <strong>{{ $alert['title'] }}</strong>
                    <span>{{ $alert['message'] }}</span>
                @if(! empty($alert['url']))
                    </a>
                @else
                    </div>
                @endif
            @endforeach
        </section>
    @endif

    <section class="sales-intel__kpis" aria-label="شاخص‌های فروش">
        @foreach($dashboard['kpis'] as $kpi)
            @php
                $tag = ! empty($kpi['url']) ? 'a' : 'div';
                $href = ! empty($kpi['url']) ? 'href="'.$kpi['url'].'" wire:navigate' : '';
            @endphp
            <{{ $tag }} {!! $href !!} class="sales-intel__kpi">
                <div class="sales-intel__kpi-title">{{ $kpi['title'] }}</div>
                <div class="sales-intel__kpi-value">
                    @if($kpi['format'] === 'percent')
                        {{ number_format((float) $kpi['value'], 1) }}%
                    @elseif($kpi['format'] === 'number')
                        {{ number_format((float) $kpi['value']) }}
                    @else
                        <x-executive-dashboard.money :amount="$kpi['value']" />
                    @endif
                </div>
                @if($kpi['delta_percent'] !== null && $kpi['key'] === 'sales_month')
                    <div class="sales-intel__kpi-delta {{ $kpi['delta_percent'] >= 0 ? 'is-up' : 'is-down' }}">
                        {{ $kpi['delta_percent'] >= 0 ? '+' : '' }}{{ number_format((float) $kpi['delta_percent'], 1) }}% نسبت به ماه قبل
                    </div>
                @endif
            </{{ $tag }}>
        @endforeach
    </section>

    <div class="sales-intel__grid">
        <section class="sales-intel__panel">
            <h2>تحلیل هوشمند امروز</h2>
            <p class="sales-intel__brief-head">{{ $dashboard['ai_brief']['headline'] ?? '' }}</p>
            <ul class="sales-intel__brief">
                @foreach($dashboard['ai_brief']['bullets'] ?? [] as $bullet)
                    <li>{{ $bullet }}</li>
                @endforeach
            </ul>
            <form wire:submit.prevent="ask" class="sales-intel__ask">
                <label for="sales-q">از داده‌های فروش سؤال بپرسید</label>
                <div class="sales-intel__ask-row">
                    <input id="sales-q" type="text" wire:model="question" placeholder="مثلاً حاشیه سود این ماه چقدر است؟" />
                    <button type="submit">بپرس</button>
                </div>
                @if($answer)
                    <p class="sales-intel__answer">{{ $answer }}</p>
                @endif
            </form>
        </section>

        <section class="sales-intel__panel">
            <h2>تحلیل سود (این ماه)</h2>
            <dl class="sales-intel__stats">
                <div><dt>فروش ناخالص (با VAT)</dt><dd><x-executive-dashboard.money :amount="$dashboard['profit_analysis']['gross_sales']" /></dd></div>
                <div><dt>فروش خالص</dt><dd><x-executive-dashboard.money :amount="$dashboard['profit_analysis']['net_sales']" /></dd></div>
                <div><dt>تخفیف</dt><dd><x-executive-dashboard.money :amount="$dashboard['profit_analysis']['discount']" /></dd></div>
                <div><dt>VAT</dt><dd><x-executive-dashboard.money :amount="$dashboard['profit_analysis']['vat']" /></dd></div>
                <div><dt>بهای تمام‌شده</dt><dd><x-executive-dashboard.money :amount="$dashboard['profit_analysis']['cogs']" /></dd></div>
                <div class="is-accent"><dt>سود ناخالص</dt><dd><x-executive-dashboard.money :amount="$dashboard['profit_analysis']['gross_profit']" /></dd></div>
                <div><dt>حاشیه سود</dt><dd>{{ number_format((float) $dashboard['profit_analysis']['margin_percent'], 1) }}%</dd></div>
                <div><dt>وصولی</dt><dd><x-executive-dashboard.money :amount="$dashboard['profit_analysis']['collected']" /></dd></div>
                <div><dt>مانده فاکتورهای دوره</dt><dd><x-executive-dashboard.money :amount="$dashboard['profit_analysis']['outstanding']" /></dd></div>
            </dl>
            <a href="{{ $dashboard['links']['profitability'] }}" wire:navigate class="sales-intel__link">جزئیات سودآوری →</a>
        </section>
    </div>

    <div class="sales-intel__grid sales-intel__grid--charts">
        <section class="sales-intel__panel">
            <x-executive-dashboard.column-chart
                title="روند فروش ماه‌های شمسی"
                variant="compact"
                :subtitle="! empty($dashboard['fiscal_year']['title']) ? 'سال مالی '.$dashboard['fiscal_year']['title'] : null"
                :points="$dashboard['charts']['monthly_trend']"
            />
        </section>
        <section class="sales-intel__panel">
            <x-executive-dashboard.bar-chart
                title="فروش بر اساس گروه کالا"
                variant="category"
                :points="$dashboard['charts']['by_category']"
            />
        </section>
        <section class="sales-intel__panel">
            <x-executive-dashboard.bar-chart
                title="۵ مشتری برتر"
                variant="category"
                :points="$dashboard['charts']['top_customers']"
            />
        </section>
        <section class="sales-intel__panel">
            <x-executive-dashboard.bar-chart
                title="Aging مطالبات"
                variant="category"
                :points="$dashboard['charts']['receivable_aging']"
            />
        </section>
    </div>
</div>
