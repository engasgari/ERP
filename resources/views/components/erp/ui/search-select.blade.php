@props([
    'name' => null,
    'value' => '',
    'placeholder' => 'جستجو یا انتخاب...',
    'inputClass' => '',
    'hiddenClass' => '',
    'optionsSource' => null,
    'options' => [],
    'required' => false,
])

@php
    $inlineOptions = $optionsSource ? [] : $options;
    $wireAttributes = collect($attributes->getAttributes())
        ->filter(static fn ($value, $key) => str_starts_with((string) $key, 'wire:'))
        ->all();
    $wrapperAttributes = $attributes
        ->except(array_keys($wireAttributes))
        ->class(['erp-search-select']);
@endphp

<div
    {{ $wrapperAttributes }}
    @if($wireAttributes !== []) wire:ignore.self @endif
    data-erp-search-select
    @if($optionsSource) data-options-source="{{ $optionsSource }}" @endif
    data-options="@json($inlineOptions)"
>
    <input
        type="hidden"
        @if($name && $wireAttributes === []) name="{{ $name }}" @endif
        value="{{ $value }}"
        @if($hiddenClass) class="{{ $hiddenClass }}" @endif
        @if($required) required @endif
        @foreach($wireAttributes as $attributeName => $attributeValue)
            {{ $attributeName }}="{{ $attributeValue }}"
        @endforeach
    >
    <input
        type="text"
        class="erp-search-select__input {{ $inputClass }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        spellcheck="false"
    >
    <div class="erp-search-select__list" hidden></div>
</div>
