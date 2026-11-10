# Accounting Rules

## Principles

Accounting is the heart of ERP.

Every financial event must create an Accounting Document.

Accounting Documents are immutable after posting.

Posted documents can only be reversed.

---

## Fiscal Period

Documents can only be registered inside Open Fiscal Period.

Closed Fiscal Period is Read Only.

---

## Journal Entry

Every document must balance.

Total Debit = Total Credit

---

## Numbering

Document Number

Automatic

Unique

Sequential

Per Fiscal Year

---

## Accounts

Levels

Group

General

Subsidiary

Detail

Floating Detail

Accounts with ledger movement must stay active and selectable.

Inactive codes without turnover remain searchable for guidance and can be toggled from Chart of Accounts edit (`is_active`).

COA standardization must never deactivate an account that already has document lines.

---

## Cost Center

Optional

---

## Project

Optional

---

## Currency

Support Base Currency.

Future Support Multi Currency.

---

## Audit

Every document stores

Creator

Poster

Posting Time

Revision History

---

## Forbidden

Delete Posted Document

Edit Posted Document

Unbalanced Entry

Negative Debit

Negative Credit