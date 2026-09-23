<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ request()->is('crm*') ? 'CRM' : config('app.name', 'ERP') }}</title>

        <link rel="stylesheet" href="{{ asset('vendor/fonts/vazirmatn/vazirmatn-font-face.css') }}">
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
        <link rel="stylesheet" href="{{ asset('css/erp-ui.css') }}?v={{ @filemtime(public_path('css/erp-ui.css')) ?: 1 }}">
        @vite(['resources/css/app.css'])
        <script src="{{ asset('vendor/jquery/jquery-3.6.0.min.js') }}"></script>
        <script src="{{ asset('js/erp-ui.js') }}?v={{ @filemtime(public_path('js/erp-ui.js')) ?: 1 }}"></script>
    </head>
    <body class="font-sans antialiased bg-white text-slate-900">
        <div class="erp-shell invoice-embed-shell p-2 sm:p-3">
            @yield('content')
        </div>
    </body>
</html>
