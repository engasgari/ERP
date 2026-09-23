<div class="erp-filter-row">
    <label class="erp-filter-field md:col-span-2">عنوان
        <input wire:model="form_title">
        @error('form_title') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">مشتری
        <span class="erp-field-label-row">
            <select wire:model.live="form_party_id" class="erp-field-control-grow">
                <option value="">انتخاب کنید</option>
                @foreach($parties as $party)
                    <option value="{{ $party->id }}">{{ $party->name }}</option>
                @endforeach
            </select>
            <button
                type="button"
                class="erp-field-add-btn"
                wire:click="openQuickCustomerModal"
                title="مشتری جدید"
                aria-label="مشتری جدید"
            >+</button>
        </span>
        @error('form_party_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">مخاطب
        <span class="erp-field-label-row">
            <select wire:model="form_contact_id" class="erp-field-control-grow" @disabled($form_party_id === '')>
                <option value="">بدون مخاطب</option>
                @foreach($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->full_name }}</option>
                @endforeach
            </select>
            <button
                type="button"
                class="erp-field-add-btn"
                wire:click="openQuickContactModal"
                title="مخاطب جدید"
                aria-label="مخاطب جدید"
                @disabled($form_party_id === '')
            >+</button>
        </span>
    </label>
    <label class="erp-filter-field">مسئول
        <select wire:model="form_assigned_user_id">
            @foreach($users as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
            @endforeach
        </select>
        @error('form_assigned_user_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">پایپ‌لاین
        <select wire:model.live="form_pipeline_id" @disabled($editingOpportunity && $editingOpportunity->status !== 'open')>
            <option value="">انتخاب کنید</option>
            @foreach($pipelines as $pipeline)
                <option value="{{ $pipeline->id }}">{{ $pipeline->name }}</option>
            @endforeach
        </select>
        @error('form_pipeline_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">مرحله
        <select wire:model="form_stage_id" @disabled(($editingOpportunity && $editingOpportunity->status !== 'open') || $form_pipeline_id === '')>
            <option value="">انتخاب کنید</option>
            @foreach($stages as $stage)
                <option value="{{ $stage->id }}">{{ $stage->name }} ({{ number_format((float) $stage->probability, 0) }}%)</option>
            @endforeach
        </select>
        @error('form_stage_id') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">مبلغ (ریال)
        <x-erp.ui.money-input wire:model="form_amount" :decimals="0" class="w-full" />
        @error('form_amount') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">احتمال (%)
        <input wire:model="form_probability" data-erp-number="1" inputmode="decimal" dir="ltr" placeholder="خالی = احتمال مرحله" class="tabular-nums">
        @error('form_probability') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">تاریخ پیش‌بینی بسته‌شدن
        <x-erp.ui.jalali-date-input wire:model="form_expected_close_date" placeholder="1405/01/01" />
        @error('form_expected_close_date') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    <label class="erp-filter-field">منبع
        <select wire:model="form_source_id">
            <option value="">بدون منبع</option>
            @foreach($sources as $source)
                <option value="{{ $source->id }}">{{ $source->title }}</option>
            @endforeach
        </select>
    </label>
    <label class="erp-filter-field md:col-span-2">توضیحات / دلیل ثبت فرصت
        <textarea wire:model="form_description" rows="3" placeholder="چرا این فرصت ثبت می‌شود؟ چه نیازی مطرح شده (لیست قیمت، پشتیبانی، تمدید قرارداد و ...)"></textarea>
        @error('form_description') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
    </label>
    @if($editingOpportunity && $editingOpportunity->status !== 'open')
        <p class="md:col-span-2 text-sm text-amber-700">این فرصت {{ $editingOpportunity->status === 'won' ? 'برنده' : 'بسته' }} شده است؛ فقط اطلاعات پایه قابل ویرایش است.</p>
    @endif
</div>
