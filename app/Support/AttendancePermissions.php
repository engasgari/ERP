<?php

namespace App\Support;

final class AttendancePermissions
{
    public const VIEW = 'attendance.view';

    public const MANAGE = 'attendance.manage';

    public const IMPORT = 'attendance.import';

    public const CALCULATE = 'attendance.calculate';

    public const DAILY_EDIT = 'attendance.daily.edit';

    public const LEAVES_CREATE = 'attendance.leaves.create';

    public const LEAVES_APPROVE = 'attendance.leaves.approve';

    public const MISSIONS_CREATE = 'attendance.missions.create';

    public const MISSIONS_APPROVE = 'attendance.missions.approve';

    public const SUMMARIES_REBUILD = 'attendance.summaries.rebuild';

    /**
     * @return list<array{key:string,title:string,group:string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => self::VIEW, 'title' => 'مشاهده حضور و غیاب', 'group' => 'حضور و غیاب'],
            ['key' => self::MANAGE, 'title' => 'مدیریت کامل حضور و غیاب', 'group' => 'حضور و غیاب'],
            ['key' => self::IMPORT, 'title' => 'ورود اطلاعات تردد', 'group' => 'حضور و غیاب'],
            ['key' => self::CALCULATE, 'title' => 'محاسبه کارکرد ماهانه', 'group' => 'حضور و غیاب'],
            ['key' => self::DAILY_EDIT, 'title' => 'ویرایش خلاصه کارکرد روزانه', 'group' => 'حضور و غیاب'],
            ['key' => self::LEAVES_CREATE, 'title' => 'ثبت درخواست مرخصی', 'group' => 'حضور و غیاب'],
            ['key' => self::LEAVES_APPROVE, 'title' => 'تایید / رد مرخصی', 'group' => 'حضور و غیاب'],
            ['key' => self::MISSIONS_CREATE, 'title' => 'ثبت درخواست ماموریت', 'group' => 'حضور و غیاب'],
            ['key' => self::MISSIONS_APPROVE, 'title' => 'تایید / رد ماموریت', 'group' => 'حضور و غیاب'],
            ['key' => self::SUMMARIES_REBUILD, 'title' => 'بازسازی خلاصه کارکرد ماهانه', 'group' => 'حضور و غیاب'],
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
        return [
            self::IMPORT,
            self::CALCULATE,
            self::DAILY_EDIT,
            self::LEAVES_CREATE,
            self::LEAVES_APPROVE,
            self::MISSIONS_CREATE,
            self::MISSIONS_APPROVE,
            self::SUMMARIES_REBUILD,
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
