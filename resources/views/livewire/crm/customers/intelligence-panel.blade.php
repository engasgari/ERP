@php
    $result = $intelligence?->result ?? [];
    $profile = $result['profile'] ?? [];
    $identity = $result['identity'] ?? [];
    $identityLabels = ['matched' => 'شناسایی شد', 'ambiguous' => 'مبهم', 'not_found' => 'یافت نشد'];
    $confidenceLabels = ['high' => 'بالا', 'medium' => 'متوسط', 'low' => 'پایین'];
    $freshnessLabels = [
        'fresh' => ['تازه', 'crm-ai-badge--ok'],
        'review' => ['نیازمند بازبینی', 'crm-ai-badge--warn'],
        'stale' => ['قدیمی — نیازمند به‌روزرسانی', 'crm-ai-badge--danger'],
    ];
    $reviewLabels = ['pending_review' => 'در انتظار بازبینی', 'accepted' => 'تأییدشده', 'rejected' => 'ردشده', 'expired' => 'منقضی', 'applied' => 'اعمال‌شده'];
    $fetchLabels = [
        'ok' => 'دریافت شد', 'invalid_url' => 'نشانی نامعتبر', 'blocked_host' => 'میزبان غیرمجاز',
        'http_error' => 'خطای HTTP', 'redirect' => 'تغییر مسیر (تأیید نشد)', 'unsupported_content' => 'نوع محتوا نامعتبر', 'failed' => 'دریافت ناموفق',
    ];
@endphp

<section class="crm-ai-panel" @if($isPending) wire:poll.5s @endif>
    @if($message)
        <div @class(['crm-ai-alert', 'crm-ai-alert--error' => $messageType === 'error', 'crm-ai-alert--success' => $messageType === 'success'])>
            {{ $message }}
        </div>
    @endif

    <div class="crm-ai-card">
        <div class="crm-ai-card-head">
            <h3 class="crm-ai-title">سابقه CRM <span class="crm-ai-subtitle">(محاسبه‌شده از داده‌های سیستم)</span></h3>
            <span class="crm-ai-badge">{{ $summary['relationship_label'] }}</span>
        </div>

        <div class="crm-ai-grid">
            <div class="crm-ai-fact">
                <span class="crm-ai-fact-label">آخرین پیگیری</span>
                <span class="crm-ai-fact-value">{{ $summary['last_follow_up_at'] ? formatJalaliDateTime($summary['last_follow_up_at']) : 'ثبت نشده' }}</span>
            </div>
            <div class="crm-ai-fact">
                <span class="crm-ai-fact-label">آخرین تماس</span>
                <span class="crm-ai-fact-value">{{ $summary['last_call_at'] ? formatJalaliDateTime($summary['last_call_at']) : 'ثبت نشده' }}</span>
            </div>
            <div class="crm-ai-fact">
                <span class="crm-ai-fact-label">تماس‌های ۹۰ روز اخیر</span>
                <span class="crm-ai-fact-value">{{ $summary['calls_last_90_days'] }}</span>
            </div>
            <div class="crm-ai-fact">
                <span class="crm-ai-fact-label">وظایف باز / عقب‌افتاده</span>
                <span class="crm-ai-fact-value">{{ $summary['open_tasks'] }} / <span @class(['text-rose-600' => $summary['overdue_tasks'] > 0])>{{ $summary['overdue_tasks'] }}</span></span>
            </div>
            <div class="crm-ai-fact">
                <span class="crm-ai-fact-label">فرصت‌های باز</span>
                <span class="crm-ai-fact-value">{{ $summary['open_opportunities']['count'] }} — {{ formatMoney((float) $summary['open_opportunities']['total_amount']) }}</span>
            </div>
            <div class="crm-ai-fact">
                <span class="crm-ai-fact-label">پیش‌فاکتورهای باز</span>
                <span class="crm-ai-fact-value">{{ $summary['open_proformas']['count'] }} — {{ formatMoney((float) $summary['open_proformas']['total_amount']) }}</span>
            </div>
            <div class="crm-ai-fact">
                <span class="crm-ai-fact-label">فاکتورهای فروش قطعی</span>
                <span class="crm-ai-fact-value">{{ $summary['sale_invoices']['count'] }} — {{ formatMoney((float) $summary['sale_invoices']['total_amount']) }}</span>
            </div>
            <div class="crm-ai-fact">
                <span class="crm-ai-fact-label">آخرین فاکتور</span>
                <span class="crm-ai-fact-value" dir="auto">
                    {{ $summary['sale_invoices']['latest_number'] ? $summary['sale_invoices']['latest_number'].' — '.gregorianToJalaliDate($summary['sale_invoices']['latest_date']) : 'ندارد' }}
                </span>
            </div>
        </div>

        @if($summary['next_task'])
            <p class="crm-ai-note">وظیفه بعدی: {{ $summary['next_task']['title'] }} — سررسید {{ formatJalaliDateTime($summary['next_task']['due_at']) }}</p>
        @endif
    </div>

    <div class="crm-ai-card">
        <div class="crm-ai-card-head">
            <h3 class="crm-ai-title">خلاصه هوشمند مشتری <span class="crm-ai-subtitle">(پیشنهاد هوش مصنوعی — نیازمند بازبینی انسانی)</span></h3>

            @if($aiEnabled && $canRequest)
                <button type="button" class="erp-action-btn erp-action-edit" wire:click="requestSummary" wire:loading.attr="disabled" @disabled($isPending)>
                    {{ $intelligence ? 'تولید مجدد' : 'تولید خلاصه' }}
                </button>
            @endif
        </div>

        @if(! $aiEnabled)
            <p class="crm-ai-empty">قابلیت هوش مصنوعی غیرفعال است.</p>
        @elseif(! $intelligence)
            <p class="crm-ai-empty">هنوز خلاصه‌ای برای این مشتری تولید نشده است.</p>
        @elseif($isPending)
            <p class="crm-ai-empty" wire:loading.remove>در حال تولید خلاصه… این بخش خودکار به‌روزرسانی می‌شود.</p>
        @elseif($intelligence->status === 'failed')
            <p class="crm-ai-alert crm-ai-alert--error">تولید خلاصه ناموفق بود: {{ $intelligence->failure_reason ?: 'خطای نامشخص' }}</p>
        @endif

        @if($intelligence && in_array($intelligence->status, ['completed', 'insufficient_data'], true))
            <div class="crm-ai-meta">
                @if($freshness)
                    <span class="crm-ai-badge {{ $freshnessLabels[$freshness][1] }}">{{ $freshnessLabels[$freshness][0] }}</span>
                @endif
                <span class="crm-ai-badge">هویت: {{ $identityLabels[$identity['status'] ?? ''] ?? '—' }}</span>
                <span class="crm-ai-badge">اطمینان: {{ $confidenceLabels[$identity['confidence'] ?? ''] ?? '—' }}</span>
                <span class="crm-ai-badge">بازبینی: {{ $reviewLabels[$intelligence->review_status] ?? $intelligence->review_status }}</span>
                <span class="crm-ai-meta-text">
                    تولید: {{ formatJalaliDateTime($intelligence->generated_at) }}
                    · مدل: <span dir="ltr">{{ $intelligence->model ?: '—' }}</span>
                    · نسخه Prompt: <span dir="ltr">{{ $intelligence->prompt_version }}</span>
                </span>
            </div>

            @if(! empty($identity['ambiguities']))
                <div class="crm-ai-warning">
                    <strong>ابهام در تشخیص هویت:</strong>
                    <ul>
                        @foreach($identity['ambiguities'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <h4 class="crm-ai-section-title">اطلاعات بیرونی (از منابع تأییدشده)</h4>
            @if(! empty($profile['summary']) || ! empty($profile['industry']) || ! empty($profile['products_services']))
                <dl class="crm-ai-dl">
                    @if(! empty($identity['matched_name']))
                        <dt>نام شناسایی‌شده</dt><dd>{{ $identity['matched_name'] }}</dd>
                    @endif
                    @if(! empty($profile['industry']))
                        <dt>حوزه فعالیت</dt><dd>{{ $profile['industry'] }}</dd>
                    @endif
                    @if(! empty($profile['summary']))
                        <dt>خلاصه</dt><dd>{{ $profile['summary'] }}</dd>
                    @endif
                    @if(! empty($profile['products_services']))
                        <dt>محصولات و خدمات</dt><dd>{{ implode('، ', $profile['products_services']) }}</dd>
                    @endif
                    @if(! empty($profile['official_website']))
                        <dt>وب‌سایت رسمی</dt>
                        <dd dir="ltr"><a href="{{ $profile['official_website'] }}" target="_blank" rel="noopener noreferrer nofollow" class="text-blue-700 hover:underline">{{ $profile['official_website'] }}</a></dd>
                    @endif
                    @if(! empty($result['relevance_to_aleh']))
                        <dt>ارتباط با محصولات آله</dt><dd>{{ $result['relevance_to_aleh'] }}</dd>
                    @endif
                </dl>
            @else
                <p class="crm-ai-empty">اطلاعات بیرونی تأییدشده‌ای در دسترس نیست.</p>
            @endif

            @if(! empty($result['suggested_next_action']))
                <h4 class="crm-ai-section-title">اقدام پیشنهادی</h4>
                <p class="crm-ai-note">{{ $result['suggested_next_action'] }}</p>
            @endif

            @if(! empty($result['missing_information']))
                <h4 class="crm-ai-section-title">اطلاعات ناقص / نیازمند بررسی</h4>
                <ul class="crm-ai-list">
                    @foreach($result['missing_information'] as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif

            @if($intelligence->sources->isNotEmpty())
                <h4 class="crm-ai-section-title">منابع بررسی‌شده</h4>
                <ul class="crm-ai-sources">
                    @foreach($intelligence->sources as $source)
                        <li wire:key="ci-source-{{ $source->id }}" @class(['crm-ai-source', 'crm-ai-source--unverified' => ! $source->is_verified])>
                            <span class="crm-ai-badge {{ $source->is_verified ? 'crm-ai-badge--ok' : 'crm-ai-badge--danger' }}">
                                {{ $source->is_verified ? 'تأییدشده' : 'تأیید نشده' }}
                            </span>
                            @if($source->is_verified)
                                <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer nofollow" dir="ltr" class="text-blue-700 hover:underline">{{ $source->title ?: $source->domain }}</a>
                            @else
                                <span dir="ltr" class="text-slate-500">{{ $source->domain }}</span>
                            @endif
                            <span class="crm-ai-meta-text">{{ $fetchLabels[$source->fetch_status] ?? $source->fetch_status }} · {{ $source->last_checked_at ? formatJalaliDateTime($source->last_checked_at) : '—' }}</span>
                            @if($source->is_verified && ! empty($source->claims))
                                <ul class="crm-ai-list">
                                    @foreach($source->claims as $claim)
                                        <li>{{ $claim }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if($canRequest && $intelligence->review_status === 'pending_review')
                <div class="crm-ai-actions">
                    <button type="button" class="erp-action-btn erp-action-edit" wire:click="review({{ $intelligence->id }}, true)">تأیید خلاصه</button>
                    <button type="button" class="erp-action-btn erp-action-delete" wire:click="review({{ $intelligence->id }}, false)">رد خلاصه</button>
                </div>
            @endif
        @endif
    </div>
</section>
