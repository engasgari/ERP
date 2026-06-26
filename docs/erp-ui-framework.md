# راهنمای فارسی ERP UI Framework

این چارچوب منبع واحد UI در ERP است. هر صفحه جدید یا صفحه مهاجرت‌شده باید از `app/Livewire/Core/UI` و کامپوننت‌های Blade مسیر `resources/views/components/erp/ui` استفاده کند.

## قانون‌های اصلی

- همه متن‌های قابل مشاهده برای کاربر باید فارسی و راست‌چین باشند.
- هیچ ماژولی نباید جدول، فیلتر، مودال، badge، action button یا print layout اختصاصی بسازد.
- لیست‌ها باید این ترتیب را داشته باشند: عنوان صفحه، فیلتر، اکشن‌های گروهی، جدول، pagination.
- کلیک روی ردیف یا دکمه «جزئیات» باید از modal مشترک استفاده کند؛ navigation فقط برای workflowهای کامل مثل فرم ایجاد/ویرایش مجاز است.
- گزارش‌ها باید layout چاپ اختصاصی داشته باشند و نباید table صفحه اپلیکیشن را مستقیماً چاپ کنند.

## کلاس‌های پایه

- `BaseListPage`: پایه همه لیست‌های Livewire، pagination، selected rows و details modal.
- `BaseFormPage`: پایه فرم‌های ایجاد/ویرایش با ذخیره و پیام موفقیت فارسی.
- `BaseReportPage`: پایه گزارش‌های Livewire با فیلتر و pagination.
- `BaseDetailsModal`: مودال استاندارد جزئیات.
- `BaseFilterBar`: فیلتر استاندارد.
- `BaseDataTable`: جدول فشرده استاندارد.
- `BaseBulkActions`: اکشن‌های گروهی.
- `BaseApprovalActions`: تایید، رد و لغو تایید.
- `BasePrintLayout`: چاپ و PDF.
- `BaseReportViewer`: نمایش گزارش.
- `BasePageHeader`, `BaseEmptyState`, `BaseStatusBadge`, `BaseActionMenu`: اجزای مشترک صفحه.

## نمونه لیست

```blade
<x-erp.ui.page-shell title="لیست کالاها">
    <x-erp.ui.panel>
        <x-erp.ui.page-header title="لیست کالاها" :actions="$actions" />
        <x-erp.ui.filter-bar>
            <div class="erp-filter-row">
                <label class="erp-filter-field">جستجو
                    <input wire:model.live.debounce.400ms="search">
                </label>
            </div>
        </x-erp.ui.filter-bar>
        <x-erp.ui.data-table :headers="['کد', 'عنوان', 'وضعیت', 'عملیات']">
            {{-- rows --}}
        </x-erp.ui.data-table>
    </x-erp.ui.panel>
</x-erp.ui.page-shell>
```

## ترتیب مهاجرت ماژول‌ها

1. گزارش‌ها و Kardex
2. لیست‌های Livewire CRUD
3. منابع انسانی، حضور و غیاب، حقوق
4. انبار، حسابداری، پروژه‌ها
5. فروش، خرید، CRM، تنظیمات

بعد از مهاجرت هر ماژول، markup اختصاصی جدول/فیلتر/مودال باید حذف شود.
