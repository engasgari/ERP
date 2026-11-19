@props(['title', 'description' => '', 'actions' => []])
<div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
    <div>
        <h2 class="text-xl font-bold text-slate-900">{{ $title }}</h2>
        @if($description)
            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
        @endif
    </div>
    @if($actions)
        <div class="flex flex-col gap-2 sm:flex-row">
            @foreach($actions as $action)
                <a
                    href="{{ $action['url'] ?? '#' }}"
                    @if(! empty($action['data_no_spa'])) data-no-spa @endif
                    class="erp-action-btn {{ $action['class'] ?? 'erp-action-detail' }} inline-flex items-center justify-center gap-2 text-center"
                >
                    @if(! empty($action['icon']))
                        <x-erp.ui.action-icon :name="$action['icon']" class="h-4 w-4" />
                    @endif
                    <span>{{ $action['label'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
    {{ $slot ?? '' }}
</div>
