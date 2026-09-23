@php
    use App\Support\NavigationMenu;

    $navLinks = NavigationMenu::forUser(auth()->user());
    $brandRoute = route('dashboard');
    $brandMark = 'ERP';
    $brandTitle = 'مدیریت ERP';
@endphp

@include('layouts.partials.top-navigation-inner')
