<div class="crm-ai-planner" @if($isPending) wire:poll.5s @endif>
    <x-erp.ui.detail-page
        title="پیگیری هوشمند"
        description="وظایف فعلی هر نفر و پیشنهادهای زمان‌بندی‌شده هوش مصنوعی برای پیگیری بعدی."
        route="crm.ai.sales-task-planner"
    >
        @if($message)
            <div class="crm-ai-alert crm-ai-alert--{{ $messageType === 'error' ? 'error' : 'success' }} mb-4">
                {{ $message }}
            </div>
        @endif

        <div class="crm-ai-card mb-4">
            <div class="crm-ai-card-head">
                <div>
                    <div class="crm-ai-title">برنامه پیگیری فروش</div>
                    <div class="crm-ai-subtitle">
                        Laravel کاندیداها را انتخاب می‌کند؛ هوش مصنوعی اولویت و زمان را پیشنهاد می‌دهد؛ ایجاد وظیفه فقط بعد از تأیید شما انجام می‌شود.
                    </div>
                </div>
                <div class="crm-ai-actions">
                    @if($aiEnabled && $canRequest)
                        <button type="button" class="erp-btn erp-btn--primary" wire:click="requestPlan" wire:loading.attr="disabled">
                            {{ $isPending ? 'در حال تولید…' : ($plan ? 'تولید مجدد برنامه' : 'تولید برنامه پیگیری') }}
                        </button>
                    @elseif(! $aiEnabled)
                        <span class="crm-ai-badge crm-ai-badge--warn">قابلیت برنامه‌ریز هوشمند غیرفعال است</span>
                    @endif
                </div>
            </div>

            <div class="crm-ai-meta">
                <span class="crm-ai-badge">وظایف باز: {{ number_format($openTaskCount) }}</span>
                <span class="crm-ai-badge crm-ai-badge--warn">پیشنهاد در انتظار تأیید: {{ number_format($pendingSuggestionCount) }}</span>
                @if($plan)
                    <span class="crm-ai-meta-text">
                        آخرین اجرا:
                        @if($plan->status === 'pending') در حال پردازش
                        @elseif($plan->status === 'completed') موفق
                        @elseif($plan->status === 'failed') ناموفق
                        @else {{ $plan->status }}
                        @endif
                        @if($plan->generated_at) — {{ formatJalaliDateTime($plan->generated_at) }} @endif
                    </span>
                @endif
            </div>

            @if($plan?->status === 'failed' && $plan->failure_reason)
                <div class="crm-ai-alert crm-ai-alert--error mt-3">{{ $plan->failure_reason }}</div>
            @endif

            @if($plan?->planner_note)
                <div class="crm-ai-note mt-3">{{ $plan->planner_note }}</div>
            @endif
        </div>

        @forelse($groups as $group)
            <div class="crm-ai-card mb-4">
                <div class="crm-ai-card-head">
                    <div>
                        <div class="crm-ai-title">{{ $group['user_name'] }}</div>
                        <div class="crm-ai-subtitle">
                            {{ $group['tasks']->count() }} وظیفه باز ·
                            {{ $group['suggestions']->count() }} پیشنهاد جدید
                        </div>
                    </div>
                </div>

                @if($group['tasks']->isNotEmpty())
                    <div class="crm-ai-section-title">وظایف فعلی</div>
                    <div class="crm-ai-table-wrap">
                        <table class="crm-ai-table">
                            <thead>
                                <tr>
                                    <th>عنوان</th>
                                    <th>مشتری</th>
                                    <th>اولویت</th>
                                    <th>موعد</th>
                                    <th>وضعیت</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group['tasks'] as $task)
                                    <tr class="{{ $task->due_at && $task->due_at->isPast() ? 'crm-ai-row--overdue' : '' }}">
                                        <td>{{ $task->title }}</td>
                                        <td>{{ $task->party?->name ?? '—' }}</td>
                                        <td>{{ $task->priority_label }}</td>
                                        <td>
                                            @if($task->due_at)
                                                {{ formatJalaliDateTime($task->due_at) }}
                                                @if($task->due_at->isPast())
                                                    <span class="crm-ai-badge crm-ai-badge--danger">عقب‌افتاده</span>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>{{ $task->status_label }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if($group['suggestions']->isNotEmpty())
                    <div class="crm-ai-section-title mt-4">پیشنهادهای هوش مصنوعی</div>
                    <div class="crm-ai-suggestion-list">
                        @foreach($group['suggestions'] as $suggestion)
                            <div class="crm-ai-suggestion">
                                <div class="crm-ai-suggestion__main">
                                    <div class="crm-ai-suggestion__title">
                                        <span class="crm-ai-badge">#{{ $suggestion->rank }}</span>
                                        <strong>{{ $suggestion->title }}</strong>
                                        <span class="crm-ai-badge crm-ai-badge--warn">{{ $suggestion->kind_label }}</span>
                                        <span class="crm-ai-badge">{{ $suggestion->priority_label }}</span>
                                    </div>
                                    <div class="crm-ai-meta-text">
                                        {{ $suggestion->party?->name ?? 'بدون مشتری' }}
                                        @if($suggestion->due_at)
                                            · موعد پیشنهادی: {{ formatJalaliDateTime($suggestion->due_at) }}
                                            ({{ $suggestion->due_offset_business_hours }} ساعت کاری)
                                        @endif
                                    </div>
                                    @if($suggestion->reason)
                                        <div class="crm-ai-note">{{ $suggestion->reason }}</div>
                                    @endif
                                    @if(!empty($suggestion->supporting_facts))
                                        <ul class="crm-ai-list">
                                            @foreach($suggestion->supporting_facts as $fact)
                                                <li>{{ $fact }}</li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </div>
                                @if($canApply)
                                    <div class="crm-ai-actions">
                                        <button type="button" class="erp-btn erp-btn--primary" wire:click="applySuggestion({{ $suggestion->id }})">
                                            تأیید و ایجاد وظیفه
                                        </button>
                                        <button type="button" class="erp-btn" wire:click="rejectSuggestion({{ $suggestion->id }})">
                                            رد
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <x-erp.ui.empty-state message="فعلاً وظیفه باز یا پیشنهاد پیگیری برای نمایش وجود ندارد. با دکمه تولید برنامه شروع کنید." />
        @endforelse
    </x-erp.ui.detail-page>
</div>
