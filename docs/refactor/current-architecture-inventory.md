# Current Architecture Inventory

Date: 2026-06-26

Scope:
- `app/`
- `resources/views/`
- `tests/`
- Architecture guidance under `docs/`

## 1) Existing ERP Modules

The codebase and `docs/project-context.md` currently describe these ERP areas:

- System & Access Control
- Base Information
- Accounting Engine
- Treasury
- Inventory
- Sales & Purchase
- Human Resources
- Attendance
- Payroll
- Projects & Production
- Reports

## 2) Layer Counts

Observed file counts in the current codebase:

| Layer | Count |
|---|---:|
| Controllers | 49 |
| Livewire components | 43 |
| Services | 29 |
| Repositories | 3 |
| Policies | 9 |
| Requests | 5 |
| Models | 71 |
| Tests | 22 |

## 3) Modules With Controllers/Livewire But No Repository

Only three repository classes exist in `app/Repositories`:

- `AccountingDocumentRepository`
- `FinancialReportRepository`
- `TreasuryRepository`

That means the following ERP areas currently have controllers and/or Livewire components but no dedicated repository class in `app/Repositories`:

- System & Access Control
- Base Information
- Inventory
- Sales & Purchase
- Human Resources
- Attendance
- Payroll
- Projects & Production

This is not automatically wrong, but it means the repository boundary is uneven across the ERP and most module data access is still embedded in controllers, services, or Livewire components.

## 4) Possible Business Logic Inside Controllers Or Livewire

The following files show clear signs of orchestration, calculation, or domain mutation inside the presentation layer:

- `app/Http/Controllers/DashboardController.php`
  - Cross-module financial, payroll, inventory, and project aggregations are built directly in the controller.
- `app/Http/Controllers/InvoiceController.php`
  - Contains document creation, update flows, line recreation, totals, and delete/force-delete paths.
- `app/Http/Controllers/InventoryDocumentController.php`
  - Handles transactional document creation and update behavior directly.
- `app/Http/Controllers/FinancialTransactionController.php`
  - Performs summary calculations, grouping, and transactional mutation logic.
- `app/Http/Controllers/SalaryController.php`
  - Contains salary calculation orchestration, payment state handling, and related document deletion flows.
- `app/Http/Controllers/ProductionOrderController.php`
  - Contains costing, material consumption, and posting orchestration.
- `app/Http/Controllers/WorkLogController.php`
  - Updates and deletes work logs with domain-specific behavior.
- `app/Livewire/Attendance/Calculations.php`
  - Performs attendance calculation orchestration and employee-period data processing.
- `app/Livewire/Attendance/Summaries.php`
  - Rebuilds attendance summaries and queries payroll periods directly.
- `app/Livewire/Payroll/Periods.php`
  - Coordinates payroll period processing and salary-related operations.
- `app/Livewire/Items/Index.php`
  - Performs item dependency checks and inventory/BOM related counts.
- `app/Livewire/Employees/SelfService.php`
  - Loads leave, mission, summary, payroll, and balance datasets directly from multiple models.

Interpretation:
- These files are not all equally problematic, but they do go beyond simple UI state or request forwarding.
- The strongest pattern is that controllers and Livewire classes are still doing domain orchestration that ideally belongs in services or repositories.

## 5) Possible Database Queries Inside Blade Or Livewire

Livewire files that directly query the database or build query objects:

- `app/Livewire/BankAccounts/Index.php`
- `app/Livewire/Employees/SelfService.php`
- `app/Livewire/Attendance/Summaries.php`
- `app/Livewire/Attendance/Calculations.php`
- `app/Livewire/Attendance/Missions.php`
- `app/Livewire/Payroll/Periods.php`
- `app/Livewire/Items/Index.php`
- `app/Livewire/WorkGroups/Assignments.php`
- `app/Livewire/Warehouses/Index.php`
- `app/Livewire/ChartAccounts/Index.php`

Blade views that appear to query models or relations directly:

- `resources/views/financial-transactions/project-report.blade.php`
- `resources/views/invoices/show.blade.php`
- `resources/views/invoices/print.blade.php`
- `resources/views/accounting-documents/index.blade.php`
- `resources/views/accounting-documents/print.blade.php`
- `resources/views/livewire/payroll/periods.blade.php`
- `resources/views/salaries/show.blade.php`
- `resources/views/salaries/index.blade.php`
- `resources/views/salaries/print-slip.blade.php`
- `resources/views/warehouses/show.blade.php`

Examples of the behavior observed:
- Blade files call relationship queries such as `->count()`, `->sum()`, and filtered relation queries.
- Some views compute monetary totals inline, which is exactly the kind of logic the architecture docs reserve for services or prepared datasets.

## 6) Careful Refactor Areas

### Accounting

- `app/Http/Controllers/AccountingDocumentController.php`
- `app/Models/AccountingDocument.php`
- `app/Models/AccountingDocumentLine.php`
- `app/Services/AccountingPostingService.php`
- `app/Repositories/AccountingDocumentRepository.php`

Why this area needs caution:
- Posted document immutability is central to the ERP rules.
- There are delete/unpost flows that need very careful treatment.
- Model accessors and report paths can easily create hidden performance regressions if totals are recalculated repeatedly.

### Treasury

- `app/Http/Controllers/TreasuryController.php`
- `app/Http/Controllers/BankAccountController.php`
- `app/Http/Controllers/FinancialTransactionController.php`
- `app/Repositories/TreasuryRepository.php`

Why this area needs caution:
- Treasury drives accounting entries and cash/bank state.
- Transactional integrity and auditability matter more than CRUD convenience.

### Inventory

- `app/Http/Controllers/InventoryDocumentController.php`
- `app/Http/Controllers/ItemController.php`
- `app/Livewire/Items/Index.php`
- `app/Http/Controllers/ManagementReportController.php`

Why this area needs caution:
- Inventory quantity and accounting value boundaries are easy to mix.
- Inventory-related reporting and inline calculations should be normalized before refactor.

### Payroll

- `app/Http/Controllers/SalaryController.php`
- `app/Livewire/Payroll/Periods.php`
- `app/Livewire/Attendance/Calculations.php`
- `app/Http/Controllers/PayrollAccountingSettingController.php`

Why this area needs caution:
- Payroll calculations, attendance summaries, and accounting posting are tightly coupled.
- A refactor here must preserve salary totals, payment status, and posting behavior.

### Reports

- `app/Services/FinancialReportService.php`
- `app/Repositories/FinancialReportRepository.php`
- `app/Http/Controllers/FinancialReportController.php`
- `app/Http/Controllers/ManagementReportController.php`

Why this area needs caution:
- Report outputs must remain consistent across screen, print, PDF, and Excel.
- Several report paths still contain query and aggregation logic that should remain dataset-driven.

## 7) Inventory Notes

- The architecture docs are aligned on a service/repository/controller flow, but the implementation still has many direct model and query touchpoints in controllers, Livewire, and Blade.
- Repository coverage is much smaller than the rest of the codebase, so most module-specific data access is still distributed across presentation and controller layers.
- The highest-risk refactor zones are accounting lifecycle, payroll calculation, inventory quantity/value boundaries, and report dataset consistency.

