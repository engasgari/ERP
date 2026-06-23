<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - مدیریت ERP</title>
    <link rel="stylesheet" href="{{ asset('vendor/fonts/vazirmatn/vazirmatn-font-face.css') }}">
    <link rel="stylesheet" href="{{ asset('css/erp-ui.css') }}">
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body class="erp-shell min-h-screen bg-slate-100 text-slate-900">
@include('layouts.navigation')

<main class="mx-auto max-w-7xl px-3 py-4 sm:px-5 lg:px-6">
    @yield('content')
</main>

@if(session('success') || session('error'))
    <div class="erp-flash-overlay" data-erp-flash>
        <div class="mx-auto max-w-2xl px-3 py-4 sm:px-5 lg:px-6">
            <x-erp.ui.alert
                :tone="session('error') ? 'danger' : 'success'"
                :title="session('error') ? 'خطا' : 'انجام شد'"
                :message="session('error') ?: session('success')"
                :details="session('error') ? session('error_details') : []"
            />
        </div>
    </div>
@endif

@livewireScripts
<script src="{{ asset('js/erp-ui.js') }}"></script>
</body>
</html>
