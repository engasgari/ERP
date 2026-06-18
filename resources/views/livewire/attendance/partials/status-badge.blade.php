@php
    $labels = [
        'draft' => 'پیش‌نویس',
        'pending' => 'در انتظار تایید',
        'approved' => 'تایید شده',
        'rejected' => 'رد شده',
        'cancelled' => 'لغو شده',
    ];

    $classes = [
        'draft' => 'bg-gray-100 text-gray-700',
        'pending' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-green-100 text-green-700',
        'rejected' => 'bg-red-100 text-red-700',
        'cancelled' => 'bg-slate-100 text-slate-700',
    ];
@endphp

<span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $classes[$status] ?? 'bg-gray-100 text-gray-700' }}">
    {{ $labels[$status] ?? $status }}
</span>
