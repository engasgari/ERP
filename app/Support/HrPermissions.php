<?php

namespace App\Support;

final class HrPermissions
{
    public const GROUP = 'منابع انسانی';

    public const VIEW = 'hr.view';

    public const MANAGE = 'hr.manage';

    public const ORGANIZATION = 'hr.organization.manage';

    public const DOCUMENTS = 'hr.documents.manage';

    public const ORDERS_VIEW = 'employment-orders.view';

    public const ORDERS_MANAGE = 'employment-orders.manage';

    public const ORDERS_CREATE = 'employment-orders.create';

    public const ORDERS_UPDATE = 'employment-orders.update';

    public const ORDERS_DELETE = 'employment-orders.delete';

    public const ORDERS_APPROVE = 'employment-orders.approve';

    public const CONTRACTS_VIEW = 'contracts.view';

    public const CONTRACTS_MANAGE = 'contracts.manage';

    public const CONTRACTS_CREATE = 'contracts.create';

    public const CONTRACTS_UPDATE = 'contracts.update';

    public const CONTRACTS_DELETE = 'contracts.delete';

    /**
     * @return list<array{key:string,title:string,group:string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => self::VIEW, 'title' => 'مشاهده منابع انسانی', 'group' => self::GROUP],
            ['key' => self::MANAGE, 'title' => 'مدیریت کامل منابع انسانی', 'group' => self::GROUP],
            ['key' => self::ORGANIZATION, 'title' => 'مدیریت ساختار سازمانی، مشاغل و پست‌ها', 'group' => self::GROUP],
            ['key' => self::DOCUMENTS, 'title' => 'مدیریت مدارک پرسنلی', 'group' => self::GROUP],
            ['key' => self::ORDERS_VIEW, 'title' => 'مشاهده احکام کارگزینی', 'group' => self::GROUP],
            ['key' => self::ORDERS_MANAGE, 'title' => 'مدیریت کامل احکام کارگزینی', 'group' => self::GROUP],
            ['key' => self::ORDERS_CREATE, 'title' => 'ثبت حکم کارگزینی', 'group' => self::GROUP],
            ['key' => self::ORDERS_UPDATE, 'title' => 'ویرایش حکم کارگزینی', 'group' => self::GROUP],
            ['key' => self::ORDERS_DELETE, 'title' => 'حذف حکم کارگزینی', 'group' => self::GROUP],
            ['key' => self::ORDERS_APPROVE, 'title' => 'تایید احکام کارگزینی', 'group' => self::GROUP],
            ['key' => self::CONTRACTS_VIEW, 'title' => 'مشاهده قراردادها', 'group' => self::GROUP],
            ['key' => self::CONTRACTS_MANAGE, 'title' => 'مدیریت کامل قراردادها', 'group' => self::GROUP],
            ['key' => self::CONTRACTS_CREATE, 'title' => 'ثبت قرارداد', 'group' => self::GROUP],
            ['key' => self::CONTRACTS_UPDATE, 'title' => 'ویرایش قرارداد', 'group' => self::GROUP],
            ['key' => self::CONTRACTS_DELETE, 'title' => 'حذف قرارداد', 'group' => self::GROUP],
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
            self::ORGANIZATION,
            self::DOCUMENTS,
            self::ORDERS_VIEW,
            self::ORDERS_CREATE,
            self::ORDERS_UPDATE,
            self::ORDERS_DELETE,
            self::ORDERS_APPROVE,
            self::CONTRACTS_VIEW,
            self::CONTRACTS_CREATE,
            self::CONTRACTS_UPDATE,
            self::CONTRACTS_DELETE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function ordersManageImpliedKeys(): array
    {
        return [self::ORDERS_CREATE, self::ORDERS_UPDATE, self::ORDERS_DELETE];
    }

    /**
     * @return list<string>
     */
    public static function contractsManageImpliedKeys(): array
    {
        return [self::CONTRACTS_CREATE, self::CONTRACTS_UPDATE, self::CONTRACTS_DELETE];
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_column(self::definitions(), 'key');
    }
}
