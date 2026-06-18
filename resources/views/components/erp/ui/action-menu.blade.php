@props(['actions' => []])
<div class="d-grid d-sm-flex gap-2">
    @foreach($actions as $action)
        @if(($action['type'] ?? 'link') === 'button')
            <button type="button"
                    @foreach(($action['attrs'] ?? []) as $key => $value) {{ $key }}="{{ $value }}" @endforeach
                    class="erp-action-btn {{ $action['class'] ?? 'erp-action-detail' }}">
                {{ $action['label'] }}
            </button>
        @else
            <a href="{{ $action['url'] ?? '#' }}" class="erp-action-btn {{ $action['class'] ?? 'erp-action-detail' }} text-center">{{ $action['label'] }}</a>
        @endif
    @endforeach
    {{ $slot ?? '' }}
</div>
