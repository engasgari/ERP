<div class="exec-dashboard" wire:loading.class="exec-dashboard--loading">
    <div class="exec-loading-bar" wire:loading wire:loading.class="exec-loading-bar--active" aria-hidden="true"></div>

    <header class="exec-hero exec-animate-hero">
        <div class="exec-hero__copy">
            <p class="exec-hero__eyebrow">مرکز کنترل مدیریت</p>
            <h1 class="exec-hero__title">داشبورد مدیرعامل</h1>
            <p class="exec-hero__meta">
                <span class="exec-pill">{{ $dashboard['period']['label'] ?? '' }}</span>
                <span>{{ gregorianToJalaliDate($dashboard['period']['date_from']) }} — {{ gregorianToJalaliDate($dashboard['period']['date_to']) }}</span>
                <span class="exec-hero__time">{{ formatJalaliDateTime($dashboard['generated_at']) }}</span>
            </p>
        </div>
        <div class="exec-hero__aside">
            @if(auth()->user()?->hasPermission('reports.sales.view'))
                <a href="{{ route('sales.intelligence') }}" wire:navigate class="exec-hero__cta">داشبورد فروش</a>
            @endif
            <div class="exec-toolbar" role="group" aria-label="بازه زمانی">
                <label class="exec-toolbar__field">
                    <span class="exec-toolbar__label">بازه</span>
                    <select wire:model.live="periodPreset" class="exec-toolbar__select">
                        @foreach($presets as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                @if($periodPreset === 'custom')
                    <label class="exec-toolbar__field">
                        <span class="exec-toolbar__label">از</span>
                        <input type="text" wire:model.live.debounce.500ms="customDateFrom" placeholder="1404/01/01" class="exec-toolbar__input" dir="ltr">
                    </label>
                    <label class="exec-toolbar__field">
                        <span class="exec-toolbar__label">تا</span>
                        <input type="text" wire:model.live.debounce.500ms="customDateTo" placeholder="1404/12/29" class="exec-toolbar__input" dir="ltr">
                    </label>
                @endif
            </div>
        </div>
    </header>

    @php
        $countKpiKeys = ['active_customers', 'active_projects'];
        $headlineKpis = collect($dashboard['kpis'] ?? [])->filter(fn ($kpi) => in_array($kpi['key'] ?? '', $countKpiKeys, true))->values();
        $metricKpis = collect($dashboard['kpis'] ?? [])->reject(fn ($kpi) => in_array($kpi['key'] ?? '', $countKpiKeys, true))->values();
    @endphp

    @if(! empty($dashboard['alerts']) || $headlineKpis->isNotEmpty())
        <div class="exec-status-strip exec-animate-alert" aria-label="وضعیت سریع">
            @if(! empty($dashboard['alerts']))
                <section class="exec-alerts exec-alerts--compact">
                    <span class="exec-alerts__label">نیازمند توجه</span>
                    <div class="exec-alerts__chips">
                        @foreach($dashboard['alerts'] as $alert)
                            @if(! empty($alert['url']))
                                <a href="{{ $alert['url'] }}" wire:navigate class="exec-alerts__chip exec-alerts__chip--{{ $alert['tone'] ?? 'info' }}">
                            @else
                                <span class="exec-alerts__chip exec-alerts__chip--{{ $alert['tone'] ?? 'info' }}">
                            @endif
                                <span class="exec-alerts__chip-title">{{ $alert['title'] }}</span>
                                <span class="exec-alerts__chip-msg">{{ $alert['message'] }}</span>
                            @if(! empty($alert['url']))
                                </a>
                            @else
                                </span>
                            @endif
                        @endforeach
                    </div>
                </section>
            @endif
            @if($headlineKpis->isNotEmpty())
                <div class="exec-count-pills">
                    @foreach($headlineKpis as $kpi)
                        @if(! empty($kpi['url']))
                            <a href="{{ $kpi['url'] }}" wire:navigate class="exec-count-pill exec-count-pill--{{ $kpi['key'] }}">
                        @else
                            <div class="exec-count-pill exec-count-pill--{{ $kpi['key'] }}">
                        @endif
                            <span class="exec-count-pill__label">{{ $kpi['title'] }}</span>
                            <span class="exec-count-pill__value">{{ number_format((float) ($kpi['value'] ?? 0)) }}</span>
                        @if(! empty($kpi['url']))
                            </a>
                        @else
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if($metricKpis->isNotEmpty())
        <section class="exec-kpi-grid" aria-label="شاخص‌های کلیدی">
            @foreach($metricKpis as $kpi)
                <x-executive-dashboard.kpi-card :kpi="$kpi" :stagger="$loop->index" />
            @endforeach
        </section>
    @else
        <div class="exec-empty">
            <p>برای این حساب شاخص مالی فعالی تعریف نشده است.</p>
            <p class="exec-empty__hint">دسترسی «گزارش فروش» یا «مالی» را بررسی کنید.</p>
        </div>
    @endif

    @if(isset($dashboard['charts']['sales_trend']) || isset($dashboard['charts']['sales_by_category']) || isset($dashboard['charts']['sales_by_customer']))
        <section class="exec-sales-mosaic exec-animate-panel" aria-label="داشبورد فروش">
            @if(isset($dashboard['charts']['sales_trend']))
                <div class="exec-charts-compact-tile exec-charts-compact-tile--monthly">
                    <x-executive-dashboard.column-chart
                        title="روند فروش ماه‌های شمسی"
                        variant="compact"
                        :subtitle="! empty($dashboard['charts']['sales_fiscal_label']) ? 'سال مالی '.$dashboard['charts']['sales_fiscal_label'] : 'از اول سال تا امروز'"
                        :points="$dashboard['charts']['sales_trend']"
                    />
                </div>
            @endif
            @if(isset($dashboard['charts']['sales_by_category']))
                <div class="exec-charts-compact-tile">
                    <x-executive-dashboard.bar-chart
                        title="فروش بر اساس گروه کالا"
                        variant="category"
                        :subtitle="! empty($dashboard['charts']['sales_fiscal_label']) ? 'سال مالی '.$dashboard['charts']['sales_fiscal_label'] : null"
                        :points="$dashboard['charts']['sales_by_category'] ?? []"
                    />
                </div>
            @endif
            @if(isset($dashboard['charts']['sales_by_customer']))
                <div class="exec-charts-compact-tile">
                    <x-executive-dashboard.bar-chart
                        title="فروش بر اساس مشتری"
                        variant="category"
                        :subtitle="! empty($dashboard['charts']['sales_fiscal_label']) ? 'سال مالی '.$dashboard['charts']['sales_fiscal_label'].' — ۴ مشتری اول' : '۴ مشتری اول'"
                        :points="$dashboard['charts']['sales_by_customer'] ?? []"
                    />
                </div>
            @endif
        </section>
    @endif

    @if(! empty($dashboard['charts']['profit_monthly']))
        <div class="exec-charts-split exec-animate-panel">
            <x-executive-dashboard.monthly-profit-chart
                title="سود و هزینه ماهانه"
                :subtitle="! empty($dashboard['charts']['sales_fiscal_label']) ? 'سال مالی '.$dashboard['charts']['sales_fiscal_label'].' — فروش، خرید، حقوق و هزینه' : 'فروش، خرید، حقوق و هزینه'"
                :payload="$dashboard['charts']['profit_monthly']"
                :cta-url="route('financial-reports.profit-loss')"
                cta-label="صورت سود و زیان"
            />
        </div>
    @endif

    @if(! empty($dashboard['crm']['pipeline_chart']))
        <div class="exec-crm-chart">
            <x-executive-dashboard.bar-chart title="ارزش Pipeline (CRM)" :points="$dashboard['crm']['pipeline_chart']" />
        </div>
    @endif

    <div class="exec-stats-row exec-animate-panel-group">
        @if($dashboard['financial'])
            <section class="exec-panel exec-animate-panel" style="--exec-panel-i: 0">
                <header class="exec-panel__head">
                    <h3 class="exec-section-title">وضعیت مالی</h3>
                </header>
                @php
                    $bankTotal = (float) ($dashboard['financial']['bank_total'] ?? 0);
                    $cashTotal = (float) ($dashboard['financial']['cash_total'] ?? 0);
                    $receivablesTotal = (float) ($dashboard['receivables']['total_receivables'] ?? 0);
                    $supplierPayables = (float) ($dashboard['payables']['total_payables'] ?? 0);
                    $vatPayable = (float) ($dashboard['statutory_liabilities']['vat_payable'] ?? 0);
                    $insurancePayable = (float) ($dashboard['statutory_liabilities']['insurance_payable'] ?? 0);
                    $financialBalance = $bankTotal + $cashTotal + $receivablesTotal
                        - $supplierPayables - $vatPayable - $insurancePayable;
                @endphp
                <dl class="exec-stat-list">
                    <div class="exec-stat-list__row">
                        <dt>موجودی بانک</dt>
                        <dd><x-executive-dashboard.money :amount="$bankTotal" /></dd>
                    </div>
                    <div class="exec-stat-list__row">
                        <dt>موجودی صندوق</dt>
                        <dd><x-executive-dashboard.money :amount="$cashTotal" /></dd>
                    </div>
                    <div class="exec-stat-list__row">
                        <dt>مطالبات</dt>
                        <dd><x-executive-dashboard.money :amount="$receivablesTotal" /></dd>
                    </div>
                    <div class="exec-stat-list__row">
                        <dt>بدهی تأمین‌کنندگان</dt>
                        <dd><x-executive-dashboard.money :amount="$supplierPayables" /></dd>
                    </div>
                    <div class="exec-stat-list__row">
                        <dt>بدهی ارزش افزوده</dt>
                        <dd><x-executive-dashboard.money :amount="$vatPayable" /></dd>
                    </div>
                    <div class="exec-stat-list__row">
                        <dt>بدهی بیمه</dt>
                        <dd><x-executive-dashboard.money :amount="$insurancePayable" /></dd>
                    </div>
                    <div class="exec-stat-list__row exec-stat-list__row--highlight">
                        <dt>مانده</dt>
                        <dd><x-executive-dashboard.money :amount="$financialBalance" /></dd>
                    </div>
                </dl>
                @if(auth()->user()?->hasPermission('financial.reports.view'))
                    <a href="{{ route('financial-reports.index') }}" wire:navigate class="exec-panel__cta">گزارش‌های مالی</a>
                @endif
            </section>
        @endif

        @if($dashboard['sales'])
            <section class="exec-panel exec-animate-panel" style="--exec-panel-i: 1">
                <header class="exec-panel__head">
                    <h3 class="exec-section-title">فروش دوره</h3>
                </header>
                <dl class="exec-stat-list">
                    <div class="exec-stat-list__row">
                        <dt>تعداد فاکتور</dt>
                        <dd>{{ number_format($dashboard['sales']['totals']['invoice_count']) }}</dd>
                    </div>
                    <div class="exec-stat-list__row">
                        <dt>میانگین مبلغ</dt>
                        <dd><x-executive-dashboard.money :amount="$dashboard['sales']['avg_invoice']" /></dd>
                    </div>
                    <div class="exec-stat-list__row">
                        <dt>مشتری جدید</dt>
                        <dd>{{ number_format($dashboard['sales']['new_customers']) }}</dd>
                    </div>
                    <div class="exec-stat-list__row">
                        <dt>تسویه‌نشده</dt>
                        <dd>{{ number_format($dashboard['sales']['unsettled_count']) }}</dd>
                    </div>
                </dl>
                <a href="{{ route('sales.intelligence') }}" wire:navigate class="exec-panel__cta">داشبورد فروش</a>
            </section>
        @endif

        @if($dashboard['purchase'])
            <section class="exec-panel exec-animate-panel" style="--exec-panel-i: 2">
                <header class="exec-panel__head">
                    <h3 class="exec-section-title">خرید دوره</h3>
                </header>
                <dl class="exec-stat-list">
                    <div class="exec-stat-list__row">
                        <dt>مبلغ خرید</dt>
                        <dd><x-executive-dashboard.money :amount="$dashboard['purchase']['totals']['total_amount']" /></dd>
                    </div>
                    <div class="exec-stat-list__row">
                        <dt>تعداد فاکتور</dt>
                        <dd>{{ number_format($dashboard['purchase']['totals']['invoice_count']) }}</dd>
                    </div>
                    @if($dashboard['purchase']['open_orders'] > 0)
                        <div class="exec-stat-list__row">
                            <dt>سفارش باز</dt>
                            <dd>{{ number_format($dashboard['purchase']['open_orders']) }}</dd>
                        </div>
                    @endif
                </dl>
            </section>
        @endif
    </div>

    <div class="exec-split">
        @if(! empty($dashboard['debtors']))
            <section class="exec-panel exec-panel--table">
                <h3 class="exec-section-title">بیشترین بدهکاران</h3>
                <div class="exec-table-wrap">
                    <table class="exec-table">
                        <thead>
                            <tr>
                                <th>مشتری</th>
                                <th>مانده</th>
                                <th>فاکتور</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($dashboard['debtors'] as $row)
                                <tr>
                                    <td class="exec-table__name">{{ $row['party_name'] }}</td>
                                    <td class="exec-table__money"><x-executive-dashboard.money :amount="$row['outstanding_amount']" /></td>
                                    <td>{{ $row['invoice_count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>

    @if(! empty($dashboard['projects']['items']))
        <section class="exec-panel exec-panel--table">
            <div class="exec-panel__head exec-panel__head--split">
                <h3 class="exec-section-title">پروژه‌های در جریان</h3>
                <a href="{{ route('projects.index') }}" wire:navigate class="exec-panel__cta exec-panel__cta--inline">همه پروژه‌ها</a>
            </div>
            <div class="exec-table-wrap">
                <table class="exec-table exec-table--wide">
                    <thead>
                        <tr>
                            <th>پروژه</th>
                            <th>مشتری</th>
                            <th>وضعیت</th>
                            <th>بودجه</th>
                            <th>درآمد</th>
                            <th>سود</th>
                            <th>مسئول</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($dashboard['projects']['items'] as $project)
                            <tr>
                                <td class="exec-table__name">
                                    <a href="{{ $project['url'] }}" wire:navigate>{{ $project['name'] }}</a>
                                </td>
                                <td>{{ $project['party_name'] ?? '—' }}</td>
                                <td>
                                    <span class="exec-tag">{{ $project['status_label'] }}</span>
                                    @if($project['is_overdue'])
                                        <span class="exec-tag exec-tag--warn">عقب‌افتاده</span>
                                    @endif
                                </td>
                                <td class="exec-table__money"><x-executive-dashboard.money :amount="$project['budget']" /></td>
                                <td class="exec-table__money"><x-executive-dashboard.money :amount="$project['revenue'] ?? 0" /></td>
                                <td class="exec-table__money"><x-executive-dashboard.money :amount="$project['profit'] ?? 0" /></td>
                                <td>{{ $project['manager_name'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($shortcuts->isNotEmpty())
        <section class="exec-panel exec-panel--flat">
            <h3 class="exec-section-title">دسترسی سریع</h3>
            <div class="exec-shortcuts">
                @foreach($shortcuts->take(12) as $shortcut)
                    <a href="{{ $shortcut['url'] }}" wire:navigate class="exec-shortcuts__item">{{ $shortcut['label'] }}</a>
                @endforeach
            </div>
        </section>
    @endif
</div>
