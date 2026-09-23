@props([
    'name' => null,
    'value' => '',
    'items' => [],
    'optionsSource' => null,
    'placeholder' => 'جستجو نام، کد یا دسته...',
    'inputClass' => '',
    'required' => false,
])

<x-erp.ui.search-select
    :name="$name"
    :value="$value"
    :placeholder="$placeholder"
    :inputClass="$inputClass"
    :optionsSource="$optionsSource"
    :options="$optionsSource ? [] : itemSearchSelectOptions($items)"
    :required="$required"
    {{ $attributes }}
/>
