@props([

    'name' => null,

    'value' => '',

    'items' => [],

    'placeholder' => 'جستجو یا انتخاب...',

    'inputClass' => '',

    'required' => false,

])



<x-erp.ui.search-select

    :name="$name"

    :value="$value"

    :placeholder="$placeholder"

    :inputClass="$inputClass"

    :options="searchSelectOptions($items)"

    :required="$required"

    data-erp-lookup-select

    {{ $attributes }}

/>

