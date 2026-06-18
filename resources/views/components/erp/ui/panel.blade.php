@props(['class' => ''])
<div {{ $attributes->merge(['class' => 'bg-white rounded-lg shadow-md p-4 md:p-6 space-y-4 ' . $class]) }}>
    {{ $slot }}
</div>
