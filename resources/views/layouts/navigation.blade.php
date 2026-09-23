@if (request()->is('crm*'))
    @include('layouts.partials.crm-navigation')
@else
    @include('layouts.partials.erp-navigation')
@endif
