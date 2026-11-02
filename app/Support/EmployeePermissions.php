<?php

namespace App\Support;

final class EmployeePermissions
{
    public const VIEW = 'employees.view';

    public const MANAGE = 'employees.manage';

    public const CREATE = 'employees.create';

    public const UPDATE = 'employees.update';

    public const DELETE = 'employees.delete';

    /**
     * @return list<array{key:string,title:string,group:string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => self::VIEW, 'title' => 'مشاهده پرسنل', 'group' => 'پرسنل'],
            ['key' => self::MANAGE, 'title' => 'مدیریت کامل پرسنل', 'group' => 'پرسنل'],
            ['key' => self::CREATE, 'title' => 'ثبت پرسنل', 'group' => 'پرسنل'],
            ['key' => self::UPDATE, 'title' => 'ویرایش پرسنل', 'group' => 'پرسنل'],
            ['key' => self::DELETE, 'title' => 'حذف پرسنل', 'group' => 'پرسنل'],
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
        return [self::CREATE, self::UPDATE, self::DELETE];
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_column(self::definitions(), 'key');
    }
}
