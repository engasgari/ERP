<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - مدیریت ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css">
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

@livewireScripts
<script src="{{ asset('js/erp-ui.js') }}"></script>
</body>
</html>
