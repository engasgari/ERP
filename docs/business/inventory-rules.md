# Inventory Rules

## Responsibilities

Stock

Warehouse

Inventory Documents

Inventory Count

Cost

---

## Quantity

Inventory owns Quantity.

---

## Value

Accounting owns Value.

---

## Stock

Calculated.

Never manually edited.

---

## Inventory Documents

Receipt

Issue

Transfer

Adjustment

Production Receipt

Production Issue

---

## Costing

Current

Moving Average

Future

FIFO

Standard Cost

---

## Permissions

- `inventory.view`
- `inventory.manage`
- `inventory.documents.create`
- `inventory.documents.update`
- `inventory.documents.delete`
- `inventory.documents.confirm`
- `warehouse.view`
- `warehouse.manage`
- `warehouse.create`
- `warehouse.update`
- `warehouse.delete`

Having `inventory.manage` implies the granular document and warehouse action permissions above.

Having `warehouse.manage` implies `warehouse.create`, `warehouse.update`, and `warehouse.delete`.

---

## Forbidden

Negative Stock

Delete Posted Document

Manual Quantity Update