@props(['label', 'emphasis' => false, 'ltr' => false])

<div @class(['crm-list-card-field', 'crm-list-card-field--emphasis' => $emphasis])>
    <dt>{{ $label }}</dt>
    <dd @class(['crm-list-card-field__value', 'crm-list-card-field__value--ltr' => $ltr])>{{ $slot }}</dd>
</div>
