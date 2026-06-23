<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">صورتحساب اشخاص و شرکت‌ها</h2></x-slot>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-4 statement-page">
        <form method="get" class="grid md:grid-cols-4 gap-3 items-end no-print">
            <label>شخص/شرکت
                <select name="party_id" class="w-full">
                    <option value="">همه</option>
                    @foreach($parties as $party)
                        <option value="{{ $party->id }}" @selected(request('party_id') == $party->id)>{{ $party->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>حساب
                <select name="account_id" class="w-full">
                    <option value="">همه حساب‌ها</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected(request('account_id') == $account->id)>{{ chartAccountDisplayLabel($account) }}</option>
                    @endforeach
                </select>
            </label>
            <label>از تاریخ
                <input name="date_from" inputmode="numeric" dir="ltr" placeholder="1405/01/01" value="{{ request('date_from') ? jalaliDateInputValue(request('date_from')) : '' }}" class="w-full">
            </label>
            <label>تا تاریخ
                <input name="date_to" inputmode="numeric" dir="ltr" placeholder="1405/12/29" value="{{ request('date_to') ? jalaliDateInputValue(request('date_to')) : '' }}" class="w-full">
            </label>
            <div class="md:col-span-4 flex gap-2">
                <button class="bg-blue-600 text-white px-4 py-2 rounded">اعمال فیلتر</button>
                <a href="{{ route('financial-reports.statement') }}" class="bg-gray-500 text-white px-4 py-2 rounded">پاک کردن</a>
            </div>
        </form>

        <div class="overflow-x-auto no-print">
            <table class="erp-ui-data-table w-full">
                <thead>
                <tr>
                    <th>کد</th>
                    <th>شخص/شرکت</th>
                    <th>جمع بدهکار (ریال)</th>
                    <th>جمع بستانکار (ریال)</th>
                    <th>مانده</th>
                    <th>ماهیت مانده</th>
                </tr>
                </thead>
                <tbody>
                @forelse($summaries as $index => $summary)
                    <tr class="statement-summary-row cursor-pointer hover:bg-slate-50"
                        data-statement-index="{{ $index }}">
                        <td>{{ $summary['party_code'] }}</td>
                        <td class="font-bold text-slate-800">{{ $summary['party_name'] }}</td>
                        <td>{{ number_format($summary['debit']) }}</td>
                        <td>{{ number_format($summary['credit']) }}</td>
                        <td>{{ number_format(abs($summary['balance'])) }}</td>
                        <td>{{ $summary['balance_type'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-500 py-6">صورتحسابی برای نمایش وجود ندارد.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="statement-modal-backdrop no-print" id="statement-modal" hidden>
        <div class="statement-modal" role="dialog" aria-modal="true" aria-labelledby="statement-modal-title">
            <div class="statement-modal-header">
                <div>
                    <h3 id="statement-modal-title">صورتحساب</h3>
                    <div id="statement-modal-subtitle" class="statement-modal-subtitle"></div>
                </div>
                <button type="button" id="statement-modal-close" aria-label="بستن">×</button>
            </div>
            <div class="statement-modal-actions">
                <a href="{{ route('financial-reports.statement.print', request()->query()) }}" target="_blank" data-no-spa id="statement-print-button">چاپ صورتحساب</a>
            </div>
            <div id="statement-print-area" class="statement-print-area">
                <div class="statement-print-header">
                    <h2 id="statement-print-title">صورتحساب</h2>
                    <div id="statement-print-meta"></div>
                </div>
                <table class="erp-ui-data-table w-full">
                    <thead>
                    <tr>
                        <th>تاریخ</th>
                        <th>شماره سند</th>
                        <th>حساب</th>
                        <th>شرح</th>
                        <th>بدهکار (ریال)</th>
                        <th>بستانکار (ریال)</th>
                        <th>مانده جاری</th>
                    </tr>
                    </thead>
                    <tbody id="statement-lines"></tbody>
                </table>
            </div>
        </div>
    </div>

    <style>
        .statement-summary-row { transition: background-color .15s ease; }
        .statement-modal-backdrop { position: fixed; inset: 0; z-index: 100; display: grid; place-items: center; background: rgba(15, 23, 42, .45); padding: 1rem; }
        .statement-modal-backdrop[hidden] { display: none; }
        .statement-modal { width: min(72rem, 100%); max-height: calc(100vh - 2rem); overflow: auto; border-radius: .5rem; background: #fff; box-shadow: 0 24px 70px rgba(15, 23, 42, .22); }
        .statement-modal-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; border-bottom: 1px solid #e2e8f0; padding: .85rem 1rem; }
        .statement-modal-header h3 { margin: 0; font-size: 1rem; font-weight: 900; }
        .statement-modal-subtitle { margin-top: .2rem; color: #64748b; font-size: .78rem; font-weight: 700; }
        .statement-modal-header button { display: grid; place-items: center; width: 2rem; height: 2rem; border: 0; border-radius: .35rem; background: #f1f5f9; color: #334155; font-size: 1.2rem; font-weight: 900; }
        .statement-modal-actions { display: flex; justify-content: flex-end; padding: .75rem 1rem 0; }
        .statement-modal-actions button,
        .statement-modal-actions a { border: 0; border-radius: .35rem; background: #334155; color: #fff; padding: .5rem .85rem; font-size: .8rem; font-weight: 900; text-decoration: none; }
        .statement-print-area { padding: 1rem; }
        .statement-print-header { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; margin-bottom: .75rem; }
        .statement-print-header h2 { margin: 0; font-size: 1.05rem; font-weight: 900; }
        .statement-print-header div { color: #475569; font-size: .8rem; font-weight: 700; }

        @media print {
            body * { visibility: hidden !important; }
            #statement-print-area, #statement-print-area * { visibility: visible !important; }
            #statement-print-area { position: fixed; inset: 0; padding: 0; background: #fff; }
            .erp-top-nav, header, .no-print, .statement-modal-header, .statement-modal-actions { display: none !important; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #94a3b8; padding: 6px; font-size: 12px; }
        }
    </style>

    <script>
        (() => {
            const statements = @js($summaries);
            const modal = document.getElementById('statement-modal');
            const closeButton = document.getElementById('statement-modal-close');
            const printButton = document.getElementById('statement-print-button');
            const title = document.getElementById('statement-modal-title');
            const subtitle = document.getElementById('statement-modal-subtitle');
            const printTitle = document.getElementById('statement-print-title');
            const printMeta = document.getElementById('statement-print-meta');
            const linesBody = document.getElementById('statement-lines');

            const formatNumber = (value) => Number(value || 0).toLocaleString('fa-IR');

            const openStatement = (statement) => {
                title.textContent = `صورتحساب ${statement.party_name}`;
                subtitle.textContent = `مانده: ${formatNumber(Math.abs(statement.balance))} - ${statement.balance_type}`;
                printTitle.textContent = `صورتحساب ${statement.party_name}`;
                printMeta.textContent = `کد: ${statement.party_code} | جمع بدهکار: ${formatNumber(statement.debit)} | جمع بستانکار: ${formatNumber(statement.credit)} | مانده: ${formatNumber(Math.abs(statement.balance))} ${statement.balance_type}`;
                const printUrl = new URL(@json(route('financial-reports.statement.print')), window.location.origin);
                const currentParams = new URLSearchParams(window.location.search);
                currentParams.forEach((value, key) => printUrl.searchParams.set(key, value));
                printUrl.searchParams.set('party_id', statement.party_id);
                printButton.href = printUrl.toString();

                linesBody.innerHTML = '';
                statement.lines.forEach((line) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${line.date || '-'}</td>
                        <td>${line.document_number || '-'}</td>
                        <td>${line.account || '-'}</td>
                        <td>${line.description || '-'}</td>
                        <td>${formatNumber(line.debit)}</td>
                        <td>${formatNumber(line.credit)}</td>
                        <td>${formatNumber(line.running_balance)}</td>
                    `;
                    linesBody.appendChild(row);
                });

                modal.hidden = false;
            };

            document.querySelectorAll('.statement-summary-row').forEach((row) => {
                row.addEventListener('click', () => openStatement(statements[Number(row.dataset.statementIndex)]));
            });

            const closeModal = () => { modal.hidden = true; };
            closeButton?.addEventListener('click', closeModal);
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });
        })();
    </script>
</x-app-layout>

