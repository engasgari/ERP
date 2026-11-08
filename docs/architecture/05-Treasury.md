# Treasury

## Responsibilities

- Cashbox
- Bank Account
- Cheque
- Receipt
- Payment
- Transfer
- Bank Reconciliation

---

## Rules

Treasury never writes directly to Ledger.

Every receipt creates an accounting document.

Every payment creates an accounting document.

Cashbox balance is calculated.

Bank balance is calculated.

Never store calculated balances.

Bank account statement (`bank-accounts/{id}/statement`) uses `FinancialReportService::bankTransactions` as the single dataset for screen, printable form, and Excel export.

---

## Flow

Receipt

↓

Treasury Service

↓

Accounting Engine

↓

Accounting Document