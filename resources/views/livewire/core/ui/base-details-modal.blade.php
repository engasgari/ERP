<x-erp.ui.details-modal :title="$title" :open="$open" :actions="$actions">
    <div class="erp-modal-grid">
        @foreach($fields as $label => $value)
            <div class="erp-modal-field">{{ $label }}<div class="erp-modal-value">{{ $value ?: '-' }}</div></div>
        @endforeach
    </div>
</x-erp.ui.details-modal>
