<div
    class="exec-spatial"
    wire:loading.class="exec-dashboard--loading"
    x-data="{
        page: 1,
        liquidityOpen: false,
        receivablesOpen: false,
        payablesOpen: false,
        openLiquidity() { this.receivablesOpen = false; this.payablesOpen = false; this.liquidityOpen = true; },
        closeLiquidity() { this.liquidityOpen = false; },
        openReceivables() { this.liquidityOpen = false; this.payablesOpen = false; this.receivablesOpen = true; },
        closeReceivables() { this.receivablesOpen = false; },
        openPayables() { this.liquidityOpen = false; this.receivablesOpen = false; this.payablesOpen = true; },
        closePayables() { this.payablesOpen = false; },
        closeAll() { this.liquidityOpen = false; this.receivablesOpen = false; this.payablesOpen = false; },
        go(n) {
            this.page = n;
            this.closeAll();
            this.$nextTick(() => this.syncNotch());
        },
        syncNotch() {
            const orb = this.$refs.dock?.querySelector('.exec-spatial__orb.is-active');
            const panel = this.$refs.panel;
            if (!orb || !panel) return;
            const orbBox = orb.getBoundingClientRect();
            const panelBox = panel.getBoundingClientRect();
            if (panelBox.height < 1 || panelBox.width < 1) return;
            const isMobile = window.matchMedia('(max-width: 640px)').matches;
            if (isMobile) {
                const x = ((orbBox.left + orbBox.width / 2) - panelBox.left) / panelBox.width * 100;
                panel.style.setProperty('--exec-notch-x', `${x}%`);
                panel.style.setProperty('--exec-notch-y', '100%');
            } else {
                const y = ((orbBox.top + orbBox.height / 2) - panelBox.top) / panelBox.height * 100;
                panel.style.setProperty('--exec-notch-y', `${y}%`);
                panel.style.setProperty('--exec-notch-x', '0%');
            }
        },
        init() {
            this.$nextTick(() => this.syncNotch());
            window.addEventListener('resize', () => this.syncNotch());
        },
    }"
    @keydown.escape.window="closeAll()"
>
    @php
        $spatialPages = [
            1 => [
                'label' => 'فروش',
                'icon' => 'sales',
            ],
            2 => [
                'label' => 'سود و هزینه',
                'icon' => 'profit',
            ],
            3 => [
                'label' => 'وضعیت مالی',
                'icon' => 'finance',
            ],
            4 => [
                'label' => 'پروژه‌ها',
                'icon' => 'projects',
            ],
        ];
        $countKpiKeys = ['active_customers', 'active_projects'];
        $headlineKpis = collect($dashboard['kpis'] ?? [])->filter(fn ($kpi) => in_array($kpi['key'] ?? '', $countKpiKeys, true))->values();
        $metricKpis = collect($dashboard['kpis'] ?? [])->reject(fn ($kpi) => in_array($kpi['key'] ?? '', $countKpiKeys, true))->values();
        $debtorListPoints = collect($dashboard['debtors'] ?? [])
            ->map(fn ($row) => [
                'label' => (string) ($row['party_name'] ?? '—'),
                'value' => abs((float) ($row['outstanding_amount'] ?? 0)),
            ])
            ->filter(fn ($row) => (float) $row['value'] > 0.00001)
            ->take(5)
            ->values()
            ->all();
        $hasSalesMosaic = isset($dashboard['charts']['sales_trend'])
            || isset($dashboard['charts']['sales_by_category'])
            || isset($dashboard['charts']['sales_by_customer'])
            || count($debtorListPoints) > 0;
    @endphp

    <nav class="exec-spatial__dock" aria-label="صفحات داشبورد" x-ref="dock">
        @foreach($spatialPages as $pageNum => $pageMeta)
            @php
                $pageLabel = $pageMeta['label'];
                $pageIcon = $pageMeta['icon'];
            @endphp
            <button
                type="button"
                class="exec-spatial__orb"
                :class="{ 'is-active': page === {{ $pageNum }} }"
                @click="go({{ $pageNum }})"
                :aria-current="page === {{ $pageNum }} ? 'page' : null"
                aria-label="{{ $pageLabel }}"
                title="{{ $pageLabel }}"
            >
                <span class="exec-spatial__orb-icon" aria-hidden="true">
                    @if($pageIcon === 'sales')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h2l.4 2M7 13h10l3-8H6.4"/><circle cx="9" cy="19" r="1"/><circle cx="17" cy="19" r="1"/><path d="M7 13l-1.2-6"/></svg>
                    @elseif($pageIcon === 'profit')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17 9 11l4 4 8-8"/><path d="M14 7h7v7"/></svg>
                    @elseif($pageIcon === 'finance')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    @endif
                </span>
                <span class="exec-spatial__orb-label">{{ $pageLabel }}</span>
            </button>
        @endforeach
    </nav>

    <div
        class="exec-dashboard-page exec-dashboard-page--spatial exec-dashboard-page--docked"
        x-ref="panel"
        :data-page="page"
        style="--exec-notch-x: 0%; --exec-notch-y: 38%;"
    >
        <div class="exec-dashboard-page__stage" aria-hidden="true"></div>
        <div class="exec-dashboard-page__inner">
            <div class="exec-dashboard">
                <div class="exec-loading-bar" wire:loading wire:loading.class="exec-loading-bar--active" aria-hidden="true"></div>

                <header class="exec-hero exec-animate-hero">
                    <div class="exec-hero__copy">
                        <div class="exec-hero__mark" aria-hidden="true" title="داشبورد">
                            <svg class="exec-hero__mark-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="7.5" height="7.5" rx="1.4"/>
                                <rect x="13.5" y="3" width="7.5" height="4.5" rx="1.4"/>
                                <rect x="13.5" y="10.5" width="7.5" height="10.5" rx="1.4"/>
                                <rect x="3" y="13.5" width="7.5" height="7.5" rx="1.4"/>
                            </svg>
                        </div>
                        <p class="exec-hero__meta">
                            <span>{{ gregorianToJalaliDate($dashboard['period']['date_from']) }} — {{ gregorianToJalaliDate($dashboard['period']['date_to']) }}</span>
                            <span class="exec-hero__time">{{ formatJalaliDateTime($dashboard['generated_at']) }}</span>
                        </p>
                    </div>
                    <div class="exec-hero__aside">
                        <div class="exec-period-card" role="group" aria-label="بازه زمانی">
                            <label class="exec-period-card__row">
                                <span class="exec-period-card__label">بازه</span>
                                <span class="exec-period-card__select-wrap">
                                    <select wire:model.live="periodPreset" class="exec-period-card__select">
                                        @foreach($presets as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <span class="exec-period-card__chevron" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="currentColor" width="14" height="14">
                                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                </span>
                            </label>
                            @if($periodPreset === 'custom')
                                <div class="exec-period-card__custom" wire:key="exec-period-custom">
                                    <label class="exec-period-card__date-field">
                                        <span class="exec-period-card__date-label">از</span>
                                        <x-erp.ui.jalali-date-input
                                            wire:model.blur="customDateFrom"
                                            placeholder="1404/01/01"
                                            class="exec-toolbar__date"
                                        />
                                    </label>
                                    <label class="exec-period-card__date-field">
                                        <span class="exec-period-card__date-label">تا</span>
                                        <x-erp.ui.jalali-date-input
                                            wire:model.blur="customDateTo"
                                            placeholder="1404/12/29"
                                            class="exec-toolbar__date"
                                        />
                                    </label>
                                </div>
                            @endif
                        </div>
                    </div>
                </header>

                {{-- صفحه ۱: فروش --}}
                <div
                    class="exec-spatial__page"
                    x-show="page === 1"
                    x-transition:enter="exec-spatial__page--enter"
                    x-transition:enter-start="exec-spatial__page--enter-start"
                    x-transition:enter-end="exec-spatial__page--enter-end"
                    @transitionend="syncNotch()"
                >
    <nav class="exec-actions" aria-label="دسترسی داشبوردها">
        @if(auth()->user()?->hasPermission('reports.sales.view'))
            <a href="{{ route('sales.intelligence') }}" wire:navigate class="exec-actions__link">داشبورد فروش</a>
        @endif
        {{-- دکمه‌های بعدی داشبوردها اینجا اضافه شوند --}}
    </nav>

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

    @php
        $debtorListPoints = collect($dashboard['debtors'] ?? [])
            ->map(fn ($row) => [
                'label' => (string) ($row['party_name'] ?? '—'),
                'value' => abs((float) ($row['outstanding_amount'] ?? 0)),
            ])
            ->filter(fn ($row) => (float) $row['value'] > 0.00001)
            ->take(5)
            ->values()
            ->all();
        $hasSalesMosaic = isset($dashboard['charts']['sales_trend'])
            || isset($dashboard['charts']['sales_by_category'])
            || isset($dashboard['charts']['sales_by_customer'])
            || count($debtorListPoints) > 0;
    @endphp

    @if($hasSalesMosaic)
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
            @if(count($debtorListPoints) > 0)
                <div class="exec-charts-compact-tile">
                    <x-executive-dashboard.bar-chart
                        title="لیست بدهکاران"
                        variant="category"
                        subtitle="بیشترین مانده مطالبات"
                        empty="بدهکاری ثبت نشده است."
                        :points="$debtorListPoints"
                        :cta-url="auth()->user()?->hasPermission('reports.sales.view') ? route('sales-reports.show', ['report' => 'receivables']) : null"
                        :cta-label="auth()->user()?->hasPermission('reports.sales.view') ? 'گزارش مطالبات' : null"
                    />
                </div>
            @endif
        </section>
    @endif

    @if(! empty($dashboard['crm']['pipeline_chart']))
        <div class="exec-crm-chart">
            <x-executive-dashboard.bar-chart title="ارزش Pipeline (CRM)" :points="$dashboard['crm']['pipeline_chart']" />
        </div>
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

                </div>

                {{-- صفحه ۲: سود و هزینه --}}
                <div
                    class="exec-spatial__page"
                    x-show="page === 2"
                    x-cloak
                    x-transition:enter="exec-spatial__page--enter"
                    x-transition:enter-start="exec-spatial__page--enter-start"
                    x-transition:enter-end="exec-spatial__page--enter-end"
                    @transitionend="syncNotch()"
                >
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

                {{-- صفحه ۳: وضعیت مالی --}}
                <div
                    class="exec-spatial__page"
                    x-show="page === 3"
                    x-cloak
                    x-transition:enter="exec-spatial__page--enter"
                    x-transition:enter-start="exec-spatial__page--enter-start"
                    x-transition:enter-end="exec-spatial__page--enter-end"
                    @transitionend="syncNotch()"
                >
                    @if($metricKpis->isNotEmpty())
                        <section class="exec-kpi-grid" aria-label="شاخص‌های کلیدی">
                            @foreach($metricKpis as $kpi)
                                <x-executive-dashboard.kpi-card :kpi="$kpi" :stagger="$loop->index" />
                            @endforeach
                        </section>
                    @endif
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
                $bankAccounts = $dashboard['financial']['bank_accounts'] ?? [];
                $cashAccounts = $dashboard['financial']['cash_accounts'] ?? [];
            @endphp
            <div class="exec-liquidity-tiles" aria-label="نقد و بانک">
                <h4 class="exec-liquidity-tiles__title">نقد و بانک</h4>
                <button
                    type="button"
                    class="exec-liquidity-tile exec-liquidity-tile--bank"
                    @click="openLiquidity()"
                    title="جزئیات بانک‌ها"
                >
                    <span class="exec-liquidity-tile__label">موجودی بانک</span>
                    <span class="exec-liquidity-tile__value">
                        <x-executive-dashboard.money :amount="$bankTotal" />
                    </span>
                    <span class="exec-liquidity-tile__hint">{{ count($bankAccounts) }} حساب — کلیک برای جزئیات</span>
                </button>
                <button
                    type="button"
                    class="exec-liquidity-tile exec-liquidity-tile--cash"
                    @click="openLiquidity()"
                    title="جزئیات صندوق‌ها"
                >
                    <span class="exec-liquidity-tile__label">موجودی صندوق</span>
                    <span class="exec-liquidity-tile__value">
                        <x-executive-dashboard.money :amount="$cashTotal" />
                    </span>
                    <span class="exec-liquidity-tile__hint">{{ count($cashAccounts) }} صندوق — کلیک برای جزئیات</span>
                </button>
            </div>
            <dl class="exec-stat-list">
                <div
                    class="exec-stat-list__row exec-stat-list__row--action"
                    role="button"
                    tabindex="0"
                    @click="openReceivables()"
                    @keydown.enter.prevent="openReceivables()"
                    @keydown.space.prevent="openReceivables()"
                    title="جزئیات مطالبات"
                >
                    <dt>مطالبات</dt>
                    <dd><x-executive-dashboard.money :amount="$receivablesTotal" /></dd>
                </div>
                <div
                    class="exec-stat-list__row exec-stat-list__row--action"
                    role="button"
                    tabindex="0"
                    @click="openPayables()"
                    @keydown.enter.prevent="openPayables()"
                    @keydown.space.prevent="openPayables()"
                    title="جزئیات بدهی تأمین‌کنندگان"
                >
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


                </div>

                {{-- صفحه ۴: پروژه‌ها --}}
                <div
                    class="exec-spatial__page"
                    x-show="page === 4"
                    x-cloak
                    x-transition:enter="exec-spatial__page--enter"
                    x-transition:enter-start="exec-spatial__page--enter-start"
                    x-transition:enter-end="exec-spatial__page--enter-end"
                    @transitionend="syncNotch()"
                >
    @if(! empty($dashboard['projects']['items']))
        <section class="exec-panel exec-animate-panel" style="--exec-panel-i: 3">
            <div class="exec-panel__head exec-panel__head--split">
                <h3 class="exec-section-title">پروژه‌های در جریان</h3>
                <a href="{{ route('projects.index') }}" wire:navigate class="exec-panel__cta exec-panel__cta--inline">همه پروژه‌ها</a>
            </div>
            <ul class="exec-project-list">
                @foreach($dashboard['projects']['items'] as $project)
                    <li class="exec-project-list__row">
                        <div class="exec-project-list__meta">
                            <a href="{{ $project['url'] }}" wire:navigate class="exec-project-list__name">{{ $project['name'] }}</a>
                            <span class="exec-project-list__sub">
                                {{ $project['party_name'] ?? '—' }}
                                · {{ $project['status_label'] }}
                                @if($project['is_overdue'])
                                    · عقب‌افتاده
                                @endif
                                @if(! empty($project['manager_name']))
                                    · {{ $project['manager_name'] }}
                                @endif
                            </span>
                        </div>
                        <div class="exec-project-list__figures">
                            <div class="exec-project-list__figure">
                                <span class="exec-project-list__figure-label">بودجه</span>
                                <x-executive-dashboard.money :amount="$project['budget']" />
                            </div>
                            <div class="exec-project-list__figure">
                                <span class="exec-project-list__figure-label">درآمد</span>
                                <x-executive-dashboard.money :amount="$project['revenue'] ?? 0" />
                            </div>
                            <div class="exec-project-list__figure">
                                <span class="exec-project-list__figure-label">سود</span>
                                <x-executive-dashboard.money :amount="$project['profit'] ?? 0" />
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

                </div>

                @if($shortcuts->isNotEmpty())
                    <section class="exec-panel exec-panel--flat exec-shortcuts-bar">
                        <h3 class="exec-section-title">دسترسی سریع</h3>
                        <div class="exec-shortcuts">
                            @foreach($shortcuts->take(12) as $shortcut)
                                <a href="{{ $shortcut['url'] }}" wire:navigate class="exec-shortcuts__item">{{ $shortcut['label'] }}</a>
                            @endforeach
                        </div>
                    </section>
                @endif

    @if(! empty($dashboard['financial']))
        @php
            $modalBankTotal = (float) ($dashboard['financial']['bank_total'] ?? 0);
            $modalCashTotal = (float) ($dashboard['financial']['cash_total'] ?? 0);
            $modalLiquidityTotal = (float) ($dashboard['financial']['liquidity_total'] ?? ($modalBankTotal + $modalCashTotal));
            $modalBankAccounts = $dashboard['financial']['bank_accounts'] ?? [];
            $modalCashAccounts = $dashboard['financial']['cash_accounts'] ?? [];
        @endphp
        <div
            class="exec-dash-modal"
            x-show="liquidityOpen"
            x-cloak
            x-transition:enter="exec-dash-modal--enter"
            x-transition:enter-start="exec-dash-modal--enter-start"
            x-transition:enter-end="exec-dash-modal--enter-end"
            x-transition:leave="exec-dash-modal--leave"
            x-transition:leave-start="exec-dash-modal--leave-start"
            x-transition:leave-end="exec-dash-modal--leave-end"
            @click.self="closeLiquidity()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="exec-liquidity-modal-title"
        >
            <div
                class="exec-dash-modal__panel exec-liquidity-modal"
                @click.stop
                x-show="liquidityOpen"
                x-transition:enter="exec-dash-modal__panel--enter"
                x-transition:enter-start="exec-dash-modal__panel--enter-start"
                x-transition:enter-end="exec-dash-modal__panel--enter-end"
                x-transition:leave="exec-dash-modal__panel--leave"
                x-transition:leave-start="exec-dash-modal__panel--leave-start"
                x-transition:leave-end="exec-dash-modal__panel--leave-end"
            >
                <div class="erp-modal-header">
                    <h3 id="exec-liquidity-modal-title">تفکیک نقد و بانک</h3>
                    <button type="button" class="erp-modal-close" @click="closeLiquidity()" aria-label="بستن">×</button>
                </div>
                <div class="erp-modal-body exec-liquidity-modal__body">
                    <div class="exec-liquidity-modal__summary">
                        <div class="exec-liquidity-modal__summary-item exec-liquidity-modal__summary-item--bank">
                            <span class="exec-liquidity-modal__summary-label">بانک</span>
                            <x-executive-dashboard.money :amount="$modalBankTotal" />
                        </div>
                        <div class="exec-liquidity-modal__summary-item exec-liquidity-modal__summary-item--cash">
                            <span class="exec-liquidity-modal__summary-label">صندوق</span>
                            <x-executive-dashboard.money :amount="$modalCashTotal" />
                        </div>
                        <div class="exec-liquidity-modal__summary-item exec-liquidity-modal__summary-item--total">
                            <span class="exec-liquidity-modal__summary-label">جمع</span>
                            <x-executive-dashboard.money :amount="$modalLiquidityTotal" />
                        </div>
                    </div>

                    <section class="exec-liquidity-modal__section">
                        <h4 class="exec-liquidity-modal__section-title">بانک‌ها</h4>
                        @if(count($modalBankAccounts) > 0)
                            <ul class="exec-liquidity-modal__list">
                                @foreach($modalBankAccounts as $row)
                                    <li class="exec-liquidity-modal__row">
                                        <div class="exec-liquidity-modal__row-meta">
                                            <span class="exec-liquidity-modal__row-code">{{ $row['code'] ?? '—' }}</span>
                                            <span class="exec-liquidity-modal__row-name">{{ $row['name'] ?? '—' }}</span>
                                        </div>
                                        <span class="exec-liquidity-modal__row-amount">
                                            <x-executive-dashboard.money :amount="(float) ($row['closing'] ?? 0)" />
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="exec-liquidity-modal__empty">حساب بانکی با مانده ثبت نشده است.</p>
                        @endif
                    </section>

                    <section class="exec-liquidity-modal__section">
                        <h4 class="exec-liquidity-modal__section-title">صندوق‌ها</h4>
                        @if(count($modalCashAccounts) > 0)
                            <ul class="exec-liquidity-modal__list">
                                @foreach($modalCashAccounts as $row)
                                    <li class="exec-liquidity-modal__row">
                                        <div class="exec-liquidity-modal__row-meta">
                                            <span class="exec-liquidity-modal__row-code">{{ $row['code'] ?? '—' }}</span>
                                            <span class="exec-liquidity-modal__row-name">{{ $row['name'] ?? '—' }}</span>
                                        </div>
                                        <span class="exec-liquidity-modal__row-amount">
                                            <x-executive-dashboard.money :amount="(float) ($row['closing'] ?? 0)" />
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="exec-liquidity-modal__empty">صندوقی با مانده ثبت نشده است.</p>
                        @endif
                    </section>

                    @if(auth()->user()?->hasPermission('financial.reports.view'))
                        <div class="exec-liquidity-modal__footer">
                            <a href="{{ route('financial-reports.index') }}" wire:navigate class="exec-panel__cta exec-panel__cta--inline">گزارش‌های مالی</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if(! empty($dashboard['receivables']))
        @php
            $rx = $dashboard['receivables'];
            $rxTotal = abs((float) ($rx['total_receivables'] ?? 0));
            $rxCurrent = abs((float) ($rx['current'] ?? data_get($rx, 'buckets.current', 0)));
            $rxOverdue = abs((float) ($rx['overdue'] ?? 0));
            $rxBuckets = [
                ['label' => 'جاری', 'value' => abs((float) data_get($rx, 'buckets.current', 0))],
                ['label' => '۱ تا ۳۰ روز', 'value' => abs((float) data_get($rx, 'buckets.days_1_30', 0))],
                ['label' => '۳۱ تا ۶۰ روز', 'value' => abs((float) data_get($rx, 'buckets.days_31_60', 0))],
                ['label' => '۶۱ تا ۹۰ روز', 'value' => abs((float) data_get($rx, 'buckets.days_61_90', 0))],
                ['label' => 'بیش از ۹۰ روز', 'value' => abs((float) data_get($rx, 'buckets.over_90', 0))],
            ];
            $rxDebtors = collect($rx['parties'] ?? $dashboard['debtors'] ?? [])
                ->map(function ($row) {
                    return [
                        'party_name' => (string) ($row['party_name'] ?? '—'),
                        'outstanding_amount' => abs((float) ($row['outstanding_amount'] ?? 0)),
                        'invoice_count' => (int) ($row['invoice_count'] ?? 0),
                        'days' => (int) ($row['days'] ?? 0),
                    ];
                })
                ->filter(fn ($row) => (float) $row['outstanding_amount'] > 0.00001)
                ->take(10)
                ->values()
                ->all();
        @endphp
        <div
            class="exec-dash-modal"
            x-show="receivablesOpen"
            x-cloak
            x-transition:enter="exec-dash-modal--enter"
            x-transition:enter-start="exec-dash-modal--enter-start"
            x-transition:enter-end="exec-dash-modal--enter-end"
            x-transition:leave="exec-dash-modal--leave"
            x-transition:leave-start="exec-dash-modal--leave-start"
            x-transition:leave-end="exec-dash-modal--leave-end"
            @click.self="closeReceivables()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="exec-receivables-modal-title"
        >
            <div
                class="exec-dash-modal__panel exec-liquidity-modal"
                @click.stop
                x-show="receivablesOpen"
                x-transition:enter="exec-dash-modal__panel--enter"
                x-transition:enter-start="exec-dash-modal__panel--enter-start"
                x-transition:enter-end="exec-dash-modal__panel--enter-end"
                x-transition:leave="exec-dash-modal__panel--leave"
                x-transition:leave-start="exec-dash-modal__panel--leave-start"
                x-transition:leave-end="exec-dash-modal__panel--leave-end"
            >
                <div class="erp-modal-header">
                    <h3 id="exec-receivables-modal-title">تفکیک مطالبات</h3>
                    <button type="button" class="erp-modal-close" @click="closeReceivables()" aria-label="بستن">×</button>
                </div>
                <div class="erp-modal-body exec-liquidity-modal__body">
                    <div class="exec-liquidity-modal__summary">
                        <div class="exec-liquidity-modal__summary-item">
                            <span class="exec-liquidity-modal__summary-label">جاری</span>
                            <x-executive-dashboard.money :amount="$rxCurrent" />
                        </div>
                        <div class="exec-liquidity-modal__summary-item exec-liquidity-modal__summary-item--cash">
                            <span class="exec-liquidity-modal__summary-label">معوق</span>
                            <x-executive-dashboard.money :amount="$rxOverdue" />
                        </div>
                        <div class="exec-liquidity-modal__summary-item exec-liquidity-modal__summary-item--total">
                            <span class="exec-liquidity-modal__summary-label">جمع</span>
                            <x-executive-dashboard.money :amount="$rxTotal" />
                        </div>
                    </div>

                    <section class="exec-liquidity-modal__section">
                        <h4 class="exec-liquidity-modal__section-title">سن مطالبات</h4>
                        <ul class="exec-liquidity-modal__list">
                            @foreach($rxBuckets as $bucket)
                                <li class="exec-liquidity-modal__row">
                                    <div class="exec-liquidity-modal__row-meta">
                                        <span class="exec-liquidity-modal__row-name">{{ $bucket['label'] }}</span>
                                    </div>
                                    <span class="exec-liquidity-modal__row-amount">
                                        <x-executive-dashboard.money :amount="$bucket['value']" />
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </section>

                    <section class="exec-liquidity-modal__section">
                        <h4 class="exec-liquidity-modal__section-title">بیشترین بدهکاران</h4>
                        @if(count($rxDebtors) > 0)
                            <ul class="exec-liquidity-modal__list">
                                @foreach($rxDebtors as $row)
                                    <li class="exec-liquidity-modal__row">
                                        <div class="exec-liquidity-modal__row-meta">
                                            <span class="exec-liquidity-modal__row-name">{{ $row['party_name'] ?? '—' }}</span>
                                            <span class="exec-liquidity-modal__row-code">
                                                {{ (int) ($row['invoice_count'] ?? 0) }} فاکتور
                                                @if((int) ($row['days'] ?? 0) > 0)
                                                    — {{ number_format((int) $row['days']) }} روز گذشته
                                                @else
                                                    — جاری
                                                @endif
                                            </span>
                                        </div>
                                        <span class="exec-liquidity-modal__row-amount">
                                            <x-executive-dashboard.money :amount="(float) ($row['outstanding_amount'] ?? 0)" />
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="exec-liquidity-modal__empty">بدهکار ثبت‌شده‌ای نیست.</p>
                        @endif
                    </section>

                    @if(auth()->user()?->hasPermission('reports.sales.view'))
                        <div class="exec-liquidity-modal__footer">
                            <a href="{{ route('sales-reports.show', ['report' => 'receivables']) }}" wire:navigate class="exec-panel__cta exec-panel__cta--inline">گزارش مطالبات</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if(! empty($dashboard['payables']))
        @php
            $px = $dashboard['payables'];
            $pxTotal = abs((float) ($px['total_payables'] ?? 0));
            $pxCurrent = abs((float) ($px['current'] ?? data_get($px, 'buckets.current', 0)));
            $pxOverdue = abs((float) ($px['overdue'] ?? 0));
            $pxParties = collect($px['parties'] ?? [])
                ->map(function ($row) {
                    return [
                        'party_name' => (string) ($row['party_name'] ?? '—'),
                        'outstanding_amount' => abs((float) ($row['outstanding_amount'] ?? 0)),
                        'days' => (int) ($row['days'] ?? 0),
                    ];
                })
                ->filter(fn ($row) => (float) $row['outstanding_amount'] > 0.00001)
                ->take(15)
                ->values()
                ->all();
        @endphp
        <div
            class="exec-dash-modal"
            x-show="payablesOpen"
            x-cloak
            x-transition:enter="exec-dash-modal--enter"
            x-transition:enter-start="exec-dash-modal--enter-start"
            x-transition:enter-end="exec-dash-modal--enter-end"
            x-transition:leave="exec-dash-modal--leave"
            x-transition:leave-start="exec-dash-modal--leave-start"
            x-transition:leave-end="exec-dash-modal--leave-end"
            @click.self="closePayables()"
            role="dialog"
            aria-modal="true"
            aria-labelledby="exec-payables-modal-title"
        >
            <div
                class="exec-dash-modal__panel exec-liquidity-modal"
                @click.stop
                x-show="payablesOpen"
                x-transition:enter="exec-dash-modal__panel--enter"
                x-transition:enter-start="exec-dash-modal__panel--enter-start"
                x-transition:enter-end="exec-dash-modal__panel--enter-end"
                x-transition:leave="exec-dash-modal__panel--leave"
                x-transition:leave-start="exec-dash-modal__panel--leave-start"
                x-transition:leave-end="exec-dash-modal__panel--leave-end"
            >
                <div class="erp-modal-header">
                    <h3 id="exec-payables-modal-title">تفکیک بدهی تأمین‌کنندگان</h3>
                    <button type="button" class="erp-modal-close" @click="closePayables()" aria-label="بستن">×</button>
                </div>
                <div class="erp-modal-body exec-liquidity-modal__body">
                    <div class="exec-liquidity-modal__summary">
                        <div class="exec-liquidity-modal__summary-item">
                            <span class="exec-liquidity-modal__summary-label">جاری</span>
                            <x-executive-dashboard.money :amount="$pxCurrent" />
                        </div>
                        <div class="exec-liquidity-modal__summary-item exec-liquidity-modal__summary-item--cash">
                            <span class="exec-liquidity-modal__summary-label">معوق</span>
                            <x-executive-dashboard.money :amount="$pxOverdue" />
                        </div>
                        <div class="exec-liquidity-modal__summary-item exec-liquidity-modal__summary-item--total">
                            <span class="exec-liquidity-modal__summary-label">جمع</span>
                            <x-executive-dashboard.money :amount="$pxTotal" />
                        </div>
                    </div>

                    <section class="exec-liquidity-modal__section">
                        <h4 class="exec-liquidity-modal__section-title">لیست تأمین‌کنندگان</h4>
                        @if(count($pxParties) > 0)
                            <ul class="exec-liquidity-modal__list">
                                @foreach($pxParties as $row)
                                    <li class="exec-liquidity-modal__row">
                                        <div class="exec-liquidity-modal__row-meta">
                                            <span class="exec-liquidity-modal__row-name">{{ $row['party_name'] }}</span>
                                            <span class="exec-liquidity-modal__row-code">
                                                @if((int) ($row['days'] ?? 0) > 0)
                                                    {{ number_format((int) $row['days']) }} روز گذشته
                                                @else
                                                    جاری
                                                @endif
                                            </span>
                                        </div>
                                        <span class="exec-liquidity-modal__row-amount">
                                            <x-executive-dashboard.money :amount="(float) $row['outstanding_amount']" />
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="exec-liquidity-modal__empty">بدهی تأمین‌کننده‌ای ثبت نشده است.</p>
                        @endif
                    </section>

                    @if(auth()->user()?->hasPermission('financial.reports.view'))
                        <div class="exec-liquidity-modal__footer">
                            <a href="{{ route('financial-reports.show', ['report' => 'accounts-payable-aging']) }}" wire:navigate class="exec-panel__cta exec-panel__cta--inline">گزارش سن بدهی‌ها</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

            </div>
        </div>
    </div>
</div>
