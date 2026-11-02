<?php

namespace App\Support;

final class PayrollPermissions
{
    public const GROUP = 'حقوق و دستمزد';

    /** @deprecated Use VIEW; kept for employee-scoped access compatibility. */
    public const SALARIES_VIEW = 'salaries.view';

    /** @deprecated Use MANAGE; kept for employee-scoped access compatibility. */
    public const SALARIES_MANAGE = 'salaries.manage';

    public const VIEW = 'payroll.view';

    public const MANAGE = 'payroll.manage';

    public const PERIODS_CREATE = 'payroll.periods.create';

    public const CALCULATE = 'payroll.calculate';

    public const APPROVE = 'payroll.approve';

    public const POST = 'payroll.post';

    public const PERIODS_DELETE = 'payroll.periods.delete';

    public const PERIODS_CLOSE = 'payroll.periods.close';

    public const ADJUST = 'payroll.adjust';

    public const SETTINGS = 'payroll.settings.manage';

    public const INSURANCE_VIEW = 'insurance.view';

    public const INSURANCE_MANAGE = 'insurance.manage';

    public const INSURANCE_PAYMENT = 'insurance.payment';

    /**
     * Permissions shown in role UI (single payroll group).
     *
     * @return list<array{key:string,title:string,group:string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => self::VIEW, 'title' => 'مشاهده حقوق و دستمزد', 'group' => self::GROUP],
            ['key' => self::MANAGE, 'title' => 'مدیریت کامل حقوق و دستمزد', 'group' => self::GROUP],
            ['key' => self::PERIODS_CREATE, 'title' => 'ایجاد دوره حقوق', 'group' => self::GROUP],
            ['key' => self::CALCULATE, 'title' => 'محاسبه حقوق', 'group' => self::GROUP],
            ['key' => self::APPROVE, 'title' => 'تایید حقوق', 'group' => self::GROUP],
            ['key' => self::POST, 'title' => 'صدور سند حقوق', 'group' => self::GROUP],
            ['key' => self::PERIODS_DELETE, 'title' => 'حذف دوره حقوق', 'group' => self::GROUP],
            ['key' => self::PERIODS_CLOSE, 'title' => 'بستن / بازگشایی دوره حقوق', 'group' => self::GROUP],
            ['key' => self::ADJUST, 'title' => 'ویرایش اقلام و پرداخت حقوق', 'group' => self::GROUP],
            ['key' => self::SETTINGS, 'title' => 'تنظیمات حسابداری حقوق', 'group' => self::GROUP],
            ['key' => self::INSURANCE_VIEW, 'title' => 'مشاهده بیمه تأمین اجتماعی', 'group' => self::GROUP],
            ['key' => self::INSURANCE_MANAGE, 'title' => 'مدیریت بدهی بیمه', 'group' => self::GROUP],
            ['key' => self::INSURANCE_PAYMENT, 'title' => 'ثبت پرداخت بیمه', 'group' => self::GROUP],
        ];
    }

    /**
     * Legacy keys kept in DB for employee access scopes / old role grants.
     *
     * @return list<array{key:string,title:string,group:string}>
     */
    public static function legacyDefinitions(): array
    {
        return [
            ['key' => self::SALARIES_VIEW, 'title' => 'مشاهده حقوق (سازگاری)', 'group' => self::GROUP],
            ['key' => self::SALARIES_MANAGE, 'title' => 'مدیریت حقوق (سازگاری)', 'group' => self::GROUP],
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
     * Action keys implied by payroll.manage or salaries.manage.
     *
     * @return list<string>
     */
    public static function manageImpliedKeys(): array
    {
        return [
            self::VIEW,
            self::PERIODS_CREATE,
            self::CALCULATE,
            self::APPROVE,
            self::POST,
            self::PERIODS_DELETE,
            self::PERIODS_CLOSE,
            self::ADJUST,
            self::SETTINGS,
            self::INSURANCE_VIEW,
            self::INSURANCE_MANAGE,
            self::INSURANCE_PAYMENT,
            self::SALARIES_VIEW,
        ];
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_column([...self::definitions(), ...self::legacyDefinitions()], 'key');
    }

    /**
     * @return list<string>
     */
    public static function uiKeys(): array
    {
        return array_column(self::definitions(), 'key');
    }
}
