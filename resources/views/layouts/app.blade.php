<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ request()->is('crm*') ? 'CRM' : config('app.name', 'ERP') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

        <!-- Fonts -->
        <link rel="stylesheet" href="{{ asset('vendor/fonts/vazirmatn/vazirmatn-font-face.css') }}">
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
        <link rel="stylesheet" href="{{ asset('css/erp-ui.css') }}?v={{ @filemtime(public_path('css/erp-ui.css')) ?: 1 }}">
        @livewireStyles

        <!-- Scripts -->
        @vite(['resources/css/app.css'])
        <script src="{{ asset('vendor/jquery/jquery-3.6.0.min.js') }}"></script>
    </head>
    <body @class([
        'font-sans antialiased',
        request()->routeIs('crm.pipeline.index') ? 'crm-pipeline-view' : '',
    ])>
        <div @class([
            'erp-shell min-h-screen bg-slate-100 text-slate-900',
            request()->routeIs('crm.pipeline.index') ? 'crm-pipeline-shell' : '',
            request()->is('crm*') ? 'crm-app' : '',
        ])>
            @include('layouts.navigation')

            @if(request()->is('crm*') && ! ($hideBreadcrumb ?? false))
                <div class="erp-page-subbar">
                    <div @class([
                        'px-4 py-2 sm:px-5 lg:px-6',
                        request()->routeIs('crm.pipeline.index') ? 'w-full max-w-none' : 'mx-auto max-w-7xl',
                    ])>
                        @if(! empty($breadcrumbTrail))
                            <x-erp.ui.breadcrumb :trail="$breadcrumbTrail" :back="$breadcrumbBack ?? null" />
                        @else
                            <x-erp.ui.breadcrumb />
                        @endif
                    </div>
                </div>
            @elseif(isset($header))
                <header class="border-b border-slate-200 bg-white">
                    <div class="mx-auto max-w-7xl px-4 py-4 sm:px-5 lg:px-6">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main @class(request()->routeIs('crm.pipeline.index')
                ? 'crm-pipeline-main'
                : 'mx-auto max-w-7xl px-3 py-4 sm:px-5 lg:px-6')>
                @unless(request()->is('crm*') || ($hideBreadcrumb ?? false))
                    @if(! empty($breadcrumbTrail))
                        <x-erp.ui.breadcrumb :trail="$breadcrumbTrail" :back="$breadcrumbBack ?? null" />
                    @else
                        <x-erp.ui.breadcrumb />
                    @endif
                @endunless
                {{ $slot }}
            </main>
        </div>
        <div id="erp-toast-stack" class="erp-toast-stack"></div>
        @livewireScripts
        <script src="{{ asset('js/erp-ui.js') }}?v={{ @filemtime(public_path('js/erp-ui.js')) ?: 1 }}"></script>
    </body>
</html>
