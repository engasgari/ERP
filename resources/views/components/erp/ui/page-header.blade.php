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
                <a href="{{ $action['url'] ?? '#' }}" class="erp-action-btn {{ $action['class'] ?? 'erp-action-detail' }} text-center">{{ $action['label'] }}</a>
            @endforeach
        </div>
    @endif
    {{ $slot ?? '' }}
</div>
