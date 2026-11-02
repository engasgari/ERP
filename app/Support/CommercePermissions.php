<?php

namespace App\Support;

final class CommercePermissions
{
    public const VIEW = 'commerce.view';

    public const MANAGE = 'commerce.manage';

    public const INVOICES_CREATE = 'commerce.invoices.create';

    public const INVOICES_UPDATE = 'commerce.invoices.update';

    public const INVOICES_DELETE = 'commerce.invoices.delete';

    public const INVOICES_CANCEL = 'commerce.invoices.cancel';

    public const INVOICES_CONFIRM = 'commerce.invoices.confirm';

    public const INVOICES_SETTLE = 'commerce.invoices.settle';

    public const INVOICES_CONVERT = 'commerce.invoices.convert';

    public const PRICING_MANAGE = 'commerce.pricing.manage';

    /**
     * @return list<array{key:string,title:string,group:string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => self::VIEW, 'title' => 'مشاهده بازرگانی', 'group' => 'بازرگانی'],
            ['key' => self::MANAGE, 'title' => 'مدیریت کامل بازرگانی', 'group' => 'بازرگانی'],
            ['key' => self::INVOICES_CREATE, 'title' => 'صدور فاکتور / پیش‌فاکتور / برگشت', 'group' => 'بازرگانی'],
            ['key' => self::INVOICES_UPDATE, 'title' => 'ویرایش فاکتور', 'group' => 'بازرگانی'],
            ['key' => self::INVOICES_DELETE, 'title' => 'حذف فاکتور', 'group' => 'بازرگانی'],
            ['key' => self::INVOICES_CANCEL, 'title' => 'ابطال فاکتور', 'group' => 'بازرگانی'],
            ['key' => self::INVOICES_CONFIRM, 'title' => 'تایید فاکتور و صدور سند', 'group' => 'بازرگانی'],
            ['key' => self::INVOICES_SETTLE, 'title' => 'تسویه / خروج از تسویه فاکتور', 'group' => 'بازرگانی'],
            ['key' => self::INVOICES_CONVERT, 'title' => 'تبدیل پیش‌فاکتور به فاکتور', 'group' => 'بازرگانی'],
            ['key' => self::PRICING_MANAGE, 'title' => 'قیمت‌گذاری کالا و خدمات', 'group' => 'بازرگانی'],
        ];
    }

    /**
     * Top-level commerce permissions shown before granular actions.
     *
     * @return list<string>
     */
    public static function primaryKeys(): array
    {
        return [
            self::VIEW,
            self::MANAGE,
        ];
    }

    /**
     * Action permissions implied by legacy commerce.manage.
     *
     * @return list<string>
     */
    public static function manageImpliedKeys(): array
    {
        return [
            self::INVOICES_CREATE,
            self::INVOICES_UPDATE,
            self::INVOICES_DELETE,
            self::INVOICES_CANCEL,
            self::INVOICES_CONFIRM,
            self::INVOICES_SETTLE,
            self::INVOICES_CONVERT,
            self::PRICING_MANAGE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_column(self::definitions(), 'key');
    }
}
