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
                    <div class="erp-flash-card {{ session('error') ? 'is-error' : 'is-success' }}">
                        <button type="button" class="erp-flash-close" data-erp-flash-close>×</button>
                        <div class="erp-flash-icon">{{ session('error') ? '!' : '✓' }}</div>
                        <div>
                            <div class="erp-flash-title">{{ session('error') ? 'خطا' : 'انجام شد' }}</div>
                            <div class="erp-flash-message">{{ session('error') ?: session('success') }}</div>
                            @if(session('error') && session('error_details'))
                                <ul class="erp-flash-details">
                                    @foreach((array) session('error_details') as $detail)
                                        <li>{{ $detail }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        </div>
        @livewireScripts
        <script src="{{ asset('js/erp-ui.js') }}"></script>
    </body>
</html>
