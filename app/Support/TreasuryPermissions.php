<?php

namespace App\Support;

final class TreasuryPermissions
{
    public const GROUP = 'خزانه';

    public const VIEW = 'treasury.view';

    public const MANAGE = 'treasury.manage';

    public const CREATE = 'treasury.create';

    public const UPDATE = 'treasury.update';

    public const DELETE = 'treasury.delete';

    public const PARTNERS = 'treasury.partners.manage';

    /**
     * @return list<array{key:string,title:string,group:string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => self::VIEW, 'title' => 'مشاهده خزانه', 'group' => self::GROUP],
            ['key' => self::MANAGE, 'title' => 'مدیریت کامل خزانه', 'group' => self::GROUP],
            ['key' => self::CREATE, 'title' => 'ثبت تراکنش خزانه', 'group' => self::GROUP],
            ['key' => self::UPDATE, 'title' => 'ویرایش تراکنش خزانه', 'group' => self::GROUP],
            ['key' => self::DELETE, 'title' => 'حذف تراکنش خزانه', 'group' => self::GROUP],
            ['key' => self::PARTNERS, 'title' => 'مدیریت حساب‌های جاری شرکا', 'group' => self::GROUP],
        ];
    }

    /**
     * @return list<string>
     */
    public static function primaryKeys(): array
    {
        return [self::VIEW, self::MANAGE];
    }

    /**
     * @return list<string>
     */
    public static function manageImpliedKeys(): array
    {
        return [self::VIEW, self::CREATE, self::UPDATE, self::DELETE, self::PARTNERS];
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_column(self::definitions(), 'key');
    }
}
