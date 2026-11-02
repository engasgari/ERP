<?php

namespace App\Support;

final class WorklogPermissions
{
    public const GROUP = 'کارکرد';

    public const VIEW = 'worklogs.view';

    public const MANAGE = 'worklogs.manage';

    public const CREATE = 'worklogs.create';

    public const UPDATE = 'worklogs.update';

    public const DELETE = 'worklogs.delete';

    public const SETUP = 'worklogs.setup.manage';

    /**
     * @return list<array{key:string,title:string,group:string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => self::VIEW, 'title' => 'مشاهده کارکرد', 'group' => self::GROUP],
            ['key' => self::MANAGE, 'title' => 'مدیریت کامل کارکرد', 'group' => self::GROUP],
            ['key' => self::CREATE, 'title' => 'ثبت کارکرد', 'group' => self::GROUP],
            ['key' => self::UPDATE, 'title' => 'ویرایش کارکرد', 'group' => self::GROUP],
            ['key' => self::DELETE, 'title' => 'حذف کارکرد', 'group' => self::GROUP],
            ['key' => self::SETUP, 'title' => 'مدیریت شیفت، تقویم و گروه کاری', 'group' => self::GROUP],
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
        return [self::CREATE, self::UPDATE, self::DELETE, self::SETUP];
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_column(self::definitions(), 'key');
    }
}
