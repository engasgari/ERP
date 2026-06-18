<x-erp.ui.filter-bar>
    @foreach($filters as $filter)
        <label class="erp-filter-field {{ $filter['class'] ?? '' }}">
            {{ $filter['label'] ?? '' }}
            @if(($filter['type'] ?? 'text') === 'select')
                <select wire:model.live="{{ $filter['model'] }}">
                    @foreach(($filter['options'] ?? []) as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            @else
                <input wire:model.live.debounce.400ms="{{ $filter['model'] }}" placeholder="{{ $filter['placeholder'] ?? '' }}">
            @endif
        </label>
    @endforeach
</x-erp.ui.filter-bar>
