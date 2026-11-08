# Treasury Rules

## Responsibilities

Cash

Bank

Cheque

Receipt

Payment

Transfer

---

## Permissions

- `treasury.view` / `treasury.manage`
- `treasury.create` / `treasury.update` / `treasury.delete`
- `treasury.partners.manage`

`treasury.manage` implies the granular treasury action permissions.

---

## Cashbox

Balance is calculated.

Never store balance.

---

## Bank

Balance is calculated.

Bank statement screen, printable form, and Excel export must use the same `FinancialReportService::bankTransactions` dataset.

---

## Receipt

Creates Treasury Transaction.

Creates Accounting Document.

---

## Payment

Creates Treasury Transaction.

Creates Accounting Document.

---

## Cheques

Issued

Received

Transferred

Collected

Returned

Bounced

Cancelled

---

## Bank Transfer

Always creates two treasury movements.

---

## Forbidden

Negative Cash

Negative Bank

Delete Posted Transaction