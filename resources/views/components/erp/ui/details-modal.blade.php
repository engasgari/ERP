@props(['title' => 'جزئیات', 'open' => true, 'actions' => [], 'id' => null, 'hidden' => false, 'staticBackdrop' => false])
@if($open)
    <div @if($id) id="{{ $id }}" @endif class="{{ $staticBackdrop ? 'erp-modal' : 'erp-ui-modal-backdrop' }}" @if($hidden) hidden @endif>
        @if($staticBackdrop)
            <div class="erp-ui-modal-backdrop" data-erp-modal-close></div>
        @endif
        <div class="erp-ui-modal-panel">
            <div class="erp-modal-header">
                <h3>{{ $title }}</h3>
                {{ $close ?? '' }}
            </div>
            <div class="erp-modal-body">
                {{ $slot }}
            </div>
            @if($actions || isset($footer))
                <div class="erp-modal-actions">
                    <x-erp.ui.action-menu :actions="$actions" />
                    {{ $footer ?? '' }}
                </div>
            @endif
        </div>
    </div>
@endif
