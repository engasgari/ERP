@php($fixedType = $fixedType ?? null)

<div class="erp-filter-row">
    @if($fixedType)
        <input type="hidden" wire:model="activity_type">
        <div class="erp-filter-field">
            <span class="block text-sm font-semibold text-slate-600 mb-1">نوع</span>
            <span class="text-slate-900 font-bold">{{ $typeOptions[$fixedType] ?? $fixedType }}</span>
        </div>
    @else
        <label class="erp-filter-field">نوع
            <select wire:model="activity_type">
                @foreach($typeOptions as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    @endif
    <label class="erp-filter-field md:col-span-2">موضوع
        <input wire:model="activity_subject" placeholder="موضوع مطرح‌شده">
        @error('activity_subject') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field md:col-span-2">خلاصه / توضیحات
        <textarea wire:model="activity_description" rows="3" placeholder="خلاصه تماس یا جلسه"></textarea>
    </label>
    <label class="erp-filter-field">وضعیت
        <select wire:model="activity_status">
            <option value="completed">انجام شده</option>
            <option value="planned">برنامه‌ریزی شده (پیگیری بعدی)</option>
        </select>
    </label>
    <label class="erp-filter-field">موعد
        <x-erp.ui.jalali-datetime-input wire:model="activity_due_at" placeholder="1405/01/01 14:30" />
        @error('activity_due_at') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">تاریخ انجام
        <x-erp.ui.jalali-datetime-input wire:model="activity_completed_at" placeholder="1405/01/01 14:30" />
        @error('activity_completed_at') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
</div>
