@if($showQuickCustomerModal)
    <x-erp.ui.details-modal title="مشتری جدید" nested>
        <x-slot name="close">
            <button type="button" class="erp-modal-close" wire:click="closeQuickCustomerModal">×</button>
        </x-slot>
        <x-slot name="footer">
            <button type="button" class="erp-action-btn" wire:click="closeQuickCustomerModal">انصراف</button>
            <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveQuickCustomer">ثبت و انتخاب</button>
        </x-slot>
        <div class="erp-filter-row">
            <label class="erp-filter-field md:col-span-2">نام مشتری
                <input wire:model="quick_customer_name" placeholder="نام شرکت یا شخص">
                @error('quick_customer_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </label>
            <label class="erp-filter-field">نوع
                <select wire:model="quick_customer_kind">
                    <option value="company">حقوقی</option>
                    <option value="person">حقیقی</option>
                </select>
                @error('quick_customer_kind') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </label>
            <label class="erp-filter-field">موبایل
                <input wire:model="quick_customer_mobile" dir="ltr">
                @error('quick_customer_mobile') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </label>
            <label class="erp-filter-field">تلفن
                <input wire:model="quick_customer_phone" dir="ltr">
                @error('quick_customer_phone') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </label>
            <label class="erp-filter-field md:col-span-2">ایمیل
                <input wire:model="quick_customer_email" dir="ltr">
                @error('quick_customer_email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </label>
        </div>
    </x-erp.ui.details-modal>
@endif

@if($showQuickContactModal)
    <x-erp.ui.details-modal title="مخاطب جدید" nested>
        <x-slot name="close">
            <button type="button" class="erp-modal-close" wire:click="closeQuickContactModal">×</button>
        </x-slot>
        <x-slot name="footer">
            <button type="button" class="erp-action-btn" wire:click="closeQuickContactModal">انصراف</button>
            <button type="button" class="erp-action-btn erp-action-edit" wire:click="saveQuickContact">ثبت و انتخاب</button>
        </x-slot>
        <div class="erp-filter-row">
            @if($selectedParty)
                <p class="md:col-span-2 text-sm text-slate-600">مشتری: <strong>{{ $selectedParty->name }}</strong></p>
            @endif
            <label class="erp-filter-field">نام
                <input wire:model="quick_contact_first_name">
                @error('quick_contact_first_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </label>
            <label class="erp-filter-field">نام خانوادگی
                <input wire:model="quick_contact_last_name">
                @error('quick_contact_last_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </label>
            <label class="erp-filter-field">موبایل
                <input wire:model="quick_contact_mobile" dir="ltr">
                @error('quick_contact_mobile') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </label>
            <label class="erp-filter-field">ایمیل
                <input wire:model="quick_contact_email" dir="ltr">
                @error('quick_contact_email') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </label>
            <label class="erp-filter-field md:col-span-2">سمت
                <input wire:model="quick_contact_job_title">
                @error('quick_contact_job_title') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </label>
        </div>
    </x-erp.ui.details-modal>
@endif
