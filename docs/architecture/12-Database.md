# Database Standards

## Principles

- Database is the single source of truth.
- Every table has one responsibility.
- Avoid duplicated data.
- Normalize first, optimize later.

---

## Primary Keys

All tables use:

id (BIGINT)

---

## Foreign Keys

Always use foreign keys.

Example

user_id

project_id

account_id

warehouse_id

---

## Naming

Tables

users

projects

accounting_documents

inventory_documents

invoice_items

Columns

created_at

updated_at

deleted_at

created_by

updated_by

deleted_by

---

## Soft Delete

Use SoftDeletes only when recovery is required.

Do not use SoftDeletes for pivot tables.

---

## UUID

Not required.

Use BIGINT IDs.

---

## Money

Never use float.

Use decimal(18,2)

---

## Dates

Store all dates in Gregorian.

Convert Jalali only in UI.

---

## Indexes

Always index

Foreign Keys

Document Numbers

Dates

Status

Code

---

## Transactions

Business operations affecting multiple tables MUST use DB Transactions.

---

## Audit

Important tables should store

created_by

updated_by

deleted_by

---

## Never

Store calculated balances.

Store duplicated totals.

Store presentation data.