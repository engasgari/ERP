@extends('layouts.embed')

@section('content')
    <div class="space-y-4 p-1" id="crm-proforma-link-picker">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-black text-slate-900">اتصال پیش‌فاکتور موجود</h2>
                <p class="mt-1 text-xs text-slate-500">
                    پیش‌فاکتورهای فروش ERP مربوط به
                    <span class="font-bold text-slate-700">{{ $opportunity->party?->name ?: 'مشتری فرصت' }}</span>
                    را انتخاب کنید تا به این فرصت وصل شوند.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="erp-action-btn" id="crm-proforma-link-cancel">انصراف</button>
                <a href="{{ $composeUrl }}" class="erp-action-btn erp-action-edit">صدور پیش‌فاکتور جدید</a>
            </div>
        </div>

        <div id="crm-proforma-link-errors" class="invoice-error" hidden></div>

        <form method="GET" action="{{ route('crm.proforma.link-picker', $opportunity) }}" class="flex flex-wrap gap-2">
            <input
                type="search"
                name="q"
                value="{{ $search }}"
                placeholder="جستجو شماره یا توضیحات..."
                class="min-w-[16rem] flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm"
            >
            <button type="submit" class="erp-action-btn">جستجو</button>
        </form>

        @if($proformas->isEmpty())
            <div class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-600">
                @if(! $opportunity->party_id)
                    برای این فرصت مشتری تعریف نشده است؛ ابتدا مشتری فرصت را مشخص کنید.
                @elseif($search !== '')
                    پیش‌فاکتوری با این جستجو پیدا نشد.
                @else
                    برای این مشتری پیش‌فاکتور فروش فعالی در ERP یافت نشد.
                @endif
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-3 py-2 text-right font-bold">شماره</th>
                            <th class="px-3 py-2 text-right font-bold">تاریخ</th>
                            <th class="px-3 py-2 text-right font-bold">مبلغ</th>
                            <th class="px-3 py-2 text-right font-bold">وضعیت</th>
                            <th class="px-3 py-2 text-right font-bold">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proformas as $proforma)
                            <tr class="border-t border-slate-100">
                                <td class="px-3 py-2 font-semibold" dir="ltr">{{ $proforma->number }}</td>
                                <td class="px-3 py-2">{{ gregorianToJalaliDate($proforma->invoice_date) }}</td>
                                <td class="px-3 py-2" dir="ltr">{{ formatMoney((float) $proforma->total_amount) }}</td>
                                <td class="px-3 py-2">
                                    @if($proforma->already_linked_elsewhere)
                                        <span class="text-amber-700">متصل به فرصت دیگر</span>
                                    @else
                                        {{ $proforma->status === 'draft' ? 'موقت' : ($proforma->status === 'confirmed' ? 'تایید شده' : $proforma->status) }}
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <button
                                        type="button"
                                        class="erp-action-btn erp-action-edit"
                                        data-link-proforma="{{ $proforma->id }}"
                                        @if($proforma->already_linked_elsewhere)
                                            data-confirm-relink="1"
                                        @endif
                                    >
                                        انتخاب و اتصال
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <script>
        (() => {
            const root = document.getElementById('crm-proforma-link-picker');
            const errors = document.getElementById('crm-proforma-link-errors');
            const linkAction = @json($linkAction);
            const csrf = @json(csrf_token());

            document.getElementById('crm-proforma-link-cancel')?.addEventListener('click', () => {
                window.parent.postMessage({ type: 'invoice-edit-cancel' }, window.location.origin);
            });

            root?.addEventListener('click', async (event) => {
                const trigger = event.target.closest('[data-link-proforma]');
                if (!trigger) {
                    return;
                }

                if (trigger.dataset.confirmRelink === '1') {
                    const ok = window.confirm('این پیش‌فاکتور به فرصت دیگری هم وصل است. باز هم به این فرصت متصل شود؟');
                    if (!ok) {
                        return;
                    }
                }

                if (errors) {
                    errors.hidden = true;
                    errors.textContent = '';
                }

                trigger.disabled = true;
                const original = trigger.textContent;
                trigger.textContent = 'در حال اتصال...';

                try {
                    const body = new FormData();
                    body.append('_token', csrf);
                    body.append('invoice_id', trigger.dataset.linkProforma);

                    const response = await fetch(linkAction, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                        },
                        credentials: 'same-origin',
                        body,
                    });

                    const contentType = response.headers.get('content-type') || '';
                    let payload = {};
                    if (contentType.includes('application/json')) {
                        payload = await response.json();
                    }

                    if (!response.ok) {
                        const message = payload.errors
                            ? Object.values(payload.errors).flat().join(' ')
                            : (payload.message || 'اتصال پیش‌فاکتور انجام نشد.');
                        throw new Error(message);
                    }

                    if (!payload.invoice?.id) {
                        throw new Error(payload.message || 'پاسخ اتصال نامعتبر بود.');
                    }

                    window.parent.postMessage({
                        type: 'invoice-saved',
                        message: payload.message || 'پیش‌فاکتور به فرصت متصل شد.',
                        invoice: payload.invoice,
                    }, window.location.origin);
                } catch (error) {
                    const message = error.message || 'اتصال پیش‌فاکتور انجام نشد.';
                    if (errors) {
                        errors.hidden = false;
                        errors.textContent = message;
                    }
                    if (typeof window.showErpToast === 'function') {
                        window.showErpToast(message, 'danger');
                    }
                } finally {
                    trigger.disabled = false;
                    trigger.textContent = original;
                }
            });
        })();
    </script>
@endsection
