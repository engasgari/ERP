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