<?php

namespace App\Support;

final class ProjectPermissions
{
    public const VIEW = 'projects.view';

    public const MANAGE = 'projects.manage';

    public const CREATE = 'projects.create';

    public const UPDATE = 'projects.update';

    public const DELETE = 'projects.delete';

    /**
     * @return list<array{key:string,title:string,group:string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => self::VIEW, 'title' => 'مشاهده پروژه‌ها', 'group' => 'پروژه‌ها'],
            ['key' => self::MANAGE, 'title' => 'مدیریت کامل پروژه‌ها', 'group' => 'پروژه‌ها'],
            ['key' => self::CREATE, 'title' => 'تعریف پروژه', 'group' => 'پروژه‌ها'],
            ['key' => self::UPDATE, 'title' => 'ویرایش پروژه', 'group' => 'پروژه‌ها'],
            ['key' => self::DELETE, 'title' => 'حذف پروژه', 'group' => 'پروژه‌ها'],
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
