@props([
    'route' => null,
    'trail' => null,
    'back' => null,
])
@php
    $breadcrumbService = app(\App\Services\NavigationBreadcrumbService::class);
    $routeName = $route ?: (Route::currentRouteName() ?? '');
    $trail = $trail ?? ($routeName !== '' ? $breadcrumbService->trail($routeName) : []);
    $back = $back ?? $breadcrumbService->backLink();
@endphp
@if(count($trail) > 0)
    <nav aria-label="breadcrumb" class="erp-breadcrumb">
        @if(is_array($back) && ! empty($back['url']))
            <a href="{{ $back['url'] }}" wire:navigate class="erp-breadcrumb-back">{{ $back['label'] ?? 'بازگشت' }}</a>
        @endif
        <ol class="erp-breadcrumb-list">
            @foreach($trail as $crumb)
                <li class="erp-breadcrumb-item {{ $loop->last ? 'is-active' : '' }}">
                    @if(! $loop->last)
                        @php
                            $href = $crumb['url'] ?? null;
                            if ($href === null && ! empty($crumb['route']) && Route::has($crumb['route'])) {
                                try {
                                    $href = route($crumb['route'], $crumb['routeParams'] ?? []);
                                } catch (\Illuminate\Routing\Exceptions\UrlGenerationException) {
                                    $href = null;
                                }
                            }
                        @endphp
                        @if($href)
                            <a href="{{ $href }}" wire:navigate>{{ $crumb['label'] }}</a>
                        @else
                            <span>{{ $crumb['label'] }}</span>
                        @endif
                    @else
                        <span>{{ $crumb['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
