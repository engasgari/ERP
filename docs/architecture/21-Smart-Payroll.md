# Smart Payroll (محاسبه هوشمند)

## Purpose

Register monthly payroll for active employees using:

1. Full calendar working days (optional) — ignores device punches / work-log gaps
2. A user-specified **target net payable** per employee

Insurance employee share stays based on insurable earnings from the employment order; the gap to target net is posted as `other_non_insurable_benefits` (non-insurable, taxable).

## Entry points

- Service: `App\Services\SmartPayrollService`
- UI: `payroll/smart-calculation` (`payroll.smart-calculation.index`)
- Menu: حقوق و دستمزد → بعد از «پرداخت بیمه» → «محاسبه هوشمند»
- Permission: `payroll.calculate`

## Flow

Period → (optional) calendar `AttendanceSummary` + `MonthlyAttendance` → `NewPayrollEngineService::calculateEmployee` → apply agreed net → accounting repost.

## Notes

- Does not permanently rewrite employment-order base salary.
- Blocks employees that already have payroll payments in the period.
- Posted salary documents are refreshed through the existing payroll accounting path.
