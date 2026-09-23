# Payroll Rules

## Inputs

Attendance

Work Logs

Contracts

Salary Settings

Insurance

Tax

---

## Outputs

Payslip

Salary Accounting

Reports

---

## Salary

Calculated by payroll engine from attendance and employment order.

After calculation and before payment, bonus and non-system deductions may be adjusted manually on the payroll list/payment screen.

System lines (employee insurance, tax) stay locked.

Manual adjustment recalculates net payable and refreshes the salary accrual accounting document.

---

## Payslip

Generated Monthly.

Immutable after Approval.

Personnel header (organization unit, position) is snapshotted from the covering approved employment order at issue time, with fallback to employee master data.

Print form follows Iranian payroll slip layout with Persian digits.

---

## Insurance

Calculated automatically on **insurance base** (`insurance_wage` on the insurance record), not on gross pay.

Each payroll item (`payroll_items.insurable`, or employment-order line overrides) defines whether an earning counts toward the base. Example: **حق مأموریت** (`mission`) is non-insurable by default; it still increases gross and net.

---

## Tax

Calculated automatically on **tax base** (`taxable_income` on the tax record), from items with `taxable = true` (or line overrides), not necessarily equal to gross.

---

## Approval

Draft

Calculated

Approved

Paid

---

## Payment

Registered separately after period approval.

Bank payment requires selecting the paying bank account.

Cash payment requires selecting the cashbox.

Payment posts an accounting document that settles salary payable with party dimension, so it appears on the party statement.

Payment can be reversed from the payroll payment list while the payroll period is not closed. Reversal voids the payment accounting document via the accounting engine and restores the calculation to payable state.

---

## Forbidden

Edit Approved Payslip after payment

Delete Posted Payroll

Change insurance/tax lines manually on the payment screen
