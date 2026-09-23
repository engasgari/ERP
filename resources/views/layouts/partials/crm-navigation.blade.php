@php
    use App\Support\CrmNavigationMenu;

    $navLinks = CrmNavigationMenu::forUser(auth()->user());
    $brandRoute = route('crm.dashboard');
    $brandMark = 'CRM';
    $brandTitle = 'مدیریت CRM';
@endphp

@include('layouts.partials.crm-top-navigation')
