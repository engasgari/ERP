<div class="py-4 py-md-5">
    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-3 p-md-4 space-y-4">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                <div>
                    <h2 class="text-xl font-bold mb-1">احکام کارگزینی</h2>
                    <p class="text-sm text-slate-500 mb-0">ساختار حرفه‌ای ERP: اطلاعات حکم، اقلام از کاتالوگ حقوق، کسورات، خلاصه و تاریخچه</p>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" wire:click="cancel" class="erp-action-btn">حکم جدید</button>
                    <button type="button" wire:click="save" class="erp-action-btn erp-action-edit">ذخیره حکم</button>
                </div>
            </div>

            @include('livewire.partials.flash')

            @if ($errors->any())
                <div class="rounded-lg bg-red-50 border border-red-100 p-3 text-sm text-red-700">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-2">
                @foreach([
                    'info' => 'اطلاعات حکم',
                    'items' => 'اقلام حکم',
                    'deductions' => 'کسورات',
                    'summary' => 'خلاصه محاسبات',
                    'history' => 'تاریخچه احکام',
                ] as $tab => $label)
                    <button
                        type="button"
                        wire:click="setTab('{{ $tab }}')"
                        class="px-3 py-2 rounded-lg text-sm font-bold transition {{ $activeTab === $tab ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @if($activeTab === 'info')
                <div class="row g-2 g-md-3">
                    <label class="erp-filter-field col-12 col-md-3">شماره حکم<input wire:model="form.number" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-3">تاریخ صدور<input wire:model="form.issue_date" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-3">تاریخ اجرا<input wire:model="form.effective_date" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-3">تاریخ پایان اثر<input wire:model="form.end_date" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-4">پرسنل
                        <select wire:model.live="form.employee_id">
                            <option value="">انتخاب کنید</option>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}">{{ $employee->full_name }} - {{ $employee->personnel_code ?: $employee->id }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field col-12 col-md-4">نوع حکم
                        <select wire:model="form.order_type">
                            @foreach($orderTypes as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="erp-filter-field col-12 col-md-4">علت حکم<input wire:model="form.decree_reason"></label>
                    <label class="erp-filter-field col-12 col-md-3">پست
                        <select wire:model="form.position_id"><option value="">-</option>@foreach($positions as $position)<option value="{{ $position->id }}">{{ $position->title }}</option>@endforeach</select>
                    </label>
                    <label class="erp-filter-field col-12 col-md-3">شغل
                        <select wire:model="form.job_id"><option value="">-</option>@foreach($jobs as $job)<option value="{{ $job->id }}">{{ $job->title }}</option>@endforeach</select>
                    </label>
                    <label class="erp-filter-field col-12 col-md-2">گروه<input wire:model.blur="form.job_group" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-2">رتبه<input wire:model.blur="form.job_rank" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-2">پایه<input wire:model.blur="form.job_base" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-3">محل خدمت
                        <select wire:model="form.organization_unit_id"><option value="">-</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->title }}</option>@endforeach</select>
                    </label>
                    <label class="erp-filter-field col-12 col-md-3">پروژه
                        <select wire:model="form.default_project_id"><option value="">-</option>@foreach($projects as $project)<option value="{{ $project->id }}">{{ $project->name }}</option>@endforeach</select>
                    </label>
                    <label class="erp-filter-field col-12 col-md-2">کد کارگاه<input wire:model="form.workshop_code" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-2">مرکز هزینه<input wire:model="form.cost_center_code"></label>
                    <label class="erp-filter-field col-12 col-md-2">نوع استخدام
                        <select wire:model="form.employment_type">@foreach($employmentTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </label>
                    <label class="erp-filter-field col-12 col-md-2">وضعیت بیمه
                        <select wire:model="form.insurance_status">@foreach($insuranceStatuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </label>
                    <label class="erp-filter-field col-12 col-md-2">تاهل
                        <select wire:model="form.marital_status"><option value="">-</option>@foreach($maritalStatuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select>
                    </label>
                    <label class="erp-filter-field col-12 col-md-2">تعداد اولاد<input wire:model.blur="form.children_count" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-2">ساعت ماهانه<input wire:model.blur="form.monthly_work_hours" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-2">ساعت روزانه<input wire:model.blur="form.daily_work_hours" dir="ltr"></label>
                    <label class="erp-filter-field col-12 col-md-2">نرخ ساعتی<input wire:model.blur="form.hourly_rate" dir="ltr"></label>
                    <label class="erp-filter-field col-12">یادداشت<input wire:model="form.notes"></label>
                </div>
            @endif

            @if($activeTab === 'items')
                <div class="space-y-3">
                    <div class="row g-2 align-items-end">
                        <label class="erp-filter-field col-12 col-md-8">افزودن قلم از کاتالوگ حقوق (salary_items)
                            <select wire:model="selectedSalaryItemId">
                                <option value="">انتخاب قلم مزایا</option>
                                @foreach($earningItems as $item)
                                    <option value="{{ $item->id }}">{{ $item->title }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="col-12 col-md-4 d-grid">
                            <button type="button" wire:click="addEarningLine" class="erp-action-btn erp-action-edit">افزودن به حکم</button>
                        </div>
                    </div>

                    <div class="table-responsive rounded-xl border border-slate-200 overflow-hidden">
                        <table class="erp-ui-data-table w-full mb-0">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th>عنوان</th>
                                    <th>مبلغ</th>
                                    <th>مشمول بیمه</th>
                                    <th>مشمول مالیات</th>
                                    <th>نوع آیتم</th>
                                    <th>قابل ویرایش</th>
                                    <th>حذف</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lines as $index => $line)
                                    @continue(($line['type'] ?? '') !== 'earning')
                                    <tr wire:key="earn-line-{{ $index }}-{{ $line['code'] ?? $index }}">
                                        <td class="font-bold">{{ $line['title'] }}</td>
                                        <td style="min-width:140px">
                                            @if($line['is_editable'] ?? true)
                                                <input type="text" inputmode="numeric" class="w-full" dir="ltr" wire:model.blur="lines.{{ $index }}.amount">
                                            @else
                                                <span dir="ltr">{{ $line['amount'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ ($line['is_insurable'] ?? false) ? 'بله' : 'خیر' }}</td>
                                        <td>{{ ($line['is_taxable'] ?? false) ? 'بله' : 'خیر' }}</td>
                                        <td>مزایا</td>
                                        <td>{{ ($line['is_editable'] ?? true) ? 'بله' : 'خیر' }}</td>
                                        <td>
                                            @if($line['is_removable'] ?? true)
                                                <button type="button" wire:click="removeLine({{ $index }})" class="erp-action-btn erp-action-delete">حذف</button>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="text-center text-slate-500 py-6">قلمی ثبت نشده است.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($activeTab === 'deductions')
                <div class="space-y-3">
                    <div class="row g-2 align-items-end">
                        <label class="erp-filter-field col-12 col-md-8">افزودن کسورات ثابت از کاتالوگ
                            <select wire:model="selectedDeductionItemId">
                                <option value="">انتخاب کسورات</option>
                                @foreach($deductionItems as $item)
                                    <option value="{{ $item->id }}">{{ $item->title }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="col-12 col-md-4 d-grid">
                            <button type="button" wire:click="addDeductionLine" class="erp-action-btn erp-action-edit">افزودن کسورات</button>
                        </div>
                    </div>

                    <div class="table-responsive rounded-xl border border-slate-200 overflow-hidden">
                        <table class="erp-ui-data-table w-full mb-0">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th>عنوان</th>
                                    <th>مبلغ</th>
                                    <th>مشمول بیمه</th>
                                    <th>مشمول مالیات</th>
                                    <th>نوع آیتم</th>
                                    <th>قابل ویرایش</th>
                                    <th>حذف</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $hasDeduction = false; @endphp
                                @foreach($lines as $index => $line)
                                    @continue(($line['type'] ?? '') !== 'deduction')
                                    @php $hasDeduction = true; @endphp
                                    <tr wire:key="ded-line-{{ $index }}-{{ $line['code'] ?? $index }}">
                                        <td class="font-bold">{{ $line['title'] }}</td>
                                        <td style="min-width:140px">
                                            @if($line['is_editable'] ?? true)
                                                <input type="text" inputmode="numeric" class="w-full" dir="ltr" wire:model.blur="lines.{{ $index }}.amount">
                                            @else
                                                <span dir="ltr">{{ $line['amount'] }}</span>
                                            @endif
                                        </td>
                                        <td>{{ ($line['is_insurable'] ?? false) ? 'بله' : 'خیر' }}</td>
                                        <td>{{ ($line['is_taxable'] ?? false) ? 'بله' : 'خیر' }}</td>
                                        <td>کسورات</td>
                                        <td>{{ ($line['is_editable'] ?? true) ? 'بله' : 'خیر' }}</td>
                                        <td>
                                            @if($line['is_removable'] ?? true)
                                                <button type="button" wire:click="removeLine({{ $index }})" class="erp-action-btn erp-action-delete">حذف</button>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                @unless($hasDeduction)
                                    <tr><td colspan="7" class="text-center text-slate-500 py-6">کسوراتی ثبت نشده است.</td></tr>
                                @endunless
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($activeTab === 'summary')
                <div class="row g-3">
                    @foreach([
                        'total_benefits' => 'جمع مزایا',
                        'insurable_benefits' => 'جمع مزایای مشمول بیمه',
                        'taxable_benefits' => 'جمع مزایای مشمول مالیات',
                        'total_deductions' => 'جمع کسورات',
                        'gross_salary' => 'حقوق ناخالص',
                        'insurable_wage' => 'حقوق مشمول بیمه',
                        'taxable_wage' => 'حقوق مشمول مالیات',
                        'net_salary' => 'حقوق خالص',
                    ] as $key => $label)
                        <div class="col-12 col-sm-6 col-lg-3">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 h-100">
                                <div class="text-xs text-slate-500 font-bold">{{ $label }}</div>
                                <div class="mt-2 text-lg font-black text-slate-900" dir="ltr">{{ formatMoney((float) ($summary[$key] ?? 0)) }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($activeTab === 'history')
                <div class="table-responsive rounded-xl border border-slate-200 overflow-hidden">
                    <table class="erp-ui-data-table w-full mb-0">
                        <thead class="bg-slate-50">
                            <tr>
                                <th>شماره</th>
                                <th>نوع</th>
                                <th>تاریخ اجرا</th>
                                <th>حقوق پایه</th>
                                <th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($history as $row)
                                <tr>
                                    <td>{{ $row->number }}</td>
                                    <td>{{ $row->orderTypeLabel() }}</td>
                                    <td>{{ formatJalaliDateSafe($row->effective_date) }}</td>
                                    <td>{{ formatMoney((float) $row->base_salary) }}</td>
                                    <td>{{ $row->status === 'approved' ? 'تایید شده' : 'پیش‌نویس' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-slate-500 py-6">برای مشاهده تاریخچه، پرسنل را انتخاب کنید.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="rounded-xl border border-slate-200 bg-gradient-to-l from-slate-50 to-white p-3">
                <div class="row g-2">
                    <div class="col-6 col-md-3"><div class="text-xs text-slate-500">ناخالص</div><div class="font-bold" dir="ltr">{{ formatMoney((float) $summary['gross_salary']) }}</div></div>
                    <div class="col-6 col-md-3"><div class="text-xs text-slate-500">مشمول بیمه</div><div class="font-bold text-emerald-700" dir="ltr">{{ formatMoney((float) $summary['insurable_wage']) }}</div></div>
                    <div class="col-6 col-md-3"><div class="text-xs text-slate-500">کسورات</div><div class="font-bold text-amber-700" dir="ltr">{{ formatMoney((float) $summary['total_deductions']) }}</div></div>
                    <div class="col-6 col-md-3"><div class="text-xs text-slate-500">خالص</div><div class="font-bold text-slate-900" dir="ltr">{{ formatMoney((float) $summary['net_salary']) }}</div></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-3 p-md-4 space-y-3">
            <div class="erp-ui-filter-bar mb-0">
                <div class="row g-2">
                    <label class="erp-filter-field col-12 col-md-6">جستجو<input wire:model.live.debounce.400ms="search"></label>
                    <label class="erp-filter-field col-12 col-md-3">وضعیت
                        <select wire:model.live="status">
                            <option value="">همه</option>
                            <option value="draft">پیش‌نویس</option>
                            <option value="approved">تایید شده</option>
                        </select>
                    </label>
                    <div class="col-12 col-md-3 d-grid"><button type="button" wire:click="clearFilters" class="erp-action-btn">حذف فیلتر</button></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="erp-ui-data-table w-full">
                    <thead>
                        <tr>
                            <th>شماره</th>
                            <th>پرسنل</th>
                            <th>نوع</th>
                            <th>تاریخ اجرا</th>
                            <th>حقوق پایه</th>
                            <th>مشمول بیمه</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr wire:key="order-{{ $order->id }}">
                                <td>{{ $order->number }}</td>
                                <td>{{ $order->employee->full_name }}</td>
                                <td>{{ $order->orderTypeLabel() }}</td>
                                <td>{{ formatJalaliDateSafe($order->effective_date) }}</td>
                                <td>{{ formatMoney((float) $order->base_salary) }}</td>
                                <td>{{ formatMoney($order->totalInsurableWage()) }}</td>
                                <td>{{ $order->status === 'approved' ? 'تایید شده' : 'پیش‌نویس' }}</td>
                                <td>
                                    <x-erp.ui.row-actions>
                                        @if($order->status === 'approved')
                                            <x-erp.ui.row-action icon="revert" label="برگشت" wire:click="revert({{ $order->id }})" wire:confirm="برگشت از ثبت قطعی؟" />
                                            <x-erp.ui.row-action icon="print" label="چاپ" :href="route('management-reports.hr-employment-orders.print-form', $order)" target="_blank" />
                                        @else
                                            <x-erp.ui.row-action icon="edit" label="ویرایش" wire:click="edit({{ $order->id }})" />
                                            <x-erp.ui.row-action icon="print" label="چاپ" :href="route('management-reports.hr-employment-orders.print-form', $order)" target="_blank" />
                                            <x-erp.ui.row-action icon="confirm" label="تایید" tone="success" wire:click="approve({{ $order->id }})" wire:confirm="تایید شود؟" />
                                            <x-erp.ui.row-action icon="delete" label="حذف" tone="danger" wire:click="delete({{ $order->id }})" wire:confirm="حذف شود؟" />
                                        @endif
                                    </x-erp.ui.row-actions>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-6 text-slate-500">حکمی ثبت نشده است.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $orders->links() }}
        </div>
    </div>
</div>
