# Commerce Rules

## Modules

Sales

Purchase

Returns

Price Lists (`commerce.item-pricing`)

Discounts

---

## Permissions

Keep `commerce.view` for list/print access and `commerce.manage` as full access.

Granular invoice actions (assign without giving delete if needed):

- `commerce.invoices.create`
- `commerce.invoices.update`
- `commerce.invoices.delete`
- `commerce.invoices.cancel`
- `commerce.invoices.confirm`
- `commerce.invoices.settle`
- `commerce.invoices.convert`
- `commerce.pricing.manage`

Having `commerce.manage` implies all granular commerce action permissions above.

---

## Price Lists

Catalog sale prices for products and services are maintained under بازرگانی → قیمت‌گذاری کالا و خدمات.

Updating catalog `sale_price` is the default price for new sale invoices when no last-sale unit price exists.

Last invoice sale unit price is shown for reference and may still override catalog price when issuing invoices.

---

## Sales Invoice

Updates Inventory.

Creates Accounting Document.

Updates Customer Balance.

---

## Purchase Invoice

Updates Inventory.

Creates Accounting Document.

Updates Supplier Balance.

---

## Cancellation

Sale invoices, purchase invoices, and proformas can be cancelled (`status=cancelled`).

Returns are not cancelled from this action.

Cancel does not reverse posted inventory/accounting by itself.

---

## Returns

Document type: `return` with direction `sale` or `purchase`.

Prerequisite: select a cancelled source invoice (`returned_from_id`) of the same direction.

Workflow:

1. Cancel the original sale/purchase invoice
2. Create return and choose that cancelled invoice
3. Confirm return to post inventory and accounting

Sale return (برگشت از فروش):

- Party: customer (from cancelled source)
- Inventory: receipt (stock in)
- Accounting: reverse AR / revenue / VAT / COGS
- Numbering: `sale_return` (SR-)

Purchase return (برگشت از خرید):

- Party: vendor (from cancelled source)
- Inventory: issue (stock out)
- Accounting: reverse AP / purchase / VAT
- Numbering: `purchase_return` (PR-)

Only one active return may reference a given cancelled invoice.

Sales reports stay on `document_type=invoice` only.

---

## Discounts

Line Discount

Invoice Discount

---

## Taxes

VAT

Supported.

---

## Forbidden

Delete Posted Invoice

Edit Posted Invoice