function hydrateErpTables() {
    document.querySelectorAll('.erp-shell table').forEach((table) => {
        const headers = Array.from(table.querySelectorAll('thead th')).map((th) =>
            th.textContent.trim()
        );

        if (!headers.length) {
            return;
        }

        table.querySelectorAll('tbody tr').forEach((row) => {
            Array.from(row.children).forEach((cell, index) => {
                if (!cell.hasAttribute('data-label')) {
                    cell.setAttribute('data-label', headers[index] || '');
                }
            });
        });
    });
}

let erpHydrateTimer = null;

function scheduleErpHydration() {
    window.clearTimeout(erpHydrateTimer);
    erpHydrateTimer = window.setTimeout(hydrateErpTables, 40);
}

function enableErpSpaLinks() {
    document.removeEventListener('click', handleErpSpaClick);
    document.addEventListener('click', handleErpSpaClick);
}

function enableErpDropdowns() {
    document.removeEventListener('click', handleErpDropdownClick);
    document.addEventListener('click', handleErpDropdownClick);
}

function erpInputBindingHaystack(input) {
    if (!input) {
        return '';
    }

    const parts = [
        input.getAttribute('name') || '',
        input.id || '',
    ];

    Array.from(input.attributes).forEach((attribute) => {
        if (attribute.name.startsWith('wire:model')) {
            parts.push(attribute.value);
        }
    });

    return parts.join(' ').toLowerCase();
}

function isErpDateField(input) {
    if (!input) {
        return false;
    }

    if (input.dataset.jalaliReady === '1' || input.dataset.jalaliDatepicker === '1' || input.hasAttribute('data-jalali-datepicker')) {
        return true;
    }

    const haystack = erpInputBindingHaystack(input);

    return /(^|[\[\]_.-])(date|date_from|date_to|start_date|end_date|document_date|transaction_date|invoice_date|work_date|effective_date|planned_start_date|planned_end_date|actual_start_date|actual_end_date|leave_date|mission_date|payment_date|hire_date|termination_date|issued_at|expires_at|start_date_fa|end_date_fa|from_date|to_date|period_date|due_date|delivery_date|due_at|expected_close_date|close_date|sold_at)(?=$|[\s\[\]_.-])/i.test(haystack);
}

function jalaliDatePlaceholderFor(input) {
    const name = erpInputBindingHaystack(input);
    if (name.includes('from') || name.includes('start')) {
        return '1403/01/01';
    }
    if (name.includes('to') || name.includes('end')) {
        return '1403/12/29';
    }
    return '1403/03/17';
}

function normalizeErpDateField(input) {
    input.dataset.jalaliReady = '1';
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('inputmode', 'numeric');
    input.setAttribute('placeholder', input.getAttribute('placeholder') || jalaliDatePlaceholderFor(input));
    input.dir = 'ltr';
    input.classList.add('erp-jalali-date-input');
}

function isErpMoneyField(input) {
    if (!input || input.disabled) {
        return false;
    }

    if (input.closest('.invoice-editor')) {
        return false;
    }

    if (input.classList.contains('line-quantity')
        || input.classList.contains('line-price')
        || input.classList.contains('line-discount')
        || input.classList.contains('line-tax-rate')) {
        return false;
    }

    if (input.dataset.erpMoney === '0' || input.dataset.erpNumber === '0') {
        return false;
    }

    if (input.dataset.erpMoney === '1') {
        return true;
    }

    if (input.dataset.erpNumber === '1') {
        return true;
    }

    if (input.type === 'hidden' || input.type === 'file' || input.type === 'password' || input.type === 'checkbox' || input.type === 'radio' || input.type === 'submit' || input.type === 'button') {
        return false;
    }

    const haystack = erpInputBindingHaystack(input);

    if (isErpDateField(input)) {
        return false;
    }

    if (/(^|[\[\]_.-])date|_date|date_|transaction_date|document_date|invoice_date|work_date|payment_date/i.test(haystack)) {
        return false;
    }

    if (/(code|phone|mobile|tel|fax|national_id|economic_code|postal_code|iban|serial|tracking|reference|token|password|username|email|account|card|passport|employee_code|invoice_number|document_number)/i.test(haystack)) {
        return false;
    }

    return input.type === 'number'
        || input.inputMode === 'decimal'
        || input.inputMode === 'numeric'
        || input.dataset.erpMoney === '1'
        || /(^|[\[\]_.-])(amount|price|salary|cost|fee|tax|discount|balance|payment|paid|debit|credit|subtotal|total|unit_cost|hourly_rate|overtime_rate|overtime_salary|bonus|deduction|advance_payment|sale_price|purchase_price|net_salary|grand_total|quantity|qty|count|rate|ratio|percent|percentage|age|score|points)(?=$|[\[\]_.-])/i.test(haystack);
}

function normalizeErpMoneyValue(value) {
    const raw = normalizeDigits(String(value ?? '')).replace(/[,\s٬]/g, '').replace(/[٫]/g, '.').replace(/[^\d.-]/g, '');
    if (!raw) {
        return '';
    }

    const negative = raw.startsWith('-');
    const unsigned = raw.replace(/-/g, '');
    const [integerPart = '', ...fractionParts] = unsigned.split('.');
    const fractionPart = fractionParts.join('');

    const normalizedInteger = integerPart.replace(/^0+(?=\d)/, '') || '0';
    const formattedInteger = normalizedInteger.replace(/\B(?=(\d{3})+(?!\d))/g, '٬');

    const fractionSuffix = fractionPart ? `٫${fractionPart}` : '';

    return toPersianNumber(`${negative ? '-' : ''}${formattedInteger}${fractionSuffix}`);
}

function erpMoneyRawValue(value) {
    const raw = normalizeDigits(String(value ?? '')).replace(/[,\s٬]/g, '').replace(/[٫]/g, '.').replace(/[^\d.-]/g, '');
    if (!raw) {
        return '';
    }

    const negative = raw.startsWith('-');
    const unsigned = raw.replace(/-/g, '');
    const [integerPart = '', ...fractionParts] = unsigned.split('.');
    const fractionPart = fractionParts.join('');
    const normalizedInteger = integerPart.replace(/^0+(?=\d)/, '') || '0';

    return `${negative ? '-' : ''}${normalizedInteger}${fractionPart ? `.${fractionPart}` : ''}`;
}

function syncLivewireInput(input) {
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));

    const componentEl = input.closest('[wire\\:id]');

    if (!componentEl || !window.Livewire) {
        return;
    }

    const component = window.Livewire.find(componentEl.getAttribute('wire:id'));

    if (!component) {
        return;
    }

    Array.from(input.attributes).forEach((attribute) => {
        if (!attribute.name.startsWith('wire:model')) {
            return;
        }

        const property = attribute.value.trim();

        if (property) {
            component.set(property, input.value);
        }
    });
}

function syncJalaliInputWithLivewire(input) {
    syncLivewireInput(input);
}

function formatErpMoneyInput(input, sync = true) {
    const raw = erpMoneyRawValue(input.value);
    input.value = raw === '' ? '' : normalizeErpMoneyValue(raw);

    if (sync) {
        syncLivewireInput(input);
    }
}

function enableErpMoneyInputs() {
    document.querySelectorAll('input').forEach((input) => {
        if (!isErpMoneyField(input)) {
            return;
        }

        if (input.__erpMoneyBound) {
            if (input.value) {
                formatErpMoneyInput(input, false);
            }

            return;
        }

        input.__erpMoneyBound = true;
        input.dataset.erpMoneyReady = '1';
        input.autocomplete = 'off';
        input.setAttribute('inputmode', input.step && input.step !== '1' ? 'decimal' : 'numeric');
        input.dir = 'ltr';
        input.classList.add('text-left', 'tabular-nums');

        if (input.type === 'number') {
            input.type = 'text';
        }

        input.addEventListener('focus', () => {
            input.value = erpMoneyRawValue(input.value);
        });

        input.addEventListener('input', () => {
            formatErpMoneyInput(input, false);
        });

        input.addEventListener('blur', () => {
            formatErpMoneyInput(input, true);
        });

        const form = input.closest('form');
        if (form && form.dataset.erpMoneySubmitReady !== '1') {
            form.dataset.erpMoneySubmitReady = '1';
            form.addEventListener('submit', () => {
                form.querySelectorAll('input[data-erp-money-ready="1"]').forEach((moneyInput) => {
                    moneyInput.value = erpMoneyRawValue(moneyInput.value);
                });
            }, true);
        }

        if (input.value) {
            formatErpMoneyInput(input, false);
        }
    });
}

function enableJalaliDatepickers() {
    const inputs = new Set();

    document.querySelectorAll('input[data-jalali-datepicker], input[type="text"][name*="date"], input[type="text"][id*="date"]').forEach((input) => {
        inputs.add(input);
    });

    document.querySelectorAll('input[type="text"], input:not([type])').forEach((input) => {
        if (isErpDateField(input)) {
            inputs.add(input);
        }
    });

    inputs.forEach((input) => {
        if (input.type === 'hidden') {
            return;
        }

        normalizeErpDateField(input);

        // Livewire morph can copy data-jalali-ready without keeping listeners.
        // Bind once per DOM node via a JS flag that does not survive node replacement.
        if (input.__erpJalaliBound) {
            return;
        }
        input.__erpJalaliBound = true;

        if (input.value) {
            input.value = isJalaliDateTimeInput(input)
                ? formatJalaliDateTimeInput(input.value)
                : formatJalaliDateInput(input.value);
        }

        input.addEventListener('input', () => {
            input.value = isJalaliDateTimeInput(input)
                ? formatJalaliDateTimeInput(input.value)
                : formatJalaliDateInput(input.value);
        });

        input.addEventListener('keydown', (event) => {
            // Keep typing usable; only open calendar with Alt+ArrowDown / F4.
            if (event.key === 'F4' || (event.altKey && event.key === 'ArrowDown')) {
                event.preventDefault();
                showJalaliPicker(input);
            }
        });

        input.addEventListener('click', () => showJalaliPicker(input));
    });

    document.querySelectorAll('[data-jalali-datepicker-trigger]').forEach((trigger) => {
        if (trigger.__erpJalaliTriggerBound) {
            return;
        }
        trigger.__erpJalaliTriggerBound = true;

        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();

            const root = trigger.closest('div, label, td, .relative') || trigger.parentElement;
            const input = root?.querySelector('input[data-jalali-datepicker], input.erp-jalali-date-input')
                || trigger.previousElementSibling;

            if (input && input.tagName === 'INPUT') {
                input.focus({ preventScroll: true });
                showJalaliPicker(input);
            }
        });
    });

    document.removeEventListener('click', handleJalaliOutsideClick);
    document.addEventListener('click', handleJalaliOutsideClick);
}

function enableErpDeleteConfirms() {
    document.querySelectorAll('form').forEach((form) => {
        const methodInput = form.querySelector('input[name="_method"]');
        const isDelete = methodInput && methodInput.value.toUpperCase() === 'DELETE';

        if (!isDelete || form.dataset.erpDeleteReady === '1') {
            return;
        }

        const inlineConfirm = form.getAttribute('onsubmit') || '';
        const match = inlineConfirm.match(/confirm\((['"])(.*?)\1\)/);

        form.dataset.erpDeleteReady = '1';
        form.dataset.erpConfirmMessage = form.dataset.erpConfirmMessage || (match ? match[2] : 'آیا از حذف این مورد مطمئن هستید؟');
        form.removeAttribute('onsubmit');
    });

    document.removeEventListener('submit', handleErpDeleteSubmit, true);
    document.addEventListener('submit', handleErpDeleteSubmit, true);
}

let pendingDeleteForm = null;

function handleErpDeleteSubmit(event) {
    const form = event.target;
    const methodInput = form.querySelector?.('input[name="_method"]');

    if (!methodInput || methodInput.value.toUpperCase() !== 'DELETE' || form.dataset.erpDeleteConfirmed === '1') {
        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();
    pendingDeleteForm = form;
    showErpDeleteConfirm(form.dataset.erpConfirmMessage || 'آیا از حذف این مورد مطمئن هستید؟');
}

function ensureErpDeleteConfirm() {
    let modal = document.getElementById('erp-delete-confirm');

    if (modal) {
        return modal;
    }

    modal = document.createElement('div');
    modal.id = 'erp-delete-confirm';
    modal.className = 'erp-delete-confirm';
    modal.hidden = true;
    modal.innerHTML = `
        <div class="erp-delete-confirm-card" role="dialog" aria-modal="true" aria-labelledby="erp-delete-confirm-title">
            <h3 class="erp-delete-confirm-title" id="erp-delete-confirm-title">تایید حذف</h3>
            <p class="erp-delete-confirm-message" data-erp-delete-message></p>
            <div class="erp-delete-confirm-actions">
                <button type="button" class="erp-delete-confirm-cancel" data-erp-delete-cancel>انصراف</button>
                <button type="button" class="erp-delete-confirm-submit" data-erp-delete-submit>حذف شود</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    modal.addEventListener('click', (event) => {
        if (event.target === modal || event.target.closest('[data-erp-delete-cancel]')) {
            hideErpDeleteConfirm();
            return;
        }

        if (event.target.closest('[data-erp-delete-submit]')) {
            const form = pendingDeleteForm;
            hideErpDeleteConfirm();

            if (form) {
                form.dataset.erpDeleteConfirmed = '1';
                form.requestSubmit();
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            hideErpDeleteConfirm();
        }
    });

    return modal;
}

function showErpDeleteConfirm(message) {
    const modal = ensureErpDeleteConfirm();
    modal.querySelector('[data-erp-delete-message]').textContent = message;
    modal.hidden = false;
    document.body.classList.add('erp-modal-open');
}

function hideErpDeleteConfirm() {
    const modal = document.getElementById('erp-delete-confirm');
    if (modal) {
        modal.hidden = true;
    }

    pendingDeleteForm = null;
    document.body.classList.remove('erp-modal-open');
}

const ERP_FLASH_TIMEOUTS = {
    danger: 15000,
    error: 15000,
    success: 10000,
    warning: 10000,
    info: 10000,
};

function ensureToastStack() {
    let stack = document.getElementById('erp-toast-stack');

    if (!stack) {
        stack = document.createElement('div');
        stack.id = 'erp-toast-stack';
        stack.className = 'erp-toast-stack';
        document.body.appendChild(stack);
    }

    return stack;
}

function resolveFlashTimeout(tone) {
    return ERP_FLASH_TIMEOUTS[tone] ?? 10000;
}

function dismissFlashToast(flash) {
    if (!flash || flash.dataset.erpFlashClosing === '1') {
        return;
    }

    flash.dataset.erpFlashClosing = '1';
    flash.classList.add('is-leaving');
    window.setTimeout(() => flash.remove(), 180);
}

function initFlashToast(flash) {
    if (!flash || flash.dataset.erpFlashReady === '1') {
        return;
    }

    flash.dataset.erpFlashReady = '1';

    const tone = flash.dataset.erpFlashTone || 'success';
    const message = flash.querySelector('.erp-flash-message')?.textContent?.trim() || '';

    if (message && shouldSkipDuplicateToast(message, tone)) {
        flash.remove();

        return;
    }

    const close = () => dismissFlashToast(flash);

    flash.querySelector('[data-erp-flash-close]')?.addEventListener('click', close);
    window.setTimeout(close, resolveFlashTimeout(tone));
}

function collectLivewireValidationMessages(component) {
    const errors = component?.$wire?.$errors;

    if (!errors || typeof errors.isEmpty !== 'function' || errors.isEmpty()) {
        return [];
    }

    return errors.keys()
        .map((key) => errors.first(key))
        .filter((message) => Boolean(String(message || '').trim()));
}

function processLivewireValidationToasts(component) {
    const messages = collectLivewireValidationMessages(component);

    if (!messages.length || typeof window.showErpToast !== 'function') {
        return;
    }

    window.showErpToast(messages[0], 'danger', {
        details: messages.slice(1),
    });
}

function enableLivewireValidationToasts() {
    if (!window.Livewire?.hook || window.__erpLivewireValidationToastsEnabled) {
        return;
    }

    window.__erpLivewireValidationToastsEnabled = true;

    window.Livewire.hook('commit', ({ component, succeed }) => {
        succeed(() => {
            window.requestAnimationFrame(() => {
                processLivewireValidationToasts(component);
            });
        });
    });
}

function processLivewireFlashBridges() {
    document.querySelectorAll('.erp-livewire-flash-bridge[data-erp-livewire-flash-message]').forEach((node) => {
        const tone = node.dataset.erpLivewireFlashTone || 'success';
        let message = '';

        try {
            message = JSON.parse(node.dataset.erpLivewireFlashMessage || '""');
        } catch {
            message = node.dataset.erpLivewireFlashMessage || '';
        }
        let details = [];

        if (node.dataset.erpLivewireFlashDetails) {
            try {
                details = JSON.parse(node.dataset.erpLivewireFlashDetails);
            } catch {
                details = [];
            }
        }

        if (message && typeof window.showErpToast === 'function') {
            window.showErpToast(message, tone, { details });
        }

        node.remove();
    });
}

function enableErpFlashMessages() {
    const stack = ensureToastStack();

    document.querySelectorAll('[data-erp-flash]').forEach((flash) => {
        if (!stack.contains(flash)) {
            stack.appendChild(flash);
        }

        initFlashToast(flash);
    });

    processLivewireFlashBridges();
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

const recentErpToasts = new Map();

function shouldSkipDuplicateToast(message, tone) {
    const key = `${tone}::${String(message).trim()}`;

    if (!key.endsWith('::')) {
        const now = Date.now();
        const lastShownAt = recentErpToasts.get(key);

        if (lastShownAt && now - lastShownAt < 3000) {
            return true;
        }

        recentErpToasts.set(key, now);
    }

    return false;
}

function showErpToast(message, tone = 'success', options = {}) {
    const normalizedTone = tone === 'error' ? 'danger' : tone;

    if (shouldSkipDuplicateToast(message, normalizedTone)) {
        return null;
    }
    const titles = {
        success: 'انجام شد',
        danger: 'خطا',
        warning: 'هشدار',
        info: 'اطلاع',
    };
    const icons = {
        success: '✓',
        danger: '×',
        warning: '!',
        info: 'i',
    };
    const title = options.title || titles[normalizedTone] || titles.success;
    const details = Array.isArray(options.details) ? options.details : [];
    const stack = ensureToastStack();
    const toast = document.createElement('div');

    toast.className = `erp-flash-toast is-${normalizedTone}`;
    toast.dataset.erpFlash = '';
    toast.dataset.erpFlashTone = normalizedTone;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'polite');

    const detailsHtml = details.length
        ? `<ul class="erp-flash-details">${details.map((detail) => `<li>${escapeHtml(detail)}</li>`).join('')}</ul>`
        : '';

    toast.innerHTML = `
        <button type="button" class="erp-flash-close" data-erp-flash-close aria-label="بستن">×</button>
        <div class="erp-flash-icon" aria-hidden="true">${icons[normalizedTone] || icons.success}</div>
        <div class="min-w-0">
            <div class="erp-flash-title">${escapeHtml(title)}</div>
            <div class="erp-flash-message">${escapeHtml(message)}</div>
            ${detailsHtml}
        </div>
    `;

    stack.appendChild(toast);
    initFlashToast(toast);

    return toast;
}

window.showErpToast = showErpToast;

let activeJalaliInput = null;
let activeJalaliMonth = null;

const jalaliMonthNames = [
    'فروردین',
    'اردیبهشت',
    'خرداد',
    'تیر',
    'مرداد',
    'شهریور',
    'مهر',
    'آبان',
    'آذر',
    'دی',
    'بهمن',
    'اسفند',
];

const jalaliWeekdays = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

function showJalaliPicker(input) {
    activeJalaliInput = input;
    activeJalaliMonth = parseJalaliDate(input.value) || todayJalaliParts();
    renderJalaliPicker();
}

function handleJalaliOutsideClick(event) {
    const picker = document.querySelector('.erp-jalali-picker');

    if (!picker || event.target.closest('.erp-jalali-picker') || event.target.closest('input[data-jalali-datepicker], input.erp-jalali-date-input, input[type="text"][name*="date"], input[type="text"][id*="date"], [data-jalali-datepicker-trigger]')) {
        return;
    }

    picker.remove();
    activeJalaliInput = null;
}

function renderJalaliPicker() {
    if (!activeJalaliInput || !activeJalaliMonth) {
        return;
    }

    document.querySelector('.erp-jalali-picker')?.remove();

    const picker = document.createElement('div');
    picker.className = 'erp-jalali-picker';
    picker.dir = 'rtl';

    const header = document.createElement('div');
    header.className = 'erp-jalali-picker-header';

    const prev = document.createElement('button');
    prev.type = 'button';
    prev.textContent = '‹';
    prev.addEventListener('click', () => changeJalaliMonth(-1));

    const title = document.createElement('strong');
    title.textContent = `${jalaliMonthNames[activeJalaliMonth.month - 1]} ${toPersianNumber(activeJalaliMonth.year)}`;

    const next = document.createElement('button');
    next.type = 'button';
    next.textContent = '›';
    next.addEventListener('click', () => changeJalaliMonth(1));

    header.append(next, title, prev);
    picker.appendChild(header);

    const grid = document.createElement('div');
    grid.className = 'erp-jalali-picker-grid';

    jalaliWeekdays.forEach((day) => {
        const weekday = document.createElement('span');
        weekday.className = 'erp-jalali-picker-weekday';
        weekday.textContent = day;
        grid.appendChild(weekday);
    });

    const firstWeekday = jalaliWeekday(activeJalaliMonth.year, activeJalaliMonth.month, 1);
    const daysInMonth = jalaliMonthLength(activeJalaliMonth.year, activeJalaliMonth.month);
    const selected = parseJalaliDate(activeJalaliInput.value);
    const today = todayJalaliParts();

    for (let i = 0; i < firstWeekday; i += 1) {
        grid.appendChild(document.createElement('span'));
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = toPersianNumber(day);
        button.className = 'erp-jalali-picker-day';

        if (selected && selected.year === activeJalaliMonth.year && selected.month === activeJalaliMonth.month && selected.day === day) {
            button.classList.add('is-selected');
        }

        if (today.year === activeJalaliMonth.year && today.month === activeJalaliMonth.month && today.day === day) {
            button.classList.add('is-today');
        }

        button.addEventListener('click', () => {
            setJalaliInputDate(activeJalaliInput, activeJalaliMonth.year, activeJalaliMonth.month, day);
            picker.remove();
        });

        grid.appendChild(button);
    }

    picker.appendChild(grid);

    const footer = document.createElement('div');
    footer.className = 'erp-jalali-picker-footer';

    const todayButton = document.createElement('button');
    todayButton.type = 'button';
    todayButton.textContent = 'امروز';
    todayButton.addEventListener('click', () => {
        const parts = todayJalaliParts();
        setJalaliInputDate(activeJalaliInput, parts.year, parts.month, parts.day);
        picker.remove();
    });

    footer.appendChild(todayButton);
    picker.appendChild(footer);

    document.body.appendChild(picker);
    positionJalaliPicker(picker, activeJalaliInput);
}

function positionJalaliPicker(picker, input) {
    const rect = input.getBoundingClientRect();
    let top = rect.bottom + window.scrollY + 6;
    const maxTop = window.scrollY + document.documentElement.clientHeight - picker.offsetHeight - 8;
    if (top > maxTop) {
        top = Math.max(8 + window.scrollY, rect.top + window.scrollY - picker.offsetHeight - 6);
    }
    const maxLeft = window.scrollX + document.documentElement.clientWidth - picker.offsetWidth - 8;
    const left = Math.max(8 + window.scrollX, Math.min(rect.left + window.scrollX, maxLeft));

    picker.style.top = `${top}px`;
    picker.style.left = `${left}px`;
}

function changeJalaliMonth(step) {
    let month = activeJalaliMonth.month + step;
    let year = activeJalaliMonth.year;

    if (month < 1) {
        month = 12;
        year -= 1;
    } else if (month > 12) {
        month = 1;
        year += 1;
    }

    activeJalaliMonth = { year, month, day: 1 };
    renderJalaliPicker();
}

function isJalaliDateTimeInput(input) {
    return input?.hasAttribute?.('data-jalali-datetime');
}

function extractJalaliTimeSuffix(value) {
    const match = String(value ?? '').match(/\s+(\d{1,2}:\d{0,2})$/);

    return match ? ` ${match[1]}` : '';
}

function setJalaliInputDate(input, year, month, day) {
    const dateValue = `${year}/${pad2(month)}/${pad2(day)}`;
    input.value = isJalaliDateTimeInput(input)
        ? `${dateValue}${extractJalaliTimeSuffix(input.value)}`
        : toPersianNumber(dateValue);
    syncJalaliInputWithLivewire(input);
}

function formatJalaliDateInput(value) {
    const digits = normalizeDigits(value).replace(/[^\d]/g, '').slice(0, 8);
    let formatted = '';

    if (digits.length <= 4) {
        formatted = digits;
    } else if (digits.length <= 6) {
        formatted = `${digits.slice(0, 4)}/${digits.slice(4)}`;
    } else {
        formatted = `${digits.slice(0, 4)}/${digits.slice(4, 6)}/${digits.slice(6)}`;
    }

    return toPersianNumber(formatted);
}

function formatJalaliDateTimeInput(value) {
    const raw = String(value ?? '');
    const spaceIndex = raw.search(/\s/);
    const datePart = spaceIndex >= 0 ? raw.slice(0, spaceIndex) : raw;
    const timePart = spaceIndex >= 0 ? raw.slice(spaceIndex + 1) : '';
    const formattedDate = formatJalaliDateInput(datePart);

    if (timePart === '' && !raw.includes(' ')) {
        return formattedDate;
    }

    const timeDigits = normalizeDigits(timePart).replace(/[^\d]/g, '').slice(0, 4);
    let formattedTime = '';

    if (timeDigits.length <= 2) {
        formattedTime = timeDigits;
    } else {
        formattedTime = `${timeDigits.slice(0, 2)}:${timeDigits.slice(2)}`;
    }

    return formattedTime === '' ? `${formattedDate} ` : `${formattedDate} ${formattedTime}`;
}

function parseJalaliDate(value) {
    const parts = normalizeDigits(String(value ?? '').trim()).match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})/);

    if (!parts) {
        return null;
    }

    const year = Number(parts[1]);
    const month = Number(parts[2]);
    const day = Number(parts[3]);

    if (month < 1 || month > 12 || day < 1 || day > jalaliMonthLength(year, month)) {
        return null;
    }

    return { year, month, day };
}

function todayJalaliParts() {
    const formatted = new Intl.DateTimeFormat('en-US-u-ca-persian', {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
    }).format(new Date());
    const match = formatted.match(/(\d+)\/(\d+)\/(\d+)/);

    return {
        month: Number(match[1]),
        day: Number(match[2]),
        year: Number(match[3]),
    };
}

function jalaliWeekday(year, month, day) {
    const date = jalaliToGregorian(year, month, day);
    return (date.getDay() + 1) % 7;
}

function jalaliMonthLength(year, month) {
    if (month <= 6) {
        return 31;
    }

    if (month <= 11) {
        return 30;
    }

    return isJalaliLeapYear(year) ? 30 : 29;
}

function isJalaliLeapYear(year) {
    return [1, 5, 9, 13, 17, 22, 26, 30].includes(((year - 474) % 2820 + 2820) % 2820 % 33);
}

function jalaliToGregorian(jy, jm, jd) {
    jy += 1595;
    let days = -355668 + (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + jd;

    if (jm < 7) {
        days += (jm - 1) * 31;
    } else {
        days += ((jm - 7) * 30) + 186;
    }

    let gy = 400 * Math.floor(days / 146097);
    days %= 146097;

    if (days > 36524) {
        gy += 100 * Math.floor(--days / 36524);
        days %= 36524;

        if (days >= 365) {
            days += 1;
        }
    }

    gy += 4 * Math.floor(days / 1461);
    days %= 1461;

    if (days > 365) {
        gy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }

    const gd = days + 1;
    const salA = [0, 31, isGregorianLeap(gy) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    let gm = 0;
    let day = gd;

    for (gm = 1; gm <= 12 && day > salA[gm]; gm += 1) {
        day -= salA[gm];
    }

    return new Date(gy, gm - 1, day);
}

function isGregorianLeap(year) {
    return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
}

function normalizeDigits(value) {
    const persian = '۰۱۲۳۴۵۶۷۸۹';
    const arabic = '٠١٢٣٤٥٦٧٨٩';

    return String(value || '').replace(/[۰-۹٠-٩]/g, (digit) => {
        const persianIndex = persian.indexOf(digit);

        if (persianIndex >= 0) {
            return String(persianIndex);
        }

        return String(arabic.indexOf(digit));
    });
}

function toPersianNumber(value) {
    return String(value).replace(/\d/g, (digit) => '۰۱۲۳۴۵۶۷۸۹'[digit]);
}

function normalizeSearchText(value) {
    return String(value ?? '')
        .trim()
        .toLowerCase()
        .replace(/[۰-۹٠-٩]/g, (digit) => {
            const persian = '۰۱۲۳۴۵۶۷۸۹';
            const arabic = '٠١٢٣٤٥٦٧٨٩';
            const persianIndex = persian.indexOf(digit);

            if (persianIndex >= 0) {
                return String(persianIndex);
            }

            return String(arabic.indexOf(digit));
        });
}

const LOOKUP_SELECT_EXCLUDE = /(?:^|\.|")((type|status|direction|payment_status|month|year|employment_type|gender|document_type|line_type|sort|order|limit|per_page|role|action|from_treasury_type|to_treasury_type))(?:\[|\]|$)/i;
const LOOKUP_SELECT_INCLUDE = /(?:^|\.|")(party_id|project_id|warehouse_id|target_warehouse_id|initial_warehouse_id|item_id|component_item_id|chart_account_id|detail_account_id|account_id|bank_account_id|cashbox_id|employee_id|measurement_unit_id|position_id|job_id|organization_unit_id|default_project_id|project_manager_id|fiscal_year_id|cost_center_id|cost_center|bom_version_id|work_shift_id|work_calendar_id|work_group_id|payroll_period_id|parent_id|created_by|from_treasury_id|to_treasury_id|user_id|unit_id|partner_chart_account_code|supervisor_position_id|selectedSalaryItemId|selectedDeductionItemId|party|project|warehouse|employee|item|bank|cashbox)(?:\[|\]|$)/i;

function getSelectBindingKey(select) {
    return select.name
        || select.getAttribute('wire:model')
        || select.getAttribute('wire:model.live')
        || select.getAttribute('wire:model.live.debounce.400ms')
        || select.getAttribute('wire:model.defer')
        || select.id
        || '';
}

function shouldUpgradeSelectToLookup(select) {
    if (!select || select.tagName !== 'SELECT') {
        return false;
    }

    if (select.dataset.erpLookupUpgraded === '1') {
        return false;
    }

    if (select.closest('[data-erp-search-select]')) {
        return false;
    }

    if (select.dataset.erpNoLookup === '1') {
        return false;
    }

    const realOptions = Array.from(select.options).filter((option) => option.value !== '');

    if (realOptions.length < 2) {
        return false;
    }

    if (select.hasAttribute('data-erp-lookup-select')) {
        return true;
    }

    const key = getSelectBindingKey(select);

    if (!key || LOOKUP_SELECT_EXCLUDE.test(key)) {
        return false;
    }

    return LOOKUP_SELECT_INCLUDE.test(key) || /_id(\[\])?$/.test(key);
}

function parseNativeSelectOptions(select) {
    const placeholderOption = select.options[0];
    const placeholder = placeholderOption && placeholderOption.value === ''
        ? placeholderOption.textContent.trim()
        : 'جستجو یا انتخاب...';

    const options = Array.from(select.options)
        .filter((option) => option.value !== '')
        .map((option) => ({
            id: option.value,
            label: option.textContent.trim(),
        }));

    return { options, placeholder };
}

function dispatchSearchSelectValueEvents(hidden) {
    hidden.dispatchEvent(new Event('change', { bubbles: true }));
    syncLivewireInput(hidden);
}

function upgradeNativeSelectToSearchSelect(select) {
    if (!shouldUpgradeSelectToLookup(select)) {
        return null;
    }

    const { options, placeholder } = parseNativeSelectOptions(select);
    const wrapper = document.createElement('div');
    wrapper.className = 'erp-search-select';

    select.classList.forEach((className) => {
        if (className && className !== 'erp-search-select' && !className.startsWith('erp-search-select__')) {
            wrapper.classList.add(className);
        }
    });

    wrapper.dataset.erpSearchSelect = '';
    wrapper.dataset.options = JSON.stringify(options);
    wrapper.dataset.erpLookupUpgrade = '1';

    if (select.id) {
        wrapper.dataset.erpLookupFor = select.id;
    }

    const hidden = document.createElement('input');
    hidden.type = 'hidden';

    if (select.name) {
        hidden.name = select.name;
    }

    hidden.value = select.value;

    if (select.required) {
        hidden.required = true;
    }

    if (select.disabled) {
        hidden.disabled = true;
    }

    Array.from(select.attributes).forEach((attribute) => {
        if (attribute.name.startsWith('wire:')) {
            hidden.setAttribute(attribute.name, attribute.value);
        }
    });

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'erp-search-select__input';

    if (select.classList.contains('w-full')) {
        input.classList.add('w-full');
    }

    input.placeholder = placeholder;
    input.autocomplete = 'off';
    input.spellcheck = false;

    if (select.disabled) {
        input.disabled = true;
    }

    const list = document.createElement('div');
    list.className = 'erp-search-select__list';
    list.hidden = true;

    wrapper.appendChild(hidden);
    wrapper.appendChild(input);
    wrapper.appendChild(list);

    select.parentNode.insertBefore(wrapper, select);
    select.dataset.erpLookupUpgraded = '1';
    select.dataset.erpLookupMirror = '1';
    select.removeAttribute('name');
    select.removeAttribute('required');
    select.tabIndex = -1;
    select.style.display = 'none';
    select.setAttribute('aria-hidden', 'true');

    hidden.addEventListener('change', () => {
        select.value = hidden.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
    });

    hidden.addEventListener('input', () => {
        select.value = hidden.value;
    });

    bindErpSearchSelect(wrapper);

    wrapper.__erpLookupMirrorSelect = select;
    select.__erpLookupWrapper = wrapper;

    observeLookupMirrorDisabledState(select);

    return wrapper;
}

function observeLookupMirrorDisabledState(select) {
    if (!select || select.__erpLookupDisabledObserver) {
        return;
    }

    const sync = () => {
        syncLookupMirrorStates(select.closest('form') || select.parentElement || document);
    };

    const observer = new MutationObserver(sync);
    observer.observe(select, { attributes: true, attributeFilter: ['disabled'] });
    select.__erpLookupDisabledObserver = observer;

    select.addEventListener('change', sync);
}

function syncLookupMirrorStates(scope = document) {
    scope.querySelectorAll('select[data-erp-lookup-mirror="1"]').forEach((mirror) => {
        const wrapper = mirror.__erpLookupWrapper || mirror.previousElementSibling;

        if (!wrapper?.dataset?.erpLookupUpgrade) {
            return;
        }

        const hidden = wrapper.querySelector('input[type="hidden"]');
        const input = wrapper.querySelector('.erp-search-select__input');

        if (!hidden || !input) {
            return;
        }

        hidden.disabled = mirror.disabled;
        input.disabled = mirror.disabled;

        if (mirror.value !== hidden.value) {
            window.ErpSearchSelect?.setValue(wrapper, mirror.value);
        }
    });
}

function enableErpLookupSelects(scope = document) {
    scope.querySelectorAll('select').forEach((select) => {
        if (shouldUpgradeSelectToLookup(select)) {
            upgradeNativeSelectToSearchSelect(select);
        }
    });

    syncLookupMirrorStates(scope);
}

function syncGlobalSearchSelectOptionSources(scope = document) {
    const rootScope = scope instanceof Element ? scope : document;
    const node = rootScope.id === 'erp-sold-device-item-options-json'
        ? rootScope
        : rootScope.querySelector('#erp-sold-device-item-options-json')
            || document.getElementById('erp-sold-device-item-options-json');

    if (!node?.textContent) {
        return;
    }

    try {
        window.__erpSoldDeviceItemOptions = JSON.parse(node.textContent.trim());
    } catch {
        window.__erpSoldDeviceItemOptions = [];
    }
}

function resolveSearchSelectOptions(root) {
    syncGlobalSearchSelectOptionSources(root);

    const source = root.dataset.optionsSource;

    if (source === 'invoice-items' && Array.isArray(window.__erpInvoiceItemOptions)) {
        return window.__erpInvoiceItemOptions;
    }

    if (source === 'inventory-items' && Array.isArray(window.__erpInventoryItemOptions)) {
        return window.__erpInventoryItemOptions;
    }

    if (source === 'erp-items' && Array.isArray(window.__erpItemOptions)) {
        return window.__erpItemOptions;
    }

    if (source === 'sold-device-items' && Array.isArray(window.__erpSoldDeviceItemOptions)) {
        return window.__erpSoldDeviceItemOptions;
    }

    try {
        return JSON.parse(root.dataset.options || '[]');
    } catch (error) {
        return [];
    }
}

function formatSearchSelectLabel(option) {
    const code = option.code ? ` (${option.code})` : '';

    return `${option.label || ''}${code}`;
}

function filterSearchSelectOptions(options, query) {
    const normalized = normalizeSearchText(query);

    if (!normalized) {
        return options.slice(0, 25);
    }

    return options
        .filter((option) => {
            const haystack = normalizeSearchText([
                option.label,
                option.code,
                option.category,
                option.type,
            ].filter(Boolean).join(' '));

            return haystack.includes(normalized);
        })
        .slice(0, 30);
}

function releaseErpSearchSelect(root) {
    if (!root?.__erpSearchSelect) {
        return;
    }

    const boundInput = root.__erpSearchSelect.boundInput;

    if (boundInput) {
        delete boundInput.__erpSearchSelectBound;
    }

    delete root.__erpSearchSelect;
    delete root.__erpSearchSelectBoundInput;
    delete root.dataset.searchSelectBound;
}

function bindErpSearchSelect(root) {
    if (!root) {
        return root;
    }

    const hidden = root.querySelector('input[type="hidden"]');
    const input = root.querySelector('.erp-search-select__input');
    const list = root.querySelector('.erp-search-select__list');

    if (!hidden || !input || !list) {
        return root;
    }

    if (root.__erpSearchSelect && root.__erpSearchSelect.boundInput === input) {
        root.__erpSearchSelect.refreshOptions();

        return root;
    }

    // Livewire morph can keep the wrapper but replace inputs without listeners.
    releaseErpSearchSelect(root);

    const state = {
        activeIndex: -1,
        options: resolveSearchSelectOptions(root),
        filtered: [],
        listHome: list.parentElement,
    };

    const positionList = () => {
        if (list.hidden) {
            return;
        }

        const rect = input.getBoundingClientRect();
        const preferredHeight = 224;
        const spaceBelow = window.innerHeight - rect.bottom - 8;
        const spaceAbove = rect.top - 8;
        const openUpward = spaceBelow < 160 && spaceAbove > spaceBelow;
        const maxHeight = Math.max(120, Math.min(preferredHeight, openUpward ? spaceAbove : spaceBelow));

        list.style.position = 'fixed';
        list.style.left = `${Math.max(8, rect.left)}px`;
        list.style.width = `${rect.width}px`;
        list.style.right = 'auto';
        list.style.zIndex = '12000';
        list.style.maxHeight = `${maxHeight}px`;

        if (openUpward) {
            list.style.top = 'auto';
            list.style.bottom = `${window.innerHeight - rect.top + 4}px`;
            root.classList.add('is-open-up');
        } else {
            list.style.top = `${rect.bottom + 4}px`;
            list.style.bottom = 'auto';
            root.classList.remove('is-open-up');
        }
    };

    const repositionList = () => {
        positionList();
    };

    const attachListListeners = () => {
        window.addEventListener('scroll', repositionList, true);
        window.addEventListener('resize', repositionList);
    };

    const detachListListeners = () => {
        window.removeEventListener('scroll', repositionList, true);
        window.removeEventListener('resize', repositionList);
    };

    const mountFloatingList = () => {
        if (list.parentElement !== document.body) {
            document.body.appendChild(list);
        }
    };

    const unmountFloatingList = () => {
        list.style.cssText = '';
        list.classList.remove('erp-search-select__list--floating');

        if (state.listHome && list.parentElement === document.body) {
            state.listHome.appendChild(list);
        }
    };

    const closeList = () => {
        list.hidden = true;
        state.activeIndex = -1;
        root.classList.remove('is-open', 'is-open-up');
        detachListListeners();
        unmountFloatingList();
    };

    const openList = () => {
        mountFloatingList();
        list.hidden = false;
        list.classList.add('erp-search-select__list--floating');
        root.classList.add('is-open');
        positionList();
        attachListListeners();
    };

    const renderList = () => {
        state.filtered = filterSearchSelectOptions(state.options, input.value);
        list.innerHTML = '';

        if (!state.filtered.length) {
            const empty = document.createElement('div');
            empty.className = 'erp-search-select__empty';
            empty.textContent = 'موردی یافت نشد';
            list.appendChild(empty);
            state.activeIndex = -1;

            return;
        }

        state.filtered.forEach((option, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'erp-search-select__option';
            button.dataset.index = String(index);

            const label = document.createElement('span');
            label.className = 'erp-search-select__option-label';
            label.textContent = option.label || '';
            button.appendChild(label);

            if (option.code) {
                const code = document.createElement('span');
                code.className = 'erp-search-select__option-code';
                code.textContent = option.code;
                button.appendChild(code);
            }

            if (option.type) {
                const type = document.createElement('span');
                type.className = 'erp-search-select__option-type';
                type.textContent = option.type;
                button.appendChild(type);
            }

            button.addEventListener('mousedown', (event) => {
                event.preventDefault();
            });
            button.addEventListener('click', () => {
                selectOption(option);
            });
            list.appendChild(button);
        });

        highlightActiveOption();
        positionList();
    };

    const highlightActiveOption = () => {
        list.querySelectorAll('.erp-search-select__option').forEach((element, index) => {
            element.classList.toggle('is-active', index === state.activeIndex);
        });

        const active = list.querySelector('.erp-search-select__option.is-active');

        if (active) {
            active.scrollIntoView({ block: 'nearest' });
        }
    };

    const selectOption = (option) => {
        hidden.value = String(option.id ?? '');
        input.value = formatSearchSelectLabel(option);
        closeList();
        dispatchSearchSelectValueEvents(hidden);
    };

    const syncLabelFromValue = () => {
        const selected = state.options.find((option) => String(option.id) === String(hidden.value));

        input.value = selected ? formatSearchSelectLabel(selected) : '';
    };

    const tryAutoSelect = () => {
        const query = normalizeSearchText(input.value);

        if (!query) {
            hidden.value = '';
            dispatchSearchSelectValueEvents(hidden);

            return;
        }

        const exact = state.options.find((option) => {
            const code = normalizeSearchText(option.code);
            const label = normalizeSearchText(option.label);

            return code === query || label === query;
        });

        if (exact) {
            selectOption(exact);

            return;
        }

        if (state.filtered.length === 1) {
            selectOption(state.filtered[0]);

            return;
        }

        syncLabelFromValue();
    };

    const refreshOptions = () => {
        syncGlobalSearchSelectOptionSources(root);
        state.options = resolveSearchSelectOptions(root);
    };

    input.addEventListener('focus', () => {
        refreshOptions();
        renderList();
        openList();
        input.select();
    });

    input.addEventListener('input', () => {
        hidden.value = '';
        refreshOptions();
        renderList();
        openList();
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (list.hidden) {
                renderList();
                openList();
            }
            state.activeIndex = Math.min(state.activeIndex + 1, Math.max(state.filtered.length - 1, 0));
            highlightActiveOption();

            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            state.activeIndex = Math.max(state.activeIndex - 1, 0);
            highlightActiveOption();

            return;
        }

        if (event.key === 'Enter') {
            if (!list.hidden && state.activeIndex >= 0 && state.filtered[state.activeIndex]) {
                event.preventDefault();
                selectOption(state.filtered[state.activeIndex]);
            }

            return;
        }

        if (event.key === 'Escape') {
            event.preventDefault();
            closeList();
            syncLabelFromValue();
        }
    });

    input.addEventListener('blur', () => {
        window.setTimeout(() => {
            tryAutoSelect();
            closeList();
        }, 120);
    });

    root.addEventListener('click', (event) => {
        if (event.target === input) {
            return;
        }

        event.stopPropagation();
    });

    syncLabelFromValue();

    input.__erpSearchSelectBound = true;

    root.__erpSearchSelect = {
        boundInput: input,
        setValue(value) {
            hidden.value = value ? String(value) : '';
            syncLabelFromValue();
        },
        getValue() {
            return hidden.value;
        },
        setOptions(nextOptions) {
            state.options = Array.isArray(nextOptions) ? nextOptions : [];
            root.dataset.options = JSON.stringify(state.options);
            syncLabelFromValue();
        },
        setDisabled(disabled) {
            hidden.disabled = disabled;
            input.disabled = disabled;
        },
        refreshOptions() {
            state.options = resolveSearchSelectOptions(root);
            syncLabelFromValue();
        },
    };

    return root;
}

function enableErpSearchSelects(scope = document) {
    syncGlobalSearchSelectOptionSources(scope);
    enableErpLookupSelects(scope);

    scope.querySelectorAll('[data-erp-search-select]').forEach((root) => {
        bindErpSearchSelect(root);
    });
}

window.ErpUi = {
    ...(window.ErpUi || {}),
    syncLookupMirrorStates,
    enableErpLookupSelects,
    enableErpSearchSelects,
};

window.ErpSearchSelect = {
    bind(root) {
        return bindErpSearchSelect(root);
    },
    bindAll(scope = document) {
        enableErpSearchSelects(scope);
    },
    setValue(root, value) {
        const bound = root?.__erpSearchSelect ? root : bindErpSearchSelect(root);
        bound?.__erpSearchSelect?.setValue(value);
    },
    getValue(root) {
        return root?.__erpSearchSelect?.getValue() ?? root?.querySelector('input[type="hidden"]')?.value ?? '';
    },
    setOptions(root, options) {
        const bound = root?.__erpSearchSelect ? root : bindErpSearchSelect(root);

        if (bound?.__erpSearchSelect) {
            bound.__erpSearchSelect.setOptions(options);

            return;
        }

        if (root) {
            root.dataset.options = JSON.stringify(Array.isArray(options) ? options : []);
        }
    },
    setDisabled(root, disabled) {
        const bound = root?.__erpSearchSelect ? root : bindErpSearchSelect(root);
        bound?.__erpSearchSelect?.setDisabled(disabled);
    },
    findBySelectId(id) {
        if (!id) {
            return null;
        }

        const mirror = document.getElementById(id);

        if (mirror?.dataset?.erpLookupMirror === '1') {
            return mirror.__erpLookupWrapper || mirror.previousElementSibling;
        }

        return document.querySelector(`[data-erp-lookup-for="${id}"]`);
    },
    refreshFromSelect(selectEl) {
        if (!selectEl) {
            return;
        }

        const wrapper = selectEl.__erpLookupWrapper || window.ErpSearchSelect.findBySelectId(selectEl.id);

        if (!wrapper) {
            return;
        }

        const { options } = parseNativeSelectOptions(selectEl);
        window.ErpSearchSelect.setOptions(wrapper, options);
    },
};

window.ErpFormat = {
    number(value, decimals = 0) {
        return Number(value || 0).toLocaleString('fa-IR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    },
    money(value, decimals = 0) {
        return window.ErpFormat.number(value, decimals);
    },
    date(value) {
        if (!value) {
            return '';
        }

        try {
            return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
            }).format(new Date(value));
        } catch (error) {
            return '';
        }
    },
    dateTime(value) {
        if (!value) {
            return '';
        }

        try {
            return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            }).format(new Date(value));
        } catch (error) {
            return '';
        }
    },
};

function pad2(value) {
    return String(value).padStart(2, '0');
}

function handleErpDropdownClick(event) {
    const currentMenu = event.target.closest('.erp-nav-item');

    document.querySelectorAll('.erp-nav-item[open]').forEach((menu) => {
        if (menu !== currentMenu) {
            menu.removeAttribute('open');
        }
    });
}

function handleErpSpaClick(event) {
    const link = event.target.closest('a[href]');

    if (!link || !window.Livewire || typeof window.Livewire.navigate !== 'function') {
        return;
    }

    if (link.target === '_blank' || link.hasAttribute('download') || link.closest('[data-no-spa]')) {
        return;
    }

    const url = new URL(link.href, window.location.origin);
    const current = new URL(window.location.href);
    const path = url.pathname;
    const isDownloadRoute = path.includes('/excel/')
        || path.endsWith('/excel')
        || path.endsWith('/pdf')
        || path.includes('/worklog/export')
        || path.includes('/worklog/template');

    if (url.origin !== current.origin || isDownloadRoute) {
        return;
    }

    event.preventDefault();
    window.Livewire.navigate(url.pathname + url.search + url.hash);
}

function enableErpAutoFilters(root = document) {
    root.querySelectorAll('form[data-erp-auto-filter]').forEach((form) => {
        if (form.dataset.erpAutoFilterReady === '1') {
            return;
        }

        form.dataset.erpAutoFilterReady = '1';

        let debounceTimer = null;

        const submitFilters = () => {
            const action = form.getAttribute('action') || window.location.pathname;
            const url = new URL(action, window.location.origin);
            const formData = new FormData(form);

            url.search = '';

            formData.forEach((value, key) => {
                if (String(value).trim() !== '') {
                    url.searchParams.set(key, value);
                }
            });

            const target = url.pathname + url.search + url.hash;

            if (window.Livewire?.navigate) {
                window.Livewire.navigate(target);
                return;
            }

            window.location.href = target;
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitFilters();
        });

        form.querySelectorAll('select').forEach((select) => {
            select.addEventListener('change', submitFilters);
        });

        form.querySelectorAll('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"])').forEach((input) => {
            input.addEventListener('input', () => {
                window.clearTimeout(debounceTimer);
                debounceTimer = window.setTimeout(submitFilters, 400);
            });
        });
    });
}

document.addEventListener('DOMContentLoaded', enableErpAutoFilters);
document.addEventListener('DOMContentLoaded', enableErpSearchSelects);
document.addEventListener('DOMContentLoaded', hydrateErpTables);
document.addEventListener('DOMContentLoaded', enableErpSpaLinks);
document.addEventListener('DOMContentLoaded', enableErpDropdowns);
document.addEventListener('DOMContentLoaded', enableErpMoneyInputs);
document.addEventListener('DOMContentLoaded', enableJalaliDatepickers);
document.addEventListener('DOMContentLoaded', enableErpDeleteConfirms);
document.addEventListener('DOMContentLoaded', enableErpFlashMessages);
document.addEventListener('livewire:init', () => {
    enableErpFlashMessages();
    enableLivewireValidationToasts();
    enableJalaliDatepickers();
    enableErpMoneyInputs();

    if (window.Livewire?.hook) {
        Livewire.hook('element.init', ({ el }) => {
            enableErpSearchSelects(el);
        });
    }
});
document.addEventListener('livewire:navigated', () => {
    hydrateErpTables();
    enableErpSearchSelects();
    enableErpAutoFilters();
    enableErpSpaLinks();
    enableErpDropdowns();
    enableErpMoneyInputs();
    enableJalaliDatepickers();
    enableErpDeleteConfirms();
    enableErpFlashMessages();
});
document.addEventListener('livewire:update', hydrateErpTables);
document.addEventListener('livewire:morphed', () => {
    hydrateErpTables();
    enableErpSearchSelects();
    enableErpMoneyInputs();
    enableJalaliDatepickers();
    enableErpDeleteConfirms();
    enableErpFlashMessages();
});

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.erp-shell');

    if (!shell || !window.MutationObserver) {
        return;
    }

    new MutationObserver(scheduleErpHydration).observe(shell, {
        childList: true,
        subtree: true,
    });
});

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.erp-shell');

    if (!shell || !window.MutationObserver) {
        return;
    }

    new MutationObserver(() => {
        enableErpSearchSelects();
        enableErpMoneyInputs();
        enableJalaliDatepickers();
    }).observe(shell, {
        childList: true,
        subtree: true,
    });
});
