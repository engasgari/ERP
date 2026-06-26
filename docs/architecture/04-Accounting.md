# Accounting Engine

## Purpose

Accounting Engine قلب ERP است.

تمام رویدادهای مالی باید در نهایت از این موتور عبور کنند.

---

## Responsibilities

- Chart Of Accounts
- Accounting Documents
- Journal Entries
- General Ledger
- Subsidiary Ledger
- Trial Balance
- Financial Statements
- Fiscal Periods

---

## Inputs

- Sales
- Purchase
- Treasury
- Payroll
- Inventory
- Production
- Assets

---

## Outputs

- Accounting Documents
- Ledger
- Trial Balance
- Balance Sheet
- Income Statement

---

## Rules

No module can directly create accounting entries.

Every financial operation must use Accounting Engine.

Accounting Documents are immutable after posting.

Posted documents can only be reversed.

---

## Flow

Business Module

↓

Accounting Service

↓

Accounting Document

↓

Post Document

↓

Reports