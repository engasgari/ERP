# Payroll Rules

## Inputs

Attendance

Work Logs

Contracts

Salary Settings

Insurance

Tax

Versioned legal parameters (`payroll_legal_parameter_sets`)

---

## Outputs

Payslip

Salary Accounting

Reports

---

## Single engine

`NewPayrollEngineService` is the only calculation entry for monthly salary, insurance share, tax, payslip totals and payroll accounting draft.

Line insurance/tax classification uses `PayrollLineBasisCalculator` (PayrollItem flags + employment-order overrides + `IranLaborEmploymentOrderCatalog`).

Annual rates/ceilings/tax brackets load from `PayrollLegalParameterResolver` for the period Jalali year. Do **not** copy prior-year figures into a new year without an official source row.

---

## Salary

Calculated by payroll engine from attendance and employment order.

After calculation and before payment, bonus and non-system deductions may be adjusted manually on the payroll list/payment screen.

System lines (employee insurance, tax) stay locked.

Manual adjustment recalculates net payable and refreshes the salary accrual accounting document via the same finalize path.

Identity:

`gross = sum(earning lines)`

`total_deductions = sum(deduction lines)` including employee insurance + tax

`net_payable = gross - total_deductions`

Employer insurance is **not** deducted from net pay; it posts as employer expense/payable only.

---

## Attendance premiums

Day engine (`AttendanceDayCalculatorService`):

- Working day excess hours → `overtime_minutes` only
- Official holiday work → `holiday_minutes` only (not also overtime)
- Weekly rest / Friday work → `friday_minutes` only (not also overtime)
- Delay/early/absence coverage uses leave/mission windows without double-counting the same gap

Payroll premiums (Labor Law):

- Overtime multiplier default **1.4** (Art. 59: regular + 40%)
- Night premium default **0.35** of hourly rate for night hours (Art. 53)
- Holiday / Friday **payslip line** multipliers default **2.4** (common Iranian payroll practice: 1.0 wage for hours worked on rest/holiday + 1.4 premium, paid on top of monthly salary that already includes paid rest days). Configurable per year in `payroll_legal_parameter_sets`.

Coefficients may be overridden per year in legal parameter sets / shift `overtime_multiplier`.

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

Statutory shares (when insured): employee 7%, employer 20%, unemployment 3% (employer), unless exemption resolver disables unemployment.

When a legal parameter set provides `min_daily_wage` and `insurance_ceiling_daily_multiplier`, the insurance base is capped at `min_daily_wage × multiplier × days_in_period` (1404: circular 1000/1404/193).

---

## Tax

Calculated automatically on **tax base** (`taxable_income` on the tax record), from items with `taxable = true` (or line overrides), **independently** of the insurance base.

For years with seeded brackets (e.g. 1404 from Budget Law note «ز» of Tabareh 1), tax is progressive monthly via `SalaryTaxBracketCalculator`.

If no parameter set exists for the period year (e.g. 1405 until official budget/circular is entered), engine falls back to legacy flat `payroll_items.salary_tax` rate and records `mode = legacy_flat` in tax brackets meta — **do not invent** that year’s brackets.

---

## Approval

Draft

Calculated

Approved

Paid

---

## Staged monthly workflow

Payroll periods UI runs as a 3-step wizard for the selected Jalali year/month:

1. **Attendance** — display-only review of monthly attendance (calculation happens in Attendance module), confirm to continue
2. **Payroll** — `NewPayrollEngineService::runFullLifecycle`, review salary cards, optional manual amount edit popup (`PayrollAdjustmentService`), approve period
3. **Payment** — register/edit/reverse payments in-period until remaining is zero; optionally close the period

The standalone payroll payments list page is retired; `payroll/payments` redirects to periods.

---

## Payment

Registered separately after period approval.

Bank payment requires selecting the paying bank account.

Cash payment requires selecting the cashbox.

Payment posts an accounting document that settles salary payable with party dimension, so it appears on the party statement.

Payment can be reversed from the payroll payment list while the payroll period is not closed. Reversal voids the payment accounting document via the accounting engine and restores the calculation to payable state.

---

## Permissions

Single group: `حقوق و دستمزد`

- `payroll.view` / `payroll.manage`
- `payroll.periods.create` / `payroll.calculate` / `payroll.approve` / `payroll.post`
- `payroll.periods.delete` / `payroll.periods.close` / `payroll.adjust` / `payroll.settings.manage`
- Payroll item catalog (base/earnings/deductions) lives under Payroll Settings (`payroll.settings`), not on Periods
- `insurance.view` / `insurance.manage` / `insurance.payment`

`payroll.manage` implies the granular action permissions.

Legacy `salaries.view` / `salaries.manage` remain aliases of `payroll.view` / `payroll.manage` for employee-scoped access.

---

## Forbidden

Edit Approved Payslip after payment

Delete Posted Payroll

Change insurance/tax lines manually on the payment screen

Rewrite posted accounting documents without reverse/repost

Seed next-year legal rates by copying prior year without official source
