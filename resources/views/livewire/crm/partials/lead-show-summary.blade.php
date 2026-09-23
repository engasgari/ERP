@php
    /** @var \App\Models\Crm\Lead $lead */
    use App\Support\CrmLeadDialPhone;
    use App\Support\CrmOpportunityDialPhone;

    $dialPhone = CrmLeadDialPhone::resolve($lead);
    $mobileTel = $lead->mobile ? CrmOpportunityDialPhone::toTelUri($lead->mobile) : null;
@endphp

<section class="crm-lead-show-summary" aria-label="خلاصه سرنخ">

    <h2 class="crm-lead-show-summary__title">{{ $lead->title }}</h2>

    @if($lead->company_name)
        <p class="crm-lead-show-summary__company">{{ $lead->company_name }}</p>
    @endif

    @if($lead->contact_name !== '' && ! $lead->mobile)
        <p class="crm-lead-show-summary__contact">{{ $lead->contact_name }}</p>
    @endif

    <p class="crm-lead-show-summary__phone">
        @if($lead->contact_name !== '' && $lead->mobile)
            <span class="crm-lead-show-summary__phone-name">{{ $lead->contact_name }}</span>
            <span class="crm-lead-show-summary__phone-sep" aria-hidden="true">·</span>
            @if($mobileTel)
                <a href="{{ $mobileTel }}" class="crm-lead-show-summary__phone-link" dir="ltr">{{ $lead->mobile }}</a>
            @else
                <span class="crm-lead-show-summary__phone-number" dir="ltr">{{ $lead->mobile }}</span>
            @endif
        @elseif($lead->mobile)
            @if($mobileTel)
                <a href="{{ $mobileTel }}" class="crm-lead-show-summary__phone-link" dir="ltr">{{ $lead->mobile }}</a>
            @else
                <span class="crm-lead-show-summary__phone-number" dir="ltr">{{ $lead->mobile }}</span>
            @endif
        @elseif($lead->contact_name !== '')
            <span class="crm-lead-show-summary__phone-name">{{ $lead->contact_name }}</span>
        @else
            <span class="crm-lead-show-summary__phone-number">—</span>
        @endif
    </p>

    @if($dialPhone)
        <a
            href="{{ $dialPhone['tel'] }}"
            class="crm-log-call-dial-btn crm-lead-summary-dial"
            aria-label="برقراری تماس با {{ $dialPhone['display'] }}"
        >
            <span class="crm-log-call-dial-btn__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </span>
            <span class="crm-log-call-dial-btn__text">برقراری تماس</span>
            <span class="crm-log-call-dial-btn__number" dir="ltr">{{ $dialPhone['display'] }}</span>
            <span class="crm-log-call-dial-btn__meta">{{ $dialPhone['source'] }}</span>
        </a>
    @endif

    @if($lead->description)
        <div class="crm-lead-show-summary__description">{{ $lead->description }}</div>
    @endif

    <div class="crm-lead-show-summary__meta">
        <span class="crm-lead-show-chip">{{ $lead->status_label }}</span>
        @if($lead->source?->title)
            <span class="crm-lead-show-chip">منبع: {{ $lead->source->title }}</span>
        @endif
        @if($lead->assignedUser?->name)
            <span class="crm-lead-show-chip">مسئول: {{ $lead->assignedUser->name }}</span>
        @endif
        @if($lead->estimated_value)
            <span class="crm-lead-show-chip">ارزش: {{ formatMoney((float) $lead->estimated_value) }}</span>
        @endif
        @if($lead->expected_close_date)
            <span class="crm-lead-show-chip">بستن: {{ gregorianToJalaliDate($lead->expected_close_date) }}</span>
        @endif
    </div>

</section>
