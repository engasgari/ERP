<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">گزارش‌های مدیریتی</h2>
    </x-slot>

    <div class="py-12">
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-5">
                <div>
                    <h2 class="text-xl font-bold">تراز آزمایشی مالی</h2>
                    <p class="mt-1 text-sm text-gray-500">کل پروژه‌ها، درآمدها، هزینه‌ها، حقوق و دستمزد و انبار</p>
                    @if(! empty($ledgerDefaults['fiscal_year_title']))
                        <p class="mt-1 text-xs font-bold text-slate-500">
                            سال مالی فعال: <span class="text-slate-800">{{ $ledgerDefaults['fiscal_year_title'] }}</span>
                        </p>
                    @endif
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('management-reports.index') }}" wire:navigate
                       class="rounded-lg bg-gray-100 px-3 py-2 text-sm text-gray-700 transition hover:bg-gray-200">
                        فهرست گزارش‌ها
                    </a>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600">
                        {{ todayJalaliDate() }}
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="erp-compact-table w-full border-collapse border border-gray-300">
                    <thead class="bg-gray-200">
                    <tr>
                        <th class="border border-gray-300 p-2">کد حساب</th>
                        <th class="border border-gray-300 p-2">شرح حساب</th>
                        <th class="border border-gray-300 p-2">مانده افتتاحیه بدهکار</th>
                        <th class="border border-gray-300 p-2">مانده افتتاحیه بستانکار</th>
                        <th class="border border-gray-300 p-2">گردش بدهکار</th>
                        <th class="border border-gray-300 p-2">گردش بستانکار</th>
                        <th class="border border-gray-300 p-2">مانده نهایی بدهکار</th>
                        <th class="border border-gray-300 p-2">مانده نهایی بستانکار</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $row)
                        <tr
                            class="{{ ! empty($row['account_id']) ? 'trial-account-row' : '' }}"
                            @if(! empty($row['account_id']))
                                data-account-id="{{ (int) $row['account_id'] }}"
                                data-account-code="{{ $row['code'] }}"
                                data-account-title="{{ $row['title'] }}"
                                tabindex="0"
                                role="link"
                                title="مشاهده گردش حساب"
                            @endif
                        >
                            <td class="border border-gray-300 p-2 text-gray-500">{{ $row['code'] }}</td>
                            <td class="border border-gray-300 p-2 font-medium">{{ $row['title'] }}</td>
                            <td class="border border-gray-300 p-2 text-left" dir="ltr">{{ formatMoney((float) ($row['opening_debit'] ?? 0)) }}</td>
                            <td class="border border-gray-300 p-2 text-left" dir="ltr">{{ formatMoney((float) ($row['opening_credit'] ?? 0)) }}</td>
                            <td class="border border-gray-300 p-2 text-left" dir="ltr">{{ formatMoney((float) ($row['period_debit'] ?? 0)) }}</td>
                            <td class="border border-gray-300 p-2 text-left" dir="ltr">{{ formatMoney((float) ($row['period_credit'] ?? 0)) }}</td>
                            <td class="border border-gray-300 p-2 text-left" dir="ltr">{{ formatMoney((float) ($row['closing_debit'] ?? 0)) }}</td>
                            <td class="border border-gray-300 p-2 text-left" dir="ltr">{{ formatMoney((float) ($row['closing_credit'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="border border-gray-300 p-4 text-center text-gray-500">
                                هنوز داده مالی برای گزارش وجود ندارد.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                    <tfoot>
                    <tr class="trial-balance-totals font-bold">
                        <td class="border border-gray-300 px-2 py-1" colspan="2">جمع کل</td>
                        <td class="border border-gray-300 px-2 py-1 text-left text-xs" dir="ltr">{{ formatMoney($totals['opening_debit']) }}</td>
                        <td class="border border-gray-300 px-2 py-1 text-left text-xs" dir="ltr">{{ formatMoney($totals['opening_credit']) }}</td>
                        <td class="border border-gray-300 px-2 py-1 text-left text-xs" dir="ltr">{{ formatMoney($totals['period_debit']) }}</td>
                        <td class="border border-gray-300 px-2 py-1 text-left text-xs" dir="ltr">{{ formatMoney($totals['period_credit']) }}</td>
                        <td class="border border-gray-300 px-2 py-1 text-left text-xs" dir="ltr">{{ formatMoney($totals['closing_debit']) }}</td>
                        <td class="border border-gray-300 px-2 py-1 text-left text-xs" dir="ltr">{{ formatMoney($totals['closing_credit']) }}</td>
                    </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-4 text-xs leading-6 text-gray-500">
                این تراز آزمایشی از سندهای حسابداری ثبت‌شده ساخته شده است و باید با دفاتر کل و دفتر روزنامه همخوانی داشته باشد.
                برای مشاهده گردش هر حساب، روی ردیف مربوط کلیک کنید.
            </div>
        </div>
    </div>

    <div class="erp-modal trial-ledger-modal" id="trial-account-ledger-modal" hidden>
        <div class="erp-ui-modal-backdrop" data-erp-modal-close></div>
        <div class="erp-ui-modal-panel trial-ledger-modal-panel" role="dialog" aria-modal="true" aria-labelledby="trial-ledger-title">
            <div class="erp-modal-header">
                <div>
                    <h3 id="trial-ledger-title">گردش حساب</h3>
                    <div id="trial-ledger-subtitle" class="trial-ledger-subtitle"></div>
                </div>
                <button type="button" class="erp-modal-close" data-erp-modal-close aria-label="بستن">×</button>
            </div>
            <div class="erp-modal-body trial-ledger-modal-body">
                <form id="trial-ledger-filter-form" class="erp-ui-filter-bar trial-ledger-filter-bar mb-4">
                    <div class="erp-filter-row trial-ledger-filter-row">
                        <label class="erp-filter-field">از تاریخ
                            <x-erp.ui.jalali-date-input
                                id="trial-ledger-date-from"
                                name="date_from"
                                :value="$ledgerDefaults['date_from'] ?? ''"
                                placeholder="1404/01/01"
                                class="w-full"
                            />
                        </label>
                        <label class="erp-filter-field">تا تاریخ
                            <x-erp.ui.jalali-date-input
                                id="trial-ledger-date-to"
                                name="date_to"
                                :value="$ledgerDefaults['date_to'] ?? ''"
                                placeholder="1404/12/29"
                                class="w-full"
                            />
                        </label>
                        <label class="erp-filter-field">شرح
                            <input id="trial-ledger-search" type="text" name="search" value="" placeholder="جستجو در شرح سند یا طرف حساب">
                        </label>
                        <div class="erp-filter-actions">
                            <button type="submit" class="erp-filter-submit">اعمال فیلتر</button>
                            <button type="button" id="trial-ledger-filter-reset" class="erp-filter-reset">پاک کردن</button>
                        </div>
                    </div>
                </form>

                <div id="trial-ledger-summary" class="trial-ledger-summary"></div>
                <div id="trial-ledger-loading" class="trial-ledger-loading" hidden>در حال بارگذاری گردش حساب...</div>
                <div id="trial-ledger-error" class="trial-ledger-error" hidden></div>
                <div class="overflow-x-auto">
                    <table class="erp-ui-data-table min-w-full">
                        <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>شماره سند</th>
                            <th>طرف حساب</th>
                            <th>پروژه</th>
                            <th>شرح</th>
                            <th>بدهکار (ریال)</th>
                            <th>بستانکار (ریال)</th>
                            <th>مانده جاری</th>
                        </tr>
                        </thead>
                        <tbody id="trial-ledger-lines"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        .trial-balance-totals td {
            font-size: .75rem;
            line-height: 1.2;
            background: #f8fafc;
        }

        .trial-account-row {
            cursor: pointer;
            transition: background-color .12s ease;
        }

        .trial-account-row:hover,
        .trial-account-row:focus {
            background: #ecfeff;
            outline: none;
        }

        .trial-account-row:hover td,
        .trial-account-row:focus td {
            background: #ecfeff;
        }

        .trial-account-row:active td {
            background: #cffafe;
        }

        .trial-ledger-modal {
            position: fixed;
            inset: 0;
            z-index: 120;
            display: grid;
            place-items: center;
            padding: 1rem;
        }

        .trial-ledger-modal[hidden] {
            display: none;
        }

        .trial-ledger-modal .erp-ui-modal-backdrop {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, .45);
        }

        .trial-ledger-modal-panel {
            position: relative;
            z-index: 1;
            width: min(78rem, 100%);
            max-height: calc(100vh - 2rem);
            overflow: auto;
            border-radius: .75rem;
            background: #fff;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .22);
        }

        .trial-ledger-modal .erp-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            border-bottom: 1px solid #e2e8f0;
            padding: .9rem 1rem;
        }

        .trial-ledger-modal .erp-modal-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 900;
        }

        .trial-ledger-subtitle {
            margin-top: .25rem;
            color: #64748b;
            font-size: .78rem;
            font-weight: 700;
        }

        .trial-ledger-modal-body {
            padding: 1rem;
        }

        .erp-shell .trial-ledger-filter-row {
            grid-template-columns: minmax(0, 0.85fr) minmax(0, 0.85fr) minmax(0, 1.25fr) auto;
        }

        @media (max-width: 1023px) {
            .erp-shell .trial-ledger-filter-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .erp-shell .trial-ledger-filter-row .erp-filter-actions {
                grid-column: 1 / -1;
            }
        }

        .trial-ledger-summary {
            display: grid;
            gap: .45rem;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            margin-bottom: .75rem;
        }

        .trial-ledger-summary div {
            border-radius: .45rem;
            background: #f8fafc;
            padding: .4rem .55rem;
            border: 1px solid #e2e8f0;
        }

        .trial-ledger-summary .label {
            color: #64748b;
            font-size: .65rem;
            font-weight: 700;
        }

        .trial-ledger-summary .value {
            margin-top: .15rem;
            font-size: .78rem;
            font-weight: 800;
            color: #0f172a;
        }

        .trial-ledger-loading,
        .trial-ledger-error {
            margin-bottom: .85rem;
            border-radius: .5rem;
            padding: .75rem .9rem;
            font-size: .85rem;
            font-weight: 700;
        }

        .trial-ledger-loading {
            background: #f1f5f9;
            color: #334155;
        }

        .trial-ledger-error {
            background: #fef2f2;
            color: #b91c1c;
        }

        @media (max-width: 1100px) {
            .trial-ledger-summary {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .trial-ledger-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>

    <script>
        (() => {
            const modal = document.getElementById('trial-account-ledger-modal');
            const title = document.getElementById('trial-ledger-title');
            const subtitle = document.getElementById('trial-ledger-subtitle');
            const summaryBox = document.getElementById('trial-ledger-summary');
            const loading = document.getElementById('trial-ledger-loading');
            const errorBox = document.getElementById('trial-ledger-error');
            const linesBody = document.getElementById('trial-ledger-lines');
            const filterForm = document.getElementById('trial-ledger-filter-form');
            const dateFromInput = document.getElementById('trial-ledger-date-from')
                || filterForm?.querySelector('[name="date_from"]');
            const dateToInput = document.getElementById('trial-ledger-date-to')
                || filterForm?.querySelector('[name="date_to"]');
            const searchInput = document.getElementById('trial-ledger-search');
            const resetButton = document.getElementById('trial-ledger-filter-reset');
            const ledgerUrlTemplate = @json(route('management-reports.trial-balance.account-ledger', ['account' => '__ACCOUNT__']));
            const documentUrlTemplate = @json(route('accounting-documents.show', ['accountingDocument' => '__DOCUMENT__']));
            const ledgerDefaults = @json($ledgerDefaults ?? []);

            let currentAccountId = null;
            let currentAccountCode = '';
            let currentAccountTitle = '';

            const formatNumber = (value) => window.ErpFormat?.money?.(value)
                ?? Number(value || 0).toLocaleString('fa-IR');

            const readFilterValues = () => ({
                date_from: (dateFromInput?.value || '').trim(),
                date_to: (dateToInput?.value || '').trim(),
                search: (searchInput?.value || '').trim(),
            });

            const applyDefaultFilters = () => {
                if (dateFromInput) {
                    dateFromInput.value = ledgerDefaults.date_from || '';
                }
                if (dateToInput) {
                    dateToInput.value = ledgerDefaults.date_to || '';
                }
                if (searchInput) {
                    searchInput.value = '';
                }
            };

            const buildLedgerUrl = (accountId) => {
                const url = new URL(ledgerUrlTemplate.replace('__ACCOUNT__', String(accountId)), window.location.origin);
                const filters = readFilterValues();
                Object.entries(filters).forEach(([key, value]) => {
                    if (value) {
                        url.searchParams.set(key, value);
                    }
                });
                if (ledgerDefaults.fiscal_year_id) {
                    url.searchParams.set('fiscal_year_id', String(ledgerDefaults.fiscal_year_id));
                }
                return url.toString();
            };

            const openModal = () => {
                modal.hidden = false;
                modal.classList.add('is-open');
                document.body.classList.add('erp-modal-open');
            };

            const closeModal = () => {
                modal.hidden = true;
                modal.classList.remove('is-open');
                if (!document.querySelector('.erp-modal:not([hidden])')) {
                    document.body.classList.remove('erp-modal-open');
                }
            };

            const renderSummary = (payload) => {
                summaryBox.innerHTML = `
                    <div><div class="label">افتتاحیه بدهکار</div><div class="value">${formatNumber(payload.opening_debit)}</div></div>
                    <div><div class="label">افتتاحیه بستانکار</div><div class="value">${formatNumber(payload.opening_credit)}</div></div>
                    <div><div class="label">گردش بدهکار</div><div class="value">${formatNumber(payload.period_debit ?? payload.debit)}</div></div>
                    <div><div class="label">گردش بستانکار</div><div class="value">${formatNumber(payload.period_credit ?? payload.credit)}</div></div>
                    <div><div class="label">مانده نهایی بدهکار</div><div class="value">${formatNumber(payload.closing_debit)}</div></div>
                    <div><div class="label">مانده نهایی بستانکار</div><div class="value">${formatNumber(payload.closing_credit)}</div></div>
                `;
            };

            const renderLines = (lines) => {
                linesBody.innerHTML = '';

                if (!lines.length) {
                    linesBody.innerHTML = '<tr><td colspan="8" class="py-6 text-center text-slate-500">گردشی برای این حساب یافت نشد.</td></tr>';
                    return;
                }

                lines.forEach((line) => {
                    const row = document.createElement('tr');
                    const documentCell = line.document_id
                        ? `<a href="${documentUrlTemplate.replace('__DOCUMENT__', String(line.document_id))}" target="_blank" rel="noopener">${line.document_number || '-'}</a>`
                        : (line.document_number || '-');

                    row.innerHTML = `
                        <td>${line.date || '-'}</td>
                        <td>${documentCell}</td>
                        <td>${line.party || '-'}</td>
                        <td>${line.project || '-'}</td>
                        <td>${line.description || '-'}</td>
                        <td dir="ltr">${formatNumber(line.debit)}</td>
                        <td dir="ltr">${formatNumber(line.credit)}</td>
                        <td dir="ltr">${formatNumber(line.running_balance)}</td>
                    `;
                    linesBody.appendChild(row);
                });
            };

            const loadLedger = async (accountId, code, accountTitle, { open = true } = {}) => {
                currentAccountId = accountId;
                currentAccountCode = code || '';
                currentAccountTitle = accountTitle || '';

                title.textContent = `گردش حساب ${currentAccountCode}`;
                subtitle.textContent = currentAccountTitle
                    + (ledgerDefaults.fiscal_year_title ? ` | سال مالی: ${ledgerDefaults.fiscal_year_title}` : '');
                summaryBox.innerHTML = '';
                linesBody.innerHTML = '';
                errorBox.hidden = true;
                errorBox.textContent = '';
                loading.hidden = false;

                if (open) {
                    openModal();
                }

                try {
                    const response = await fetch(buildLedgerUrl(accountId), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('بارگذاری گردش حساب ناموفق بود.');
                    }

                    const payload = await response.json();
                    renderSummary(payload);
                    renderLines(payload.lines || []);
                } catch (error) {
                    errorBox.hidden = false;
                    errorBox.textContent = error?.message || 'خطا در دریافت گردش حساب.';
                } finally {
                    loading.hidden = true;
                }
            };

            const openFromRow = (row) => {
                const accountId = Number(row.dataset.accountId || 0);
                if (!accountId) {
                    return;
                }

                applyDefaultFilters();
                loadLedger(accountId, row.dataset.accountCode || '', row.dataset.accountTitle || '');
            };

            document.querySelectorAll('.trial-account-row').forEach((row) => {
                row.addEventListener('click', () => openFromRow(row));
                row.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        openFromRow(row);
                    }
                });
            });

            filterForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                if (!currentAccountId) {
                    return;
                }

                loadLedger(currentAccountId, currentAccountCode, currentAccountTitle, { open: false });
            });

            resetButton?.addEventListener('click', () => {
                applyDefaultFilters();
                if (!currentAccountId) {
                    return;
                }

                loadLedger(currentAccountId, currentAccountCode, currentAccountTitle, { open: false });
            });

            modal?.querySelectorAll('[data-erp-modal-close]').forEach((el) => {
                el.addEventListener('click', closeModal);
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !modal.hidden) {
                    closeModal();
                }
            });
        })();
    </script>
</x-app-layout>
