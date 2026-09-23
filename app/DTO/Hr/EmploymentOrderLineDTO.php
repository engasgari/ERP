<?php

namespace App\DTO\Hr;

use App\Core\Base\BaseDTO;

final readonly class EmploymentOrderLineDTO extends BaseDTO
{
    public function __construct(
        public ?int $id,
        public ?int $salaryItemId,
        public string $code,
        public string $title,
        public string $type,
        public float $amount,
        public bool $isInsurable,
        public bool $isTaxable,
        public bool $isEditable,
        public bool $isRemovable,
        public int $sortOrder = 0,
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            salaryItemId: isset($row['salary_item_id']) && $row['salary_item_id'] !== '' ? (int) $row['salary_item_id'] : null,
            code: (string) ($row['code'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            type: (string) ($row['type'] ?? 'earning'),
            amount: (float) ($row['amount'] ?? 0),
            isInsurable: (bool) ($row['is_insurable'] ?? false),
            isTaxable: (bool) ($row['is_taxable'] ?? false),
            isEditable: (bool) ($row['is_editable'] ?? true),
            isRemovable: (bool) ($row['is_removable'] ?? true),
            sortOrder: (int) ($row['sort_order'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'salary_item_id' => $this->salaryItemId,
            'code' => $this->code,
            'title' => $this->title,
            'type' => $this->type,
            'amount' => $this->amount,
            'is_insurable' => $this->isInsurable,
            'is_taxable' => $this->isTaxable,
            'is_editable' => $this->isEditable,
            'is_removable' => $this->isRemovable,
            'sort_order' => $this->sortOrder,
        ];
    }
}
