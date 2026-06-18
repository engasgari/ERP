@props(['message' => 'داده‌ای برای نمایش وجود ندارد.', 'actionLabel' => '', 'actionUrl' => ''])
<div class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
    <div class="text-sm font-semibold text-slate-700">{{ $message }}</div>
    @if($actionLabel && $actionUrl)
        <a href="{{ $actionUrl }}" class="erp-action-btn erp-action-edit mt-4 inline-flex">{{ $actionLabel }}</a>
    @endif
</div>
