<x-erp.ui.list-page
    title="تقویم CRM"
    description="نمایش فعالیت‌ها و وظایف به تفکیک روز."
    route="crm.calendar.index"
>
    <x-slot name="filters">
        <x-erp.ui.filter-bar wire:submit.prevent>
            <div class="erp-filter-row items-end">
                <div class="erp-filter-actions flex gap-2">
                    <button type="button" wire:click="previousPeriod" class="erp-action-btn">قبلی</button>
                    <button type="button" wire:click="nextPeriod" class="erp-action-btn">بعدی</button>
                </div>
                <label class="erp-filter-field">نمای
                    <select wire:model.live="viewMode">
                        <option value="month">ماهانه</option>
                        <option value="week">هفتگی</option>
                    </select>
                </label>
                <div class="erp-filter-field font-semibold">{{ $periodLabel }}</div>
            </div>
        </x-erp.ui.filter-bar>
    </x-slot>

    <div class="space-y-4">
        @forelse($events as $date => $dayEvents)
            <div class="crm-calendar-day">
                <div class="crm-calendar-day__header">
                    {{ gregorianToJalaliDate($date) }}
                </div>
                <div class="crm-calendar-day__body">
                    @foreach($dayEvents as $event)
                        <div wire:key="{{ $event['id'] }}" class="crm-calendar-day__event">
                            <div>
                                <span @class([
                                    'crm-calendar-badge',
                                    'crm-calendar-badge--task' => $event['kind'] === 'task',
                                    'crm-calendar-badge--activity' => $event['kind'] !== 'task',
                                ])>
                                    {{ $event['kind'] === 'task' ? 'وظیفه' : 'فعالیت' }}
                                </span>
                                <span class="font-semibold mr-2">{{ $event['title'] }}</span>
                                @if($event['party'])
                                    <span class="text-slate-500">— {{ $event['party'] }}</span>
                                @endif
                            </div>
                            <div class="text-slate-500 shrink-0">{{ $event['at']->format('H:i') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <x-erp.ui.empty-state message="رویدادی در این بازه یافت نشد." />
        @endforelse
    </div>
</x-erp.ui.list-page>
