<div class="erp-filter-row">
    <label class="erp-filter-field md:col-span-2">عنوان
        <input wire:model="task_title" placeholder="مثلاً پیگیری پیشنهاد">
        @error('task_title') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field md:col-span-2">توضیحات
        <textarea wire:model="task_description" rows="2"></textarea>
    </label>
    <label class="erp-filter-field">مسئول
        <select wire:model="task_assigned_user_id">
            @foreach($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </select>
        @error('task_assigned_user_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">اولویت
        <select wire:model="task_priority">
            @foreach($priorityOptions as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label class="erp-filter-field">وضعیت
        <select wire:model="task_status">
            @foreach($statusOptions as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>
        @error('task_status') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">موعد
        <x-erp.ui.jalali-datetime-input wire:model="task_due_at" placeholder="1405/01/01 14:30" />
        @error('task_due_at') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
</div>
