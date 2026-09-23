# Employment Order Rules (احکام کارگزینی)

## Purpose

Employment orders are the HR source of truth for organizational assignment and fixed wage structure.

## Architecture

Route / Livewire UI
→ EmploymentOrderService
→ EmploymentOrderRepository + SalaryItemRepository
→ EmploymentOrderCalculationService (DTO summary)
→ Models / Database

Livewire never calculates totals.
Blade never queries salary catalog or calculates money.

## Salary Items

All wage/deduction components come from `salary_items`.

Decree lines are stored in `employment_order_lines`.

Future payroll modules (overtime, eid bonus, severance, insurance, tax, leave buyout, payslips) reuse the same `salary_items` catalog via `category` and flags without rewriting decree architecture.

## UI

Tabs:

1. اطلاعات حکم
2. اقلام حکم (Data Grid)
3. کسورات (Data Grid)
4. خلاصه محاسبات
5. تاریخچه احکام

## Status Flow

draft → approved

Approved orders apply to employee master data.
Revert restores previous employee values from history.

## Forbidden

- Hardcoded wage titles in Blade/Livewire
- Calculating insurance/tax/net inside Livewire or Blade
- Editing approved orders without revert
