@php
    $opportunity = $opportunityTimelineOverview['opportunity'];
    $canMoveStage = auth()->user()?->hasPermission('crm.opportunities.move_stage');
    $linkedInvoice = $opportunity->invoice;
    $isProforma = $linkedInvoice && $linkedInvoice->document_type === 'proforma';
    $isSaleInvoice = $linkedInvoice && $linkedInvoice->document_type === 'invoice';
    $documentLabel = $isSaleInvoice ? 'فاکتور فروش' : 'پیش‌فاکتور';
    $documentTitle = $documentLabel.' '.$linkedInvoice?->number;
@endphp

<button type="button" class="erp-action-btn" wire:click="closeOpportunityTimeline">بستن</button>

@if($linkedInvoice)
    <button
        type="button"
        class="erp-action-btn erp-action-detail"
        @if($isProforma && ($proformaButtonMode ?? null) === 'kanban')
            data-crm-proforma-show="{{ $linkedInvoice->id }}"
        @else
            data-invoice-show="{{ $linkedInvoice->id }}"
            data-invoice-show-crm="1"
            data-invoice-show-title="{{ $documentTitle }}"
        @endif
    >
        @if($isSaleInvoice)
            فاکتور فروش
        @else
            مشاهده پیش‌فاکتور
        @endif
    </button>
@endif

@if($opportunity->status === 'won' && $canMoveStage)
    <button
        type="button"
        class="erp-action-btn erp-action-detail inline-flex items-center gap-2"
        wire:click="reopenFromWon({{ $opportunity->id }})"
        wire:confirm="این فرصت از وضعیت برنده به مرحله قبل برمی‌گردد و دوباره باز می‌شود. ادامه می‌دهید؟"
        title="بازگشت به مرحله قبل"
    >
        <x-erp.ui.action-icon name="revert" />
        <span>بازگشت به مرحله قبل</span>
    </button>
@endif

@if($opportunity->status === 'open')
    <button type="button" class="erp-action-btn erp-action-detail" wire:click="closeOpportunityTimeline(); openOpportunityActivity({{ $opportunity->id }})">ثبت فعالیت</button>
@endif

<button type="button" class="erp-action-btn erp-action-edit" wire:click="closeOpportunityTimeline(); openOpportunityEdit({{ $opportunity->id }})">ویرایش فرصت</button>
