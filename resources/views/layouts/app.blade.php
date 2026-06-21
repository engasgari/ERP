<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts -->
{{--        <link rel="preconnect" href="https://fonts.bunny.net">--}}
{{--        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />--}}
        <!-- لینک فونت وزیر از CDN -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
        <link rel="stylesheet" href="{{ asset('css/erp-ui.css') }}">
        @livewireStyles

        <!-- Scripts -->
        @vite(['resources/css/app.css'])
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    </head>
    <body class="font-sans antialiased">
        <div class="erp-shell min-h-screen bg-slate-100 text-slate-900">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="border-b border-slate-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-4 sm:px-5 lg:px-6">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="mx-auto max-w-7xl px-3 py-4 sm:px-5 lg:px-6">
                {{ $slot }}
            </main>

            @if(session('success') || session('error'))
                <div class="erp-flash-overlay" data-erp-flash>
                    <div class="mx-auto max-w-2xl px-3 py-4 sm:px-5 lg:px-6">
                        <x-erp.ui.alert
                            :tone="session('error') ? 'danger' : 'success'"
                            :title="session('error') ? 'خطا' : 'انجام شد'"
                            :message="session('error') ?: session('success')"
                            :details="session('error') ? session('error_details') : []"
                            class="relative"
                        />
                    </div>
                </div>
            @endif
        </div>
        @livewireScripts
        <script src="{{ asset('js/erp-ui.js') }}"></script>
    </body>
</html>
