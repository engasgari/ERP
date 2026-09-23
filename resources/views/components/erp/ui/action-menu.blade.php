@props(['actions' => []])

@php
    $inferIcon = function (array $action): string {
        if (! empty($action['icon'])) {
            return $action['icon'];
        }

        $label = $action['label'] ?? '';
        $class = $action['class'] ?? '';

        return match (true) {
            str_contains($label, 'حذف') || str_contains($class, 'delete') => 'delete',
            str_contains($label, 'سفارش تولید') || str_contains($label, 'تولید') => 'production',
            str_contains($label, 'ویرایش') || str_contains($class, 'edit') => 'edit',
            str_contains($label, 'جزئیات') || $label === 'مشاهده' => 'view',
            str_contains($label, 'نمایش کامل') => 'expand',
            str_contains($label, 'چاپ') => 'print',
            str_contains($label, 'تایید') => 'confirm',
            str_contains($label, 'رد') => 'reject',
            str_contains($label, 'تسویه') => 'settle',
            str_contains($label, 'برگشت') => 'revert',
            str_contains($label, 'بستن') => 'close',
            str_contains($label, 'بازگشایی') => 'reopen',
            str_contains($label, 'گزارش') || str_contains($label, 'بهای') => 'chart',
            str_contains($label, 'سند') => 'document',
            str_contains($label, 'تبدیل') => 'convert',
            str_contains($label, 'سفارش') => 'production',
            str_contains($label, 'صدور') => 'issue',
            str_contains($label, 'محاسبه') => 'calculate',
            str_contains($label, 'پرداخت') => 'payment',
            default => 'view',
        };
    };

    $inferTone = function (array $action): string {
        $class = $action['class'] ?? '';
        if (str_contains($class, 'delete')) {
            return 'danger';
        }
        if (str_contains($class, 'settle')) {
            return 'success';
        }

        $label = $action['label'] ?? '';
        if (str_contains($label, 'حذف') || str_contains($label, 'رد')) {
            return 'danger';
        }

        return 'default';
    };
@endphp

<x-erp.ui.row-actions>
    @foreach($actions as $action)
        @php
            $label = $action['label'] ?? '';
            $icon = $inferIcon($action);
            $tone = $inferTone($action);
            $attrs = $action['attrs'] ?? [];
        @endphp
        @if(($action['type'] ?? 'link') === 'button')
            <button
                type="button"
                class="erp-row-action-btn {{ $tone === 'danger' ? 'erp-row-action-btn--danger' : ($tone === 'success' ? 'erp-row-action-btn--success' : '') }}"
                title="{{ $label }}"
                aria-label="{{ $label }}"
                @foreach($attrs as $key => $value) {{ $key }}="{{ $value }}" @endforeach
            >
                <x-erp.ui.action-icon :name="$icon" />
            </button>
        @else
            <a
                href="{{ $action['url'] ?? '#' }}"
                class="erp-row-action-btn {{ $tone === 'danger' ? 'erp-row-action-btn--danger' : ($tone === 'success' ? 'erp-row-action-btn--success' : '') }}"
                title="{{ $label }}"
                aria-label="{{ $label }}"
            >
                <x-erp.ui.action-icon :name="$icon" />
            </a>
        @endif
    @endforeach
    {{ $slot ?? '' }}
</x-erp.ui.row-actions>
