<?php

namespace App\Support;

final class InventoryPermissions
{
    public const VIEW = 'inventory.view';

    public const MANAGE = 'inventory.manage';

    public const DOCUMENTS_CREATE = 'inventory.documents.create';

    public const DOCUMENTS_UPDATE = 'inventory.documents.update';

    public const DOCUMENTS_DELETE = 'inventory.documents.delete';

    public const DOCUMENTS_CONFIRM = 'inventory.documents.confirm';

    public const WAREHOUSE_VIEW = 'warehouse.view';

    public const WAREHOUSE_MANAGE = 'warehouse.manage';

    public const WAREHOUSE_CREATE = 'warehouse.create';

    public const WAREHOUSE_UPDATE = 'warehouse.update';

    public const WAREHOUSE_DELETE = 'warehouse.delete';

    /**
     * @return list<array{key:string,title:string,group:string}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => self::VIEW, 'title' => 'مشاهده انبار', 'group' => 'انبار'],
            ['key' => self::MANAGE, 'title' => 'مدیریت کامل انبار', 'group' => 'انبار'],
            ['key' => self::DOCUMENTS_CREATE, 'title' => 'صدور سند انبار', 'group' => 'انبار'],
            ['key' => self::DOCUMENTS_UPDATE, 'title' => 'ویرایش سند انبار', 'group' => 'انبار'],
            ['key' => self::DOCUMENTS_DELETE, 'title' => 'حذف سند انبار', 'group' => 'انبار'],
            ['key' => self::DOCUMENTS_CONFIRM, 'title' => 'تایید سند انبار و صدور حسابداری', 'group' => 'انبار'],
            ['key' => self::WAREHOUSE_VIEW, 'title' => 'مشاهده تعریف انبارها', 'group' => 'انبار'],
            ['key' => self::WAREHOUSE_MANAGE, 'title' => 'مدیریت کامل تعریف انبارها', 'group' => 'انبار'],
            ['key' => self::WAREHOUSE_CREATE, 'title' => 'تعریف انبار جدید', 'group' => 'انبار'],
            ['key' => self::WAREHOUSE_UPDATE, 'title' => 'ویرایش انبار', 'group' => 'انبار'],
            ['key' => self::WAREHOUSE_DELETE, 'title' => 'حذف انبار', 'group' => 'انبار'],
        ];
    }

    /**
     * Top-level inventory permissions shown before granular actions.
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
     * Action permissions implied by legacy inventory.manage.
     *
     * @return list<string>
     */
    public static function manageImpliedKeys(): array
    {
        return [
            self::DOCUMENTS_CREATE,
            self::DOCUMENTS_UPDATE,
            self::DOCUMENTS_DELETE,
            self::DOCUMENTS_CONFIRM,
            self::WAREHOUSE_VIEW,
            self::WAREHOUSE_CREATE,
            self::WAREHOUSE_UPDATE,
            self::WAREHOUSE_DELETE,
        ];
    }

    /**
     * Action permissions implied by legacy warehouse.manage.
     *
     * @return list<string>
     */
    public static function warehouseManageImpliedKeys(): array
    {
        return [
            self::WAREHOUSE_CREATE,
            self::WAREHOUSE_UPDATE,
            self::WAREHOUSE_DELETE,
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
