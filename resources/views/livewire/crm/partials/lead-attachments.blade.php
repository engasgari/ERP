@props([
    'attachments',
    'compact' => false,
    'uploadUrl' => null,
    'leadId' => null,
    'canUpload' => null,
    'canDelete' => null,
])

@php
    $uploadUrl = $uploadUrl ?? ($leadId ? route('crm.leads.attachments.store', $leadId) : null);
    $canUpload = $canUpload ?? (auth()->user()?->hasPermission('crm.leads.update') || auth()->user()?->hasPermission('crm.leads.create'));
    $canDelete = $canDelete ?? auth()->user()?->hasPermission('crm.leads.update');
@endphp

<section @class(['crm-lead-attachments', 'crm-lead-attachments--compact' => $compact]) aria-label="پیوست‌ها">
    @unless($compact)
        <h3 class="crm-lead-attachments__title">فایل درخواست / لیست</h3>
        <p class="crm-lead-attachments__hint">PDF، عکس یا Excel درخواست را انتخاب کنید؛ بلافاصله آپلود و در لیست پایین نمایش داده می‌شود (حداکثر ۲ مگابایت).</p>
    @endunless

    @if($canUpload && $uploadUrl)
        <div
            class="crm-lead-attachments__upload"
            x-data="{
                uploading: false,
                error: null,
                async uploadFile(event) {
                    const file = event.target.files?.[0];
                    if (! file) {
                        return;
                    }

                    this.error = null;

                    if (file.size > {{ \App\Services\Crm\CrmAttachmentService::MAX_BYTES }}) {
                        this.error = 'حداکثر حجم فایل ۲ مگابایت است.';
                        event.target.value = '';
                        return;
                    }

                    this.uploading = true;

                    const body = new FormData();
                    body.append('file', file);

                    try {
                        const response = await fetch(@js($uploadUrl), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body,
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (! response.ok || ! payload.ok) {
                            throw new Error(payload.message ?? 'آپلود فایل انجام نشد.');
                        }

                        await $wire.$refresh();
                    } catch (error) {
                        this.error = error?.message ?? 'آپلود فایل انجام نشد.';
                    } finally {
                        this.uploading = false;
                        event.target.value = '';
                    }
                },
            }"
        >
            <label class="crm-lead-attachments__picker" :class="{ 'is-uploading': uploading }">
                <input
                    type="file"
                    class="crm-lead-attachments__picker-input"
                    accept=".pdf,.png,.jpg,.jpeg,.webp,.gif,.xls,.xlsx,.csv,.doc,.docx,application/pdf,image/*"
                    :disabled="uploading"
                    @change="uploadFile($event)"
                >
                <span class="crm-lead-attachments__picker-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V4m0 0L8 8m4-4 4 4"/><path d="M4 17v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1"/></svg>
                </span>
                <span class="crm-lead-attachments__picker-text">
                    <span class="crm-lead-attachments__picker-title" x-text="uploading ? 'در حال آپلود...' : 'انتخاب فایل پیوست'"></span>
                    <span class="crm-lead-attachments__picker-sub">PDF · تصویر · Excel — حداکثر ۲ مگ</span>
                </span>
            </label>
            <p class="crm-lead-attachments__error" x-show="error" x-text="error" x-cloak></p>
        </div>
    @endif

    @if($attachments->isEmpty())
        <p class="crm-lead-attachments__empty">هنوز فایلی پیوست نشده.</p>
    @else
        <ul class="crm-lead-attachments__list">
            @foreach($attachments as $attachment)
                <li wire:key="crm-attachment-{{ $attachment->id }}" class="crm-lead-attachments__item">
                    <a
                        href="{{ route('crm.attachments.show', $attachment) }}"
                        target="_blank"
                        rel="noopener"
                        class="crm-lead-attachments__open"
                    >
                        <span class="crm-lead-attachments__icon" aria-hidden="true">
                            @if($attachment->isImage())
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.5"/><path d="M21 17l-5-5-4 4-2-2-5 5"/></svg>
                            @elseif($attachment->isPdf())
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                            @endif
                        </span>
                        <span class="crm-lead-attachments__meta">
                            <span class="crm-lead-attachments__name">{{ $attachment->original_name ?: 'فایل' }}</span>
                            <span class="crm-lead-attachments__size">{{ $attachment->humanSize() }}</span>
                        </span>
                        <span class="crm-lead-attachments__action">مشاهده</span>
                    </a>
                    @if($canDelete)
                        <button
                            type="button"
                            class="crm-lead-attachments__delete"
                            wire:click="deleteCrmAttachment({{ $attachment->id }})"
                            wire:confirm="این فایل حذف شود؟"
                            aria-label="حذف {{ $attachment->original_name }}"
                        >×</button>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>
