# Reports Registry and Dataset Health Check

Date: 2026-06-26

Source note: `docs/11-Reports.md` was not present at the root path requested. I used `docs/architecture/11-Reports.md`, the current implementation, and the report views/controllers to prepare this audit.

## Executive Summary

The financial report stack is in better shape than the management report stack.

`FinancialReportRegistry` now owns the catalog and report definitions, `FinancialReportService` owns the normalized report payload, `FinancialReportRepository` owns the report queries, and `FinancialReportController` reuses the same dataset for screen, print, PDF, and Excel output.

The main architectural gap is that `ManagementReportController` still builds its own inventory/trial-balance datasets directly in the controller instead of using the normalized financial report pipeline. The second gap is repeated aggregation inside `FinancialReportService` for several reports, especially VAT and P&L-style reports.

No files were found under `app/Reports`, and `app/Livewire/Reports` appears empty. Report output is therefore centered on the service/controller/view pipeline rather than dedicated report classes.

## Current Report Stack

- Registry and normalized report contract
  - `app/Support/FinancialReportRegistry.php`
  - `app/Services/FinancialReportService.php`
  - `app/Repositories/FinancialReportRepository.php`
  - `app/Exports/FinancialReportExport.php`
- Financial report controllers
  - `app/Http/Controllers/FinancialReportController.php`
- Management report controllers
  - `app/Http/Controllers/ManagementReportController.php`
- Financial report views
  - `resources/views/financial-reports/report.blade.php`
  - `resources/views/financial-reports/print/report.blade.php`
  - `resources/views/financial-reports/partials/report-shell.blade.php`
  - `resources/views/financial-reports/account-statement.blade.php`
  - `resources/views/financial-reports/statement.blade.php`
  - `resources/views/financial-reports/trial-balance.blade.php`
  - `resources/views/financial-reports/balance-sheet.blade.php`
  - `resources/views/financial-reports/profit-loss.blade.php`
  - `resources/views/financial-reports/aging.blade.php`
- Management report views
  - `resources/views/management-reports/trial-balance.blade.php`
  - `resources/views/management-reports/warehouse-cardex.blade.php`
  - `resources/views/management-reports/warehouse-inventory.blade.php`
  - `resources/views/management-reports/generic.blade.php`
  - `resources/views/management-reports/print/layout.blade.php`

## Which Reports Use the Registry or Report Classes

- `FinancialReportService::catalog()` and `FinancialReportService::reportDefinitions()` delegate to `FinancialReportRegistry`.
- `FinancialReportController::show()`, `print()`, `pdf()`, and `excel()` all call `FinancialReportService::report()` and reuse the same normalized dataset.
- The financial report keys currently routed through the registry/service include:
  - `trial-balance`
  - `detailed-trial-balance`
  - `general-ledger`
  - `detailed-ledger`
  - `balance-sheet`
  - `income-statement`
  - `cash-flow-statement`
  - `changes-in-equity`
  - `accounts-receivable-aging`
  - `accounts-payable-aging`
  - `customer-statement`
  - `supplier-statement`
  - `outstanding-invoices`
  - `overdue-invoices`
  - `cash-book`
  - `bank-book`
  - `bank-statement`
  - `bank-reconciliation`
  - `cash-flow-by-period`
  - `sales-tax`
  - `purchase-tax`
  - `vat-summary`
  - `tax-transactions`
  - `expense-analysis-by-account`
  - `revenue-analysis-by-account`
  - `profitability-by-project`
  - `profitability-by-customer`
  - `cost-center-report`
  - `department-financial-performance`
  - `journal-entries`
  - `journal-entries-by-date`
  - `journal-entries-by-account`
  - `audit-trail`
  - `deleted-modified-transactions`

## Output Model

- Screen
  - `app/Http/Controllers/FinancialReportController::show()`
- Print
  - `app/Http/Controllers/FinancialReportController::print()`
  - `resources/views/financial-reports/print/report.blade.php`
- PDF
  - `app/Http/Controllers/FinancialReportController::pdf()`
- Excel
  - `app/Http/Controllers/FinancialReportController::excel()`
  - `app/Exports/FinancialReportExport.php`

For the financial reports, screen, print, PDF, and Excel all consume the same `report()` payload. The Excel exporter reads `export_rows`, which is generated from the same section data as the screen and print views.

## Findings

| Severity | Location | Reason | Recommendation | Suggested Smallest Future Codex Task | Estimated Difficulty |
|---|---|---|---|---|---|
| Critical | `app/Http/Controllers/ManagementReportController.php::warehouseCardexData()`, `warehouseInventoryData()`, `warehouseDocumentTotals()` | The controller directly assembles inventory Kardex-style datasets, computes quantity/value balances, and derives summary totals. This is report business logic living in a controller, which breaks the normalized report boundary defined in the architecture docs. | Move these inventory report datasets into a report service/repository layer and keep the controller as a thin renderer. | Extract `warehouseInventoryData()` into a service or repository method without changing the output shape. | Medium |
| Risky | `app/Services/FinancialReportService.php::vatSummaryReport()`, `pnlGroupRows()`, `profitabilityByProjectReport()`, `profitabilityByCustomerReport()` | Several financial report methods repeat expensive aggregation work inside the service. `vatSummaryReport()` loads invoices twice, and `pnlGroupRows()` repeatedly queries accounts and posted lines per report section. The calculations are correct, but they are not normalized into reusable dataset objects. | Consolidate repeated report aggregations into shared dataset builders so each report computes once and reuses the same rows. | Refactor `vatSummaryReport()` to build one shared invoice dataset and split it into sales and purchase buckets. | Small to Medium |
| Risky | `app/Http/Controllers/ManagementReportController.php::trialBalanceData()` and `resources/views/management-reports/trial-balance.blade.php` | The management trial balance still uses a controller-specific dataset and a dedicated view instead of the financial report pipeline. The view is render-only, but the dataset is separate from the normalized report contract used elsewhere. | Route trial balance through the financial report service where possible, or at least align the controller payload with the same contract. | Replace the controller-local trial balance array with `FinancialReportService::report('trial-balance')` for the management page. | Small |
| Unclear | `resources/views/financial-reports/partials/report-shell.blade.php` | This shared shell does not query data, but it contains report-key-specific column maps, summary ordering, and drilldown column rules. That is presentation logic, yet it duplicates some of the report metadata shape that already exists in the service/export layer. | Keep the shell render-only and move any remaining report metadata rules into the service layer later. | Extract the report-key-to-columns map into a helper or DTO used by both screen and export rendering. | Small |
| Safe | `app/Http/Controllers/FinancialReportController.php` | Screen, print, PDF, and Excel all go through the same `FinancialReportService::report()` call. This is the desired behavior and keeps the dataset consistent across outputs. | Preserve the single `report()` dataset for all outputs. | None needed unless the report contract changes. | None |
| Safe | `app/Repositories/FinancialReportRepository.php` | The repository owns the data queries, eager loading, and filter application for financial reports. This matches the architecture rule. | Keep all future financial report queries in the repository. | None needed unless a new report source is introduced. | None |
| Safe | `resources/views/financial-reports/report.blade.php`, `resources/views/financial-reports/print/report.blade.php`, `resources/views/financial-reports/account-statement.blade.php`, `resources/views/financial-reports/statement.blade.php`, `resources/views/financial-reports/trial-balance.blade.php`, `resources/views/financial-reports/balance-sheet.blade.php`, `resources/views/financial-reports/profit-loss.blade.php`, `resources/views/financial-reports/aging.blade.php` | These views render prepared data and format values only. No relation queries or business totals were found in these view files. | Keep them render-only. | None. | None |

## Blade and Query Audit

- No Blade view was found querying relations directly for financial reports.
- No Blade view was found calculating report totals from raw models for the financial report screens.
- The management inventory views are mostly render-only, but they display controller-computed totals from `ManagementReportController`.
- The shared report shell does not compute business totals, but it does encode some report-key-specific column selection and drilldown behavior.

## N+1 and Duplicate Aggregation Risk

- `FinancialReportRepository` is generally eager loading the important relations for report lines and documents.
- Repeated aggregation risk exists in `FinancialReportService` where the same source data is queried more than once for the same report family.
- `ManagementReportController` contains the highest duplication risk because it reimplements inventory report dataset logic outside the financial report stack.
- The financial report views themselves do not show an obvious N+1 issue, because they render preloaded rows and formatted summaries.

## Recommended First Safe Refactor Candidate

`app/Http/Controllers/ManagementReportController.php::warehouseInventoryData()`

Why this is the best first step:

- It is a single controller method with a clearly defined dataset.
- It already aligns with the financial report concept and can be moved without changing the final UI.
- It is smaller and safer than trying to rework the entire financial report service.
- It reduces the biggest architectural gap: report data assembled directly in a controller.

## Suggested Next Codex Task

- Extract `warehouseInventoryData()` into a dedicated report dataset service or repository method.
- Keep the current route, view, and output shape unchanged.
- Reuse the same normalized dataset for any future print/export variant.
