@php
    $opportunity = $opportunityTimelineOverview['opportunity'];
    $canMoveStage = auth()->user()?->hasPermission('crm.opportunities.move_stage');
@endphp

<button type="button" class="erp-action-btn" wire:click="closeOpportunityTimeline">بستن</button>

@if($opportunity->invoice)
    <button
        type="button"
        class="erp-action-btn erp-action-detail"
        @if(($proformaButtonMode ?? null) === 'kanban')
            data-crm-proforma-show="{{ $opportunity->invoice->id }}"
        @else
            data-invoice-show="{{ $opportunity->invoice->id }}"
            data-invoice-show-crm="1"
            data-invoice-show-title="پیش‌فاکتور {{ $opportunity->invoice->number }}"
        @endif
    >
        مشاهده پیش‌فاکتور
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
