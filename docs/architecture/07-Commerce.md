# Commerce

## Modules

Sales

Purchase

Returns

Price Lists

- Route: `commerce.item-pricing.index`
- Livewire: `Commerce\ItemPricing\Index`
- Service: `ItemSalePriceService`
- Repository: `ItemRepository`

Customers

Suppliers

---

## Rules

Invoice is not Accounting.

Invoice generates Accounting.

Invoice updates Inventory.

Invoice updates Customer Balance.

Permissions are granular under `commerce.invoices.*` and `commerce.pricing.manage`.

`commerce.manage` still implies all commerce action permissions for backward compatibility.

---

## Flow

Invoice

↓

Inventory

↓

Accounting

↓

Customer Statement