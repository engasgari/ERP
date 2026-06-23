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

function isErpDateField(input) {
    if (!input) {
        return false;
    }

    if (input.dataset.jalaliReady === '1' || input.dataset.jalaliDatepicker === '1' || input.hasAttribute('data-jalali-datepicker')) {
        return true;
    }

    const name = (input.getAttribute('name') || '').toLowerCase();
    const id = (input.id || '').toLowerCase();
    const haystack = `${name} ${id}`;

    return /(^|[\[\]_.-])(date|date_from|date_to|start_date|end_date|document_date|transaction_date|invoice_date|work_date|effective_date|planned_start_date|planned_end_date|actual_start_date|actual_end_date|leave_date|mission_date|payment_date|hire_date|termination_date|issued_at|expires_at|start_date_fa|end_date_fa|from_date|to_date|period_date|due_date|delivery_date)(?=$|[\[\]_.-])/i.test(haystack);
}

function jalaliDatePlaceholderFor(input) {
    const name = (input?.getAttribute('name') || '').toLowerCase();
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
    if (!input || input.disabled || input.dataset.erpMoneyReady === '1') {
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

    const name = (input.getAttribute('name') || '').toLowerCase();
    const id = (input.id || '').toLowerCase();
    const haystack = `${name} ${id}`;

    if (isErpDateField(input)) {
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
    const formattedInteger = normalizedInteger.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return toPersianNumber(`${negative ? '-' : ''}${formattedInteger}${fractionPart ? `.${fractionPart}` : ''}`);
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

function enableErpMoneyInputs() {
    document.querySelectorAll('input').forEach((input) => {
        if (!isErpMoneyField(input)) {
            return;
        }

        input.dataset.erpMoneyReady = '1';
        input.autocomplete = 'off';
        input.setAttribute('inputmode', input.step && input.step !== '1' ? 'decimal' : 'numeric');
        input.dir = 'ltr';
        input.classList.add('text-left', 'tabular-nums');

        if (input.type === 'number') {
            input.type = 'text';
        }

        const formatCurrentValue = () => {
            input.value = input.value === '' ? '' : normalizeErpMoneyValue(input.value);
        };

        const sanitizeCurrentValue = () => {
            input.value = erpMoneyRawValue(input.value);
        };

        input.addEventListener('focus', () => {
            input.value = erpMoneyRawValue(input.value);
            window.requestAnimationFrame(() => {
                input.select?.();
                input.setSelectionRange?.(0, String(input.value ?? '').length);
            });
        });

        input.addEventListener('mousedown', (event) => {
            event.preventDefault();
            input.focus({ preventScroll: true });
            window.requestAnimationFrame(() => {
                input.select?.();
                input.setSelectionRange?.(0, String(input.value ?? '').length);
            });
        });

        input.addEventListener('input', sanitizeCurrentValue);
        input.addEventListener('blur', formatCurrentValue);

        const form = input.closest('form');
        if (form && form.dataset.erpMoneySubmitReady !== '1') {
            form.dataset.erpMoneySubmitReady = '1';
            form.addEventListener('submit', () => {
                form.querySelectorAll('input[data-erp-money-ready="1"]').forEach((moneyInput) => {
                    moneyInput.value = erpMoneyRawValue(moneyInput.value);
                });
            }, true);
        }

        formatCurrentValue();
    });
}

function enableJalaliDatepickers() {
    document.querySelectorAll('input[data-jalali-datepicker], input[type="text"][name*="date"], input[type="text"][id*="date"]').forEach((input) => {
        if (input.type === 'hidden' || input.dataset.jalaliReady === '1') {
            return;
        }

        normalizeErpDateField(input);

        input.addEventListener('input', () => {
            input.value = formatJalaliDateInput(input.value);
        });

        input.addEventListener('focus', () => showJalaliPicker(input));
        input.addEventListener('click', () => showJalaliPicker(input));
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

function enableErpFlashMessages() {
    document.querySelectorAll('[data-erp-flash]').forEach((flash) => {
        if (flash.dataset.erpFlashReady === '1') {
            return;
        }

        flash.dataset.erpFlashReady = '1';
        const close = () => flash.remove();
        flash.querySelector('[data-erp-flash-close]')?.addEventListener('click', close);
        window.setTimeout(close, 4200);
    });
}

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

    if (!picker || event.target.closest('.erp-jalali-picker') || event.target.closest('input[data-jalali-datepicker], input[type="text"][name*="date"], input[type="text"][id*="date"]')) {
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
            activeJalaliInput.value = `${activeJalaliMonth.year}/${pad2(activeJalaliMonth.month)}/${pad2(day)}`;
            activeJalaliInput.dispatchEvent(new Event('input', { bubbles: true }));
            activeJalaliInput.dispatchEvent(new Event('change', { bubbles: true }));
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
        activeJalaliInput.value = `${parts.year}/${pad2(parts.month)}/${pad2(parts.day)}`;
        activeJalaliInput.dispatchEvent(new Event('input', { bubbles: true }));
        activeJalaliInput.dispatchEvent(new Event('change', { bubbles: true }));
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

function formatJalaliDateInput(value) {
    const digits = normalizeDigits(value).replace(/[^\d]/g, '').slice(0, 8);

    if (digits.length <= 4) {
        return digits;
    }

    if (digits.length <= 6) {
        return `${digits.slice(0, 4)}/${digits.slice(4)}`;
    }

    return `${digits.slice(0, 4)}/${digits.slice(4, 6)}/${digits.slice(6)}`;
}

function parseJalaliDate(value) {
    const parts = normalizeDigits(value).match(/^(\d{4})[/-](\d{1,2})[/-](\d{1,2})$/);

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

    if (url.origin !== current.origin || url.pathname.includes('/excel/') || url.pathname.includes('/worklog/export') || url.pathname.includes('/worklog/template')) {
        return;
    }

    event.preventDefault();
    window.Livewire.navigate(url.pathname + url.search + url.hash);
}

document.addEventListener('DOMContentLoaded', hydrateErpTables);
document.addEventListener('DOMContentLoaded', enableErpSpaLinks);
document.addEventListener('DOMContentLoaded', enableErpDropdowns);
document.addEventListener('DOMContentLoaded', enableErpMoneyInputs);
document.addEventListener('DOMContentLoaded', enableJalaliDatepickers);
document.addEventListener('DOMContentLoaded', enableErpDeleteConfirms);
document.addEventListener('DOMContentLoaded', enableErpFlashMessages);
document.addEventListener('livewire:navigated', () => {
    hydrateErpTables();
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
        enableErpMoneyInputs();
    }).observe(shell, {
        childList: true,
        subtree: true,
    });
});
