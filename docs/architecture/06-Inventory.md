# Inventory

## Responsibilities

- Warehouses
- Inventory Documents
- Stock
- Transfers
- Stock Count
- Cost Calculation

---

## Rules

Inventory owns quantity.

Accounting owns value.

Inventory never edits accounting.

Accounting never edits stock.

Permissions are granular under `inventory.documents.*` and `warehouse.*`.

`inventory.manage` still implies document and warehouse action permissions for backward compatibility.

`warehouse.manage` still implies `warehouse.create`, `warehouse.update`, and `warehouse.delete`.

---

## Inventory Documents

- Receipt
- Issue
- Transfer
- Adjustment
- Production Receipt
- Production Issue

---

## Costing

Supported

- Moving Average

Future

- FIFO
- Standard Cost

---

## Flow

Inventory Document

↓

Inventory Service

↓

Update Stock

↓

Accounting Engine