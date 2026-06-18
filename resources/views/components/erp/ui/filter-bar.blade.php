@props(['method' => 'GET', 'action' => null])
<form method="{{ $method }}" @if($action) action="{{ $action }}" @endif {{ $attributes->merge(['class' => 'erp-ui-filter-bar']) }}>
    {{ $slot }}
</form>
