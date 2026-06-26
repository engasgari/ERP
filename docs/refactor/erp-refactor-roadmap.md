# ERP Refactor Roadmap

Date: 2026-06-26

Purpose:
- Define a safe, phased path to evolve the current Laravel ERP into a more disciplined Iranian-style ERP architecture.
- Preserve backward compatibility while reducing architectural risk.
- Keep Codex work small, explicit, and reversible.

## Phase 1: Architecture Guardrails And Documentation Lock

Goal:
- Make the existing architecture rules the default contract for future changes.
- Freeze the current guidance so implementation work does not drift.

Why it matters:
- The codebase already shows mixed responsibility across controllers, Livewire, Blade, and some services.
- A stable documentation baseline prevents accidental redesign while deeper refactors are in progress.

Files/modules likely involved:
- `AGENTS.md`
- `CODEX.md`
- `docs/project-context.md`
- `docs/architecture/*`
- `docs/business/*`
- `docs/standards/*`
- `docs/refactor/*`

Forbidden changes:
- No application code changes.
- No module rewrites.
- No route, permission, API, or public behavior changes.
- No new services, repositories, policies, requests, tests, or migrations.

Codex task size rule:
- One documentation or inventory task at a time.
- Prefer single-file edits.
- No cross-module documentation rewrites in one prompt.

Done criteria:
- Architecture guidance is complete, readable, and consistent.
- Refactor documents clearly reference the architecture rules.
- No implementation files are changed.

## Phase 2: Accounting Engine Safety

Goal:
- Protect posted accounting documents, ledger integrity, and reversal behavior.
- Make accounting lifecycle rules explicit and mechanically safe.

Why it matters:
- Accounting is the heart of the ERP and the highest-risk module.
- Posted documents must remain read-only and reversible only.

Files/modules likely involved:
- `app/Http/Controllers/AccountingDocumentController.php`
- `app/Models/AccountingDocument.php`
- `app/Models/AccountingDocumentLine.php`
- `app/Services/AccountingPostingService.php`
- `app/Repositories/AccountingDocumentRepository.php`
- accounting-related tests

Forbidden changes:
- No direct edits to posted documents.
- No hard delete of posted accounting records.
- No ledger changes outside Accounting Engine.
- No behavior change to route names or public endpoints without explicit approval.

Codex task size rule:
- One accounting workflow per task.
- Keep each change scoped to a single document lifecycle path such as create, post, reverse, print, or list.
- Every accounting change must include a regression test plan.

Done criteria:
- Posted documents cannot be edited or deleted.
- Reversal is explicit and auditable.
- Accounting behavior stays transaction-safe and testable.

## Phase 3: Treasury Boundary With Accounting Engine

Goal:
- Ensure Treasury creates accounting requests but never manipulates ledger state directly.
- Separate cash/bank operations from accounting posting responsibilities.

Why it matters:
- Treasury is the operational owner of cash and bank, but Accounting owns the ledger.
- Boundary violations here create audit and reconciliation risk.

Files/modules likely involved:
- `app/Http/Controllers/TreasuryController.php`
- `app/Http/Controllers/BankAccountController.php`
- `app/Http/Controllers/FinancialTransactionController.php`
- `app/Repositories/TreasuryRepository.php`
- treasury-related models and tests

Forbidden changes:
- No direct ledger writes from Treasury.
- No bypass of Accounting Engine posting flow.
- No new shortcut endpoints for ledger mutation.

Codex task size rule:
- One Treasury workflow per task.
- Limit work to one source document or one posting path at a time.

Done criteria:
- Treasury actions map cleanly to accounting requests or posted documents.
- Cash/bank state and accounting state remain consistent.
- Reconciliation reports can trace source-to-ledger flow.

## Phase 4: Inventory Quantity/Value Separation

Goal:
- Keep inventory quantity logic in Inventory and value logic in Accounting.
- Eliminate mixed quantity/value responsibilities where they are still intertwined.

Why it matters:
- Inventory errors can corrupt both stock and financial statements.
- Quantity and value must evolve independently with a clear handoff boundary.

Files/modules likely involved:
- `app/Http/Controllers/InventoryDocumentController.php`
- `app/Http/Controllers/ItemController.php`
- `app/Livewire/Items/Index.php`
- `app/Http/Controllers/ManagementReportController.php`
- inventory-related models and tests

Forbidden changes:
- No direct accounting ledger mutation from Inventory.
- No quantity mutation from Accounting.
- No redesign of inventory posting rules without explicit approval.

Codex task size rule:
- One inventory document or one reporting path per task.
- Keep quantity and value separation changes isolated.

Done criteria:
- Inventory documents own quantity only.
- Accounting documents own value only.
- Reporting outputs no longer rely on ambiguous mixed logic.

## Phase 5: Sales And Purchase Document Flow

Goal:
- Normalize invoice and commercial document flows around clear service boundaries.
- Reduce duplicated totals, posting logic, and document lifecycle inconsistencies.

Why it matters:
- Sales and Purchase sit at the intersection of parties, inventory, treasury, and accounting.
- This module can easily become the source of duplicated business rules.

Files/modules likely involved:
- `app/Http/Controllers/InvoiceController.php`
- `app/Models/Invoice.php`
- invoice-related Livewire components and views
- sales/purchase-related tests

Forbidden changes:
- No direct stock ledger updates.
- No direct accounting ledger writes outside Accounting Engine.
- No change to invoice numbering or public route names unless explicitly approved.

Codex task size rule:
- One document type or one lifecycle action per task.
- Prefer extracting consistency logic over rewriting entire flows.

Done criteria:
- Invoice creation, update, posting, and deletion paths are explicit and consistent.
- Shared dataset shape is reused across screen and print paths.
- Totals are produced in services or repositories, not in Blade.

## Phase 6: HR, Attendance And Payroll Separation

Goal:
- Separate HR master data, attendance processing, payroll calculation, and accounting posting.
- Make payroll processing explicit, testable, and auditable.

Why it matters:
- Payroll is a high-stakes domain with financial and legal impact.
- Attendance and payroll rules must not leak into unrelated presentation layers.

Files/modules likely involved:
- `app/Http/Controllers/EmployeeController.php`
- `app/Http/Controllers/EmploymentContractController.php`
- `app/Http/Controllers/EmploymentOrderController.php`
- `app/Http/Controllers/SalaryController.php`
- `app/Livewire/Attendance/*`
- `app/Livewire/Payroll/*`
- HR/payroll-related models, requests, and tests

Forbidden changes:
- No payroll posting outside Accounting Engine.
- No attendance calculations in Blade.
- No salary recalculation in Livewire UI state.

Codex task size rule:
- One HR or payroll use case per task.
- Never combine master-data cleanup with salary calculation refactors in the same step.

Done criteria:
- HR owns employment data.
- Attendance owns attendance summaries.
- Payroll owns salary calculation.
- Accounting owns posting of payroll results.

## Phase 7: Reports Normalized Dataset Layer

Goal:
- Ensure every report is powered by a normalized dataset from services/repositories.
- Keep screen, print, PDF, and Excel output aligned.

Why it matters:
- Reports are currently one of the most sensitive areas for duplication and hidden query logic.
- A stable dataset contract reduces formatting drift and business rule duplication.

Files/modules likely involved:
- `app/Services/FinancialReportService.php`
- `app/Repositories/FinancialReportRepository.php`
- `app/Http/Controllers/FinancialReportController.php`
- `app/Http/Controllers/ManagementReportController.php`
- report views and export flows

Forbidden changes:
- No query logic inside Blade.
- No separate data logic for screen versus print versus export.
- No ad hoc report calculations inside views.

Codex task size rule:
- One report family per task.
- Keep export consistency changes within the same dataset contract.

Done criteria:
- Reports use shared normalized datasets.
- Screen and export outputs match the same source data.
- Report logic is centralized and cacheable.

## Phase 8: ERP UI Consistency

Goal:
- Align pages with the ERP UI standard: breadcrumb, header, toolbar, filters, content, actions, empty state, loading state.
- Reduce duplicated UI fragments and inconsistent layouts.

Why it matters:
- A coherent ERP interface improves operator confidence and reduces maintenance overhead.
- UI consistency helps users move across modules without relearning patterns.

Files/modules likely involved:
- `resources/views/*`
- `app/Livewire/UI/*`
- module-specific Blade views and Livewire pages

Forbidden changes:
- No business logic in Blade.
- No data queries in UI views.
- No redesign that breaks established module navigation or route names.

Codex task size rule:
- One UI pattern or one page family per task.
- Do not combine layout unification with business rule changes.

Done criteria:
- Shared UI patterns are reused.
- Views render prepared data only.
- Empty/loading states are present and consistent across major modules.

## Phase 9: Tests And Regression Safety

Goal:
- Make refactoring safe by improving regression coverage around the critical modules.
- Protect current public behavior while architecture improves.

Why it matters:
- The highest-risk modules are accounting, treasury, inventory, payroll, and reports.
- Refactors without regression protection will be too risky to sustain.

Files/modules likely involved:
- `tests/*`
- accounting, treasury, inventory, payroll, and reports test suites
- any new regression cases for controller/service boundaries

Forbidden changes:
- No production behavior changes without a test plan.
- No deleting or weakening coverage to make refactors easier.
- No refactor work that cannot be verified.

Codex task size rule:
- One regression scenario per task.
- Keep test additions small and directly tied to a bug or architecture boundary.

Done criteria:
- Critical flows have regression coverage.
- Failing edge cases are captured before implementation changes.
- Refactor steps can be validated quickly and repeatedly.

## Execution Rules For Codex

- Keep every task small and local.
- Never combine more than one architectural phase in a single implementation task.
- Prefer extraction, normalization, and documentation before behavior changes.
- If a change could affect posted accounting, payroll, inventory quantity, or report output, stop and split the task.
- Preserve route names, permission keys, and public APIs unless the senior architect explicitly asks for a change.

