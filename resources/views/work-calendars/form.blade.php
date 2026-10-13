<x-app-layout>
    <x-slot name="header"><h2 class="font-semibold text-xl">{{ $calendar->exists ? 'ویرایش تقویم کاری' : 'ثبت تقویم کاری' }}</h2></x-slot>

    @php
        $selectedWorking = collect(old('working_days', $calendar->working_days ?: []))->map(fn ($d) => (int) $d)->all();
        $selectedWeekend = collect(old('weekend_days', $calendar->weekend_days ?: []))->map(fn ($d) => (int) $d)->all();
        $holidayPickerConfig = [
            'year' => (int) $holidayYear,
            'selectedHolidays' => $selectedHolidays,
            'monthNames' => array_values($monthNames),
        ];
    @endphp

    <form method="POST"
          action="{{ $calendar->exists ? route('work-calendars.update', $calendar) : route('work-calendars.store') }}"
          class="bg-white rounded-lg shadow-md p-6 space-y-4"
          id="work-calendar-form">
        @csrf
        @if($calendar->exists) @method('PUT') @endif
        <input type="hidden" name="holidays_managed" value="1">
        <div id="holiday-inputs"></div>

        <script type="application/json" id="work-calendar-holiday-config">{!! json_encode($holidayPickerConfig, JSON_UNESCAPED_UNICODE) !!}</script>

        <div class="grid gap-4 md:grid-cols-3">
            <label>کد تقویم<input name="code" value="{{ old('code', $calendar->code) }}" required class="w-full"></label>
            <label>نام تقویم<input name="name" value="{{ old('name', $calendar->name) }}" required class="w-full"></label>
            <label>سال شمسی
                <input type="text"
                       name="jalali_year"
                       id="jalali_year"
                       inputmode="numeric"
                       maxlength="4"
                       data-erp-money="0"
                       data-erp-number="0"
                       value="{{ old('jalali_year', $calendar->jalali_year ?: $holidayYear) }}"
                       class="w-full"
                       dir="ltr"
                       placeholder="1405"
                       autocomplete="off">
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <fieldset class="rounded-md border border-slate-200 p-3 space-y-2">
                <legend class="px-1 text-sm font-semibold">روزهای کاری</legend>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" id="working-days-box">
                    @foreach($dayLabels as $day => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="working_days[]" value="{{ $day }}" @checked(in_array((int) $day, $selectedWorking, true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <fieldset class="rounded-md border border-slate-200 p-3 space-y-2">
                <legend class="px-1 text-sm font-semibold">تعطیلات هفتگی</legend>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2" id="weekend-days-box">
                    @foreach($dayLabels as $day => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="weekend_days[]" value="{{ $day }}" @checked(in_array((int) $day, $selectedWeekend, true))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-slate-500 mb-0">پیش‌فرض اداری: پنجشنبه و جمعه</p>
            </fieldset>
        </div>

        <div class="rounded-md border border-slate-200 p-4 space-y-3">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div>
                    <div class="font-semibold">تعطیلات رسمی شمسی</div>
                    <div class="text-sm text-slate-500">روی تقویم ماه تیک بزنید. تاریخ میلادی نمایش داده نمی‌شود.</div>
                </div>
                <button type="button" class="erp-action-btn erp-action-edit" id="open-holiday-picker">
                    انتخاب تعطیلات رسمی
                </button>
            </div>

            <div id="holiday-summary" class="text-sm text-slate-700"></div>
            @error('holidays')
                <div class="text-sm text-red-600">{{ $message }}</div>
            @enderror
        </div>

        <div class="flex flex-wrap gap-4">
            <label class="flex items-center gap-2"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $calendar->is_default))> پیش‌فرض</label>
            <label class="flex items-center gap-2"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $calendar->is_active ?? true))> فعال</label>
        </div>

        <label class="block">توضیحات<textarea name="description" rows="2" class="w-full">{{ old('description', $calendar->description) }}</textarea></label>
        <div class="flex justify-end gap-2">
            <a href="{{ route('work-calendars.index') }}" class="erp-action-btn erp-action-detail">بازگشت</a>
            <button class="erp-action-btn erp-action-edit">ذخیره</button>
        </div>
    </form>

    <div id="holiday-picker-modal" class="erp-modal" hidden>
        <div class="erp-ui-modal-backdrop" data-holiday-close></div>
        <div class="erp-ui-modal-panel work-calendar-holiday-panel">
            <div class="erp-modal-header">
                <h3>تعطیلات رسمی سال <span id="holiday-modal-year"></span></h3>
                <button type="button" class="erp-modal-close" data-holiday-close aria-label="بستن">×</button>
            </div>
            <div class="erp-modal-body work-calendar-holiday-body">
                <div class="work-calendar-holiday-nav">
                    <button type="button" class="work-calendar-nav-btn" id="holiday-prev-month" aria-label="ماه قبل">‹</button>
                    <strong id="holiday-month-title"></strong>
                    <button type="button" class="work-calendar-nav-btn" id="holiday-next-month" aria-label="ماه بعد">›</button>
                </div>
                <div class="work-calendar-holiday-weekdays">
                    <span>ش</span><span>ی</span><span>د</span><span>س</span><span>چ</span><span class="is-weekend-label">پ</span><span class="is-weekend-label">ج</span>
                </div>
                <div id="holiday-month-grid" class="work-calendar-holiday-grid"></div>
                <div class="work-calendar-holiday-legend">
                    <span><i class="lg-work"></i>کاری</span>
                    <span><i class="lg-weekend"></i>تعطیل هفتگی</span>
                    <span><i class="lg-selected"></i>تعطیل رسمی</span>
                </div>
                <div id="holiday-month-stats" class="work-calendar-holiday-stats"></div>
            </div>
            <div class="erp-modal-actions work-calendar-holiday-actions">
                <button type="button" class="erp-action-btn" data-holiday-close>بستن</button>
                <button type="button" class="erp-action-btn erp-action-edit" id="holiday-apply">تایید</button>
            </div>
        </div>
    </div>

    <style>
        .work-calendar-holiday-panel {
            max-width: 320px !important;
            width: min(94vw, 320px) !important;
        }
        .work-calendar-holiday-panel .erp-modal-header {
            padding: .55rem .75rem !important;
        }
        .work-calendar-holiday-panel .erp-modal-header h3 {
            font-size: .9rem !important;
            margin: 0;
        }
        .work-calendar-holiday-body {
            padding: .65rem .75rem !important;
            display: grid;
            gap: .45rem;
        }
        .work-calendar-holiday-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .35rem;
        }
        .work-calendar-holiday-nav strong {
            font-size: .85rem;
        }
        .work-calendar-nav-btn {
            width: 1.55rem !important;
            height: 1.55rem !important;
            min-height: 1.55rem !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: .35rem !important;
            background: #fff !important;
            line-height: 1 !important;
            font-size: .9rem !important;
            font-weight: 600 !important;
            color: #334155 !important;
            padding: 0 !important;
            box-shadow: none !important;
            gap: 0 !important;
            white-space: nowrap !important;
        }
        .work-calendar-holiday-weekdays,
        .work-calendar-holiday-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: .15rem;
            text-align: center;
        }
        .work-calendar-holiday-weekdays span {
            font-size: .62rem;
            color: #64748b;
            font-weight: 600;
            padding: 0;
            line-height: 1.2;
        }
        .work-calendar-holiday-weekdays span.is-weekend-label {
            color: #dc2626;
        }
        .erp-shell main .work-calendar-holiday-day,
        .erp-shell .work-calendar-holiday-day,
        .work-calendar-holiday-day {
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: .3rem !important;
            background: #fff !important;
            color: #0f172a !important;
            width: 100% !important;
            min-width: 0 !important;
            min-height: 1.7rem !important;
            height: 1.7rem !important;
            max-height: 1.7rem !important;
            font-size: .72rem !important;
            font-weight: 600 !important;
            padding: 0 !important;
            margin: 0 !important;
            cursor: pointer;
            line-height: 1.7rem !important;
            gap: 0 !important;
            box-shadow: none !important;
            white-space: nowrap !important;
            overflow: hidden !important;
            overflow-wrap: normal !important;
            word-break: keep-all !important;
            word-wrap: normal !important;
            letter-spacing: 0 !important;
            transform: none !important;
            transition: background .12s ease, border-color .12s ease, color .12s ease;
        }
        .erp-shell .work-calendar-holiday-day:hover:not(.is-empty),
        .work-calendar-holiday-day:hover:not(.is-empty) {
            border-color: #94a3b8 !important;
            background: #f8fafc !important;
            transform: none !important;
            box-shadow: none !important;
        }
        /* پنجشنبه/جمعه و تعطیل رسمی — عدد قرمز یکسان */
        .erp-shell main .work-calendar-holiday-day.is-weekend,
        .erp-shell .work-calendar-holiday-day.is-weekend,
        .work-calendar-holiday-day.is-weekend,
        .erp-shell main .work-calendar-holiday-day.is-selected,
        .erp-shell .work-calendar-holiday-day.is-selected,
        .work-calendar-holiday-day.is-selected,
        .erp-shell main .work-calendar-holiday-day.is-weekend.is-selected,
        .erp-shell .work-calendar-holiday-day.is-weekend.is-selected,
        .work-calendar-holiday-day.is-weekend.is-selected {
            background: #fff5f5 !important;
            border-color: #fca5a5 !important;
            color: #dc2626 !important;
            font-weight: 700 !important;
            box-shadow: none !important;
        }
        .erp-shell main .work-calendar-holiday-day.is-empty,
        .erp-shell .work-calendar-holiday-day.is-empty,
        .work-calendar-holiday-day.is-empty {
            border: 0 !important;
            background: transparent !important;
            pointer-events: none;
            cursor: default;
            min-height: 1.7rem !important;
            height: 1.7rem !important;
            box-shadow: none !important;
        }
        .work-calendar-holiday-legend {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .55rem;
            font-size: .65rem;
            color: #64748b;
        }
        .work-calendar-holiday-legend i {
            display: inline-block;
            width: .65rem;
            height: .65rem;
            border-radius: .15rem;
            margin-left: .2rem;
            vertical-align: -1px;
            border: 1px solid transparent;
        }
        .work-calendar-holiday-legend .lg-work { background: #fff; border-color: #e2e8f0; }
        .work-calendar-holiday-legend .lg-weekend,
        .work-calendar-holiday-legend .lg-selected {
            background: #fff;
            border-color: #fecaca;
            box-shadow: inset 0 0 0 1px #dc2626;
        }
        .work-calendar-holiday-stats {
            font-size: .72rem;
            color: #475569;
        }
        .work-calendar-holiday-actions {
            padding: .55rem .75rem !important;
            gap: .4rem !important;
        }
        .holiday-chip-list { display: flex; flex-wrap: wrap; gap: .3rem; }
        .holiday-chip {
            display: inline-flex;
            align-items: center;
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: .35rem;
            padding: .1rem .4rem;
            font-size: .72rem;
        }
    </style>

    <script>
        (function initWorkCalendarHolidayPicker() {
            const form = document.getElementById('work-calendar-form');
            if (!form || form.dataset.holidayPickerReady === '1') {
                return;
            }
            form.dataset.holidayPickerReady = '1';

            const configEl = document.getElementById('work-calendar-holiday-config');
            let config = { year: 1405, selectedHolidays: [], monthNames: [] };
            try {
                config = JSON.parse(configEl?.textContent || '{}');
            } catch (e) {
                console.error('work-calendar holiday config', e);
            }

            const modal = document.getElementById('holiday-picker-modal');
            const gridEl = document.getElementById('holiday-month-grid');
            const titleEl = document.getElementById('holiday-month-title');
            const yearEl = document.getElementById('holiday-modal-year');
            const statsEl = document.getElementById('holiday-month-stats');
            const summaryEl = document.getElementById('holiday-summary');
            const inputsEl = document.getElementById('holiday-inputs');
            const yearInput = document.getElementById('jalali_year');
            const openBtn = document.getElementById('open-holiday-picker');

            const monthNames = Array.isArray(config.monthNames) ? config.monthNames : [];
            let selected = new Set(Array.isArray(config.selectedHolidays) ? config.selectedHolidays : []);
            let draft = new Set(selected);
            let currentMonth = 6;
            let activeYear = Number(config.year) || 1405;

            const digitMap = {
                '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9',
                '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
            };
            const toLatinDigits = (value) => String(value ?? '').replace(/[۰-۹٠-٩]/g, (d) => digitMap[d] || d);
            const toPersian = (value) => String(value).replace(/\d/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[d]);
            const pad2 = (n) => String(n).padStart(2, '0');

            const parseYear = (value, fallback) => {
                const digits = toLatinDigits(value).replace(/\D/g, '');
                const year = Number(digits);
                return year >= 1300 && year <= 1500 ? year : (fallback || 1405);
            };

            const checkedDays = (boxId) => Array.from(document.querySelectorAll(`#${boxId} input:checked`))
                .map((el) => Number(el.value));

            const daysInMonth = (year, month) => {
                if (typeof jalaliMonthLength === 'function') {
                    return jalaliMonthLength(year, month);
                }
                if (month <= 6) return 31;
                if (month <= 11) return 30;
                return 29;
            };

            const leadingBlanks = (year, month) => {
                if (typeof jalaliWeekday === 'function') {
                    return jalaliWeekday(year, month, 1);
                }
                return 0;
            };

            const dayOfWeek = (year, month, day) => {
                if (typeof jalaliToGregorian === 'function') {
                    try {
                        return jalaliToGregorian(year, month, day).getDay();
                    } catch (e) {
                        return 0;
                    }
                }
                return 0;
            };

            const buildMonth = (year, month) => {
                const weekend = checkedDays('weekend-days-box');
                const total = daysInMonth(year, month);
                const days = [];
                for (let day = 1; day <= total; day += 1) {
                    const dow = dayOfWeek(year, month, day);
                    days.push({
                        day,
                        jalali: `${year}/${pad2(month)}/${pad2(day)}`,
                        dow,
                        is_weekend: weekend.includes(dow),
                    });
                }
                return {
                    month,
                    name: monthNames[month - 1] || ('ماه ' + month),
                    leading: leadingBlanks(year, month),
                    days,
                };
            };

            const syncHiddenInputs = () => {
                inputsEl.innerHTML = '';
                Array.from(selected).sort().forEach((date) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'holidays[]';
                    input.value = date;
                    inputsEl.appendChild(input);
                });
            };

            const renderSummary = () => {
                if (selected.size === 0) {
                    summaryEl.innerHTML = '<span class="text-slate-500">هنوز تعطیل رسمی انتخاب نشده است.</span>';
                    syncHiddenInputs();
                    return;
                }
                const chips = Array.from(selected).sort().map((date) =>
                    `<span class="holiday-chip">${toPersian(date)}</span>`
                ).join('');
                summaryEl.innerHTML = `<div class="mb-1">${toPersian(selected.size)} روز انتخاب شده:</div><div class="holiday-chip-list">${chips}</div>`;
                syncHiddenInputs();
            };

            const renderMonth = () => {
                const month = buildMonth(activeYear, currentMonth);
                yearEl.textContent = toPersian(activeYear);
                titleEl.textContent = `${month.name} ${toPersian(activeYear)}`;
                gridEl.innerHTML = '';

                for (let i = 0; i < month.leading; i += 1) {
                    const empty = document.createElement('button');
                    empty.type = 'button';
                    empty.className = 'work-calendar-holiday-day is-empty';
                    empty.tabIndex = -1;
                    gridEl.appendChild(empty);
                }

                month.days.forEach((day) => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'work-calendar-holiday-day';
                    btn.setAttribute('aria-label', 'روز ' + day.day);
                    btn.textContent = toPersian(day.day);
                    if (day.is_weekend) btn.classList.add('is-weekend');
                    if (draft.has(day.jalali)) btn.classList.add('is-selected');
                    btn.addEventListener('click', (event) => {
                        event.preventDefault();
                        if (draft.has(day.jalali)) draft.delete(day.jalali);
                        else draft.add(day.jalali);
                        renderMonth();
                    });
                    gridEl.appendChild(btn);
                });

                const working = checkedDays('working-days-box');
                const weekend = checkedDays('weekend-days-box');
                let workCount = 0;
                let holidayCount = 0;
                month.days.forEach((day) => {
                    if (draft.has(day.jalali)) {
                        holidayCount += 1;
                        return;
                    }
                    if (weekend.includes(day.dow)) return;
                    if (working.includes(day.dow)) workCount += 1;
                });
                statsEl.textContent = `تعطیل رسمی این ماه: ${toPersian(holidayCount)} | روز کاری موظفی: ${toPersian(workCount)}`;
            };

            const openModal = (event) => {
                if (event) event.preventDefault();
                draft = new Set(selected);
                activeYear = parseYear(yearInput?.value, Number(config.year) || 1405);
                if (yearInput) {
                    yearInput.value = String(activeYear);
                }
                currentMonth = Math.min(12, Math.max(1, currentMonth || 6));
                modal.removeAttribute('hidden');
                document.body.classList.add('erp-modal-open');
                renderMonth();
            };

            const closeModal = (event) => {
                if (event) event.preventDefault();
                modal.setAttribute('hidden', 'hidden');
                document.body.classList.remove('erp-modal-open');
            };

            openBtn?.addEventListener('click', openModal);
            document.getElementById('holiday-apply')?.addEventListener('click', (event) => {
                event.preventDefault();
                selected = new Set(draft);
                renderSummary();
                closeModal();
            });
            document.getElementById('holiday-prev-month')?.addEventListener('click', (event) => {
                event.preventDefault();
                currentMonth = currentMonth <= 1 ? 12 : currentMonth - 1;
                renderMonth();
            });
            document.getElementById('holiday-next-month')?.addEventListener('click', (event) => {
                event.preventDefault();
                currentMonth = currentMonth >= 12 ? 1 : currentMonth + 1;
                renderMonth();
            });
            modal?.querySelectorAll('[data-holiday-close]').forEach((el) => {
                el.addEventListener('click', closeModal);
            });

            yearInput?.addEventListener('blur', () => {
                yearInput.value = String(parseYear(yearInput.value, Number(config.year) || 1405));
            });

            ['working-days-box', 'weekend-days-box'].forEach((id) => {
                document.getElementById(id)?.addEventListener('change', () => {
                    if (!modal.hasAttribute('hidden')) renderMonth();
                });
            });

            renderSummary();
        })();
    </script>
</x-app-layout>
