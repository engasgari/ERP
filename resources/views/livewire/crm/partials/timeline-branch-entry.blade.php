@props(['entry'])

@php
    $invoiceId = $entry['invoice_id'] ?? null;
    $isSaleInvoice = ($entry['invoice_document_type'] ?? null) === 'invoice';
    $documentLabel = $isSaleInvoice ? 'فاکتور فروش' : 'پیش‌فاکتور';
    $documentTitle = trim($documentLabel.' '.($entry['title'] ?? ''));
@endphp

<article @class([
    'crm-timeline-branch-entry',
    'crm-timeline-branch-entry--' . ($entry['kind'] ?? 'other'),
    'crm-timeline-branch-entry--pending' => ($entry['is_pending_task'] ?? false),
])>
    <div class="crm-timeline-branch-marker" aria-hidden="true"></div>
    <div class="crm-timeline-branch-body">
        <div class="crm-timeline-branch-head">
            <span class="crm-timeline-entry-badge">{{ $entry['badge'] ?? 'رویداد' }}</span>
            <time class="crm-timeline-entry-meta">{{ formatJalaliDateTime($entry['occurred_at']) }}</time>
        </div>
        <div class="crm-timeline-branch-title">{{ $entry['title'] }}</div>
        @if(! empty($entry['summary']))
            <p class="crm-timeline-entry-summary">{{ $entry['summary'] }}</p>
        @endif
        @if(! empty($entry['user_name']))
            <div class="crm-timeline-entry-user">توسط {{ $entry['user_name'] }}</div>
        @endif
        @if($invoiceId)
            <div class="crm-timeline-branch-actions">
                <button
                    type="button"
                    class="erp-btn erp-btn-secondary erp-btn-sm"
                    data-invoice-show="{{ $invoiceId }}"
                    data-invoice-show-crm="1"
                    data-invoice-show-title="{{ $documentTitle }}"
                >
                    مشاهده {{ $documentLabel }}
                </button>
            </div>
        @endif
    </div>
</article>
