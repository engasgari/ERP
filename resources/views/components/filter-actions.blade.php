@props(['resetRoute'])

<div {{ $attributes->merge(['class' => 'erp-filter-actions']) }}>
    <button type="submit" class="erp-filter-submit">
        اعمال فیلتر
    </button>
    <a href="{{ $resetRoute }}" class="erp-filter-reset">
        پاک کردن
    </a>
</div>
