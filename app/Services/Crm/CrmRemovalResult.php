<?php

namespace App\Services\Crm;

class CrmRemovalResult
{
    public const ACTION_DELETED = 'deleted';

    public const ACTION_DEACTIVATED = 'deactivated';

    public function __construct(
        public readonly string $action,
    ) {}

    public function flashMessage(string $entity = 'مخاطب'): string
    {
        return match ($this->action) {
            self::ACTION_DELETED => "{$entity} حذف شد.",
            self::ACTION_DEACTIVATED => "{$entity} غیرفعال شد.",
            default => 'عملیات انجام شد.',
        };
    }
}
