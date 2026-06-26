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

---

## Flow

Receipt

↓

Treasury Service

↓

Accounting Engine

↓

Accounting Document