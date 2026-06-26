# Accounting Direct Mutation Fix Plan

Date: 2026-06-26

Purpose:
- Prioritize risky or unclear direct accounting mutations from highest risk to lowest risk.
- Keep future Codex prompts small and reversible.
- Move the codebase toward reversal-only accounting behavior without broad rewrites.

## Highest-Risk Order

### 1) `app/Services/TreasuryService.php::deleteAccountingDocument`

- Current file: `app/Services/TreasuryService.php`
- Current class/method: `TreasuryService::deleteAccountingDocument`
- Current mutation: Deletes linked accounting document lines and force-deletes accounting documents for treasury transactions.
- Risk level: Critical
- Why it violates or risks Accounting Engine boundaries: Treasury is removing accounting history directly instead of requesting a reversal or void through Accounting Engine.
- Proposed target boundary: `AccountingPostingService`
- Exact allowed files for a future Codex task:
  - `app/Services/TreasuryService.php`
  - `app/Services/AccountingPostingService.php` if a single reversal/void entry point is needed later
  - `tests/Feature/TreasuryBankTransactionTest.php`
- Forbidden changes:
  - Do not change routes.
  - Do not change controller behavior.
  - Do not introduce new services or repositories.
  - Do not change treasury posting semantics beyond replacing the delete path.
- Test needed: Treasury delete/update scenario proving accounting documents are not hard-deleted.
- Estimated change size: Medium

### 2) `app/Services/ProjectDeletionService.php` accounting deletion helpers

- Current file: `app/Services/ProjectDeletionService.php`
- Current class/method: `ProjectDeletionService::deleteAccountingDocumentsForProjectLines`, `deleteOverheadAllocationAccountingDocuments`, `deleteInvoiceWithRelatedDocuments`, `deleteFinancialTransactions`
- Current mutation: Hard-deletes accounting documents and lines during project cleanup.
- Risk level: Critical
- Why it violates or risks Accounting Engine boundaries: Project cleanup is erasing accounting evidence across multiple downstream flows.
- Proposed target boundary: `AccountingPostingService`
- Exact allowed files for a future Codex task:
  - `app/Services/ProjectDeletionService.php`
  - `tests/Feature/ProjectDeletionTest.php`
- Forbidden changes:
  - Do not redesign project deletion.
  - Do not alter route names or permissions.
  - Do not introduce new accounting modules.
- Test needed: Project deletion regression proving linked accounting documents are preserved or voided.
- Estimated change size: Large

### 3) `app/Services/PayrollCalculationService.php::deletePeriod`

- Current file: `app/Services/PayrollCalculationService.php`
- Current class/method: `PayrollCalculationService::deletePeriod`
- Current mutation: Deletes payroll-linked accounting documents and force-deletes them.
- Risk level: High
- Why it violates or risks Accounting Engine boundaries: Payroll cleanup is erasing salary accounting history instead of preserving it through reversal or voiding.
- Proposed target boundary: `AccountingPostingService`
- Exact allowed files for a future Codex task:
  - `app/Services/PayrollCalculationService.php`
  - `tests/Feature/PayrollPeriodsTest.php`
- Forbidden changes:
  - Do not change payroll calculation formulas.
  - Do not change payroll route names or permissions.
  - Do not add a new payroll posting layer.
- Test needed: Payroll period deletion regression proving accounting documents are not force-deleted.
- Estimated change size: Medium

### 4) `app/Services/FiscalPeriodService.php::removeGeneratedClosingArtifacts`

- Current file: `app/Services/FiscalPeriodService.php`
- Current class/method: `FiscalPeriodService::removeGeneratedClosingArtifacts`
- Current mutation: Deletes generated closing/opening accounting documents and their lines with `forceDelete()`.
- Risk level: High
- Why it violates or risks Accounting Engine boundaries: Closing artifacts are accounting history and should not be hard-deleted.
- Proposed target boundary: `AccountingPostingService`
- Exact allowed files for a future Codex task:
  - `app/Services/FiscalPeriodService.php`
  - `tests/Feature/FiscalPeriodClosingWorkflowTest.php`
- Forbidden changes:
  - Do not change fiscal period route names.
  - Do not alter closing/opening document semantics outside the accounting boundary.
  - Do not introduce new migrations or document types.
- Test needed: Closing workflow regression proving generated accounting documents remain auditable.
- Estimated change size: Medium

### 5) `app/Services/RelatedDocumentDeletionService.php::deleteInventoryDocumentWithRelated`

- Current file: `app/Services/RelatedDocumentDeletionService.php`
- Current class/method: `RelatedDocumentDeletionService::deleteInventoryDocumentWithRelated`
- Current mutation: Deletes related accounting documents and lines while removing inventory documents.
- Risk level: High
- Why it violates or risks Accounting Engine boundaries: Inventory cleanup is cascading into accounting-history deletion and blurring quantity/value ownership.
- Proposed target boundary: `AccountingPostingService`
- Exact allowed files for a future Codex task:
  - `app/Services/RelatedDocumentDeletionService.php`
  - closest inventory deletion feature test file, if one exists
- Forbidden changes:
  - Do not change inventory document routes.
  - Do not change stock valuation rules.
  - Do not create a new inventory posting service.
- Test needed: Inventory deletion regression proving linked accounting documents are not hard-deleted.
- Estimated change size: Medium

### 6) `app/Http/Controllers/InvoiceController.php::deleteDownstreamDocuments`

- Current file: `app/Http/Controllers/InvoiceController.php`
- Current class/method: `InvoiceController::deleteDownstreamDocuments`
- Current mutation: Deletes downstream inventory-linked accounting documents and lines, including force-deletion.
- Risk level: High
- Why it violates or risks Accounting Engine boundaries: The controller is performing cross-module accounting cleanup directly.
- Proposed target boundary: `AccountingPostingService`
- Exact allowed files for a future Codex task:
  - `app/Http/Controllers/InvoiceController.php`
  - `tests/Feature/InvoiceVatAccountingTest.php`
  - `tests/Feature/InvoiceSettlementTest.php` if route coverage is needed
- Forbidden changes:
  - Do not change invoice route names.
  - Do not change invoice numbering or posting semantics.
  - Do not rewrite invoice business rules.
- Test needed: Invoice deletion regression proving accounting documents are not force-deleted.
- Estimated change size: Medium

### 7) `app/Services/AccountingPostingService.php::deleteFinancialTransactionDocument`

- Current file: `app/Services/AccountingPostingService.php`
- Current class/method: `AccountingPostingService::deleteFinancialTransactionDocument`
- Current mutation: Loads accounting documents, deletes their lines, and force-deletes the documents.
- Risk level: High
- Why it violates or risks Accounting Engine boundaries: It is a direct destructive mutation of accounting history and should be reversal-only if the document is historically relevant.
- Proposed target boundary: `AccountingPostingService`
- Exact allowed files for a future Codex task:
  - `app/Services/AccountingPostingService.php`
  - `tests/Feature/FinancialTransactionGeneralEntriesTest.php`
  - `tests/Feature/BankLedgerRepairTest.php` if the repair flow is involved
- Forbidden changes:
  - Do not add a new posting service.
  - Do not change routes or controller signatures.
  - Do not alter financial transaction semantics beyond replacing hard delete.
- Test needed: Financial transaction deletion or repair regression proving accounting documents are preserved or voided.
- Estimated change size: Medium

### 8) `app/Services/AccountingPostingService.php::unpost`

- Current file: `app/Services/AccountingPostingService.php`
- Current class/method: `AccountingPostingService::unpost`
- Current mutation: Moves a posted document back to `draft` and clears posting metadata.
- Risk level: High
- Why it violates or risks Accounting Engine boundaries: Posted documents are meant to be immutable and reverse-only.
- Proposed target boundary: `AccountingPostingService`
- Exact allowed files for a future Codex task:
  - `app/Services/AccountingPostingService.php`
  - `tests/Feature/FiscalPeriodRestrictionTest.php`
  - `tests/Feature/InvoiceVatAccountingTest.php`
- Forbidden changes:
  - Do not change posting creation flow.
  - Do not change route names.
  - Do not add new document types outside the accounting boundary.
- Test needed: Regression proving a posted document cannot be unposted and must instead be reversed later.
- Estimated change size: Small to medium

### 9) `app/Http/Controllers/AccountingDocumentController.php::destroy`

- Current file: `app/Http/Controllers/AccountingDocumentController.php`
- Current class/method: `AccountingDocumentController::destroy`
- Current mutation: Directly calls `$accountingDocument->delete()` for non-automatic documents.
- Risk level: Medium
- Why it violates or risks Accounting Engine boundaries: The controller mutates accounting documents directly instead of delegating lifecycle control to the accounting boundary.
- Proposed target boundary: `AccountingPostingService`
- Exact allowed files for a future Codex task:
  - `app/Http/Controllers/AccountingDocumentController.php`
  - `tests/Feature/FiscalPeriodRestrictionTest.php`
- Forbidden changes:
  - Do not change routes.
  - Do not add new services or repositories.
  - Do not change view names or permissions.
- Test needed: Controller regression proving posted accounting documents cannot be deleted through the existing destroy route.
- Estimated change size: Small

## Unclear Or Lower-Risk Items

### 10) `app/Services/AccountingPostingService.php::replaceLines`

- Current file: `app/Services/AccountingPostingService.php`
- Current class/method: `AccountingPostingService::replaceLines`
- Current mutation: Deletes all existing lines and recreates them.
- Risk level: Unclear
- Why it violates or risks Accounting Engine boundaries: It is acceptable for draft rewrites inside the accounting transaction, but it would be a boundary violation if reused on posted documents.
- Proposed target boundary: `AccountingPostingService`
- Exact allowed files for a future Codex task:
  - `app/Services/AccountingPostingService.php`
  - one accounting feature test file that exercises draft updates
- Forbidden changes:
  - Do not move this into a controller.
  - Do not broaden it into a generic helper.
  - Do not allow it to run on posted documents.
- Test needed: Draft-update regression proving line replacement still works only for editable documents.
- Estimated change size: Small

## First Safest Small Refactor Candidate

Recommended first candidate:
- `app/Http/Controllers/AccountingDocumentController.php::destroy`

Why this is the safest first step:
- It is a single controller action.
- The change can be limited to a posted-document guard.
- It does not require moving logic across modules.
- It already has a natural regression home in `tests/Feature/FiscalPeriodRestrictionTest.php`.

Smallest future Codex prompt:
- Add a posted-document guard to `AccountingDocumentController::destroy` and verify it with one regression test.

## Fix-Plan Constraints

- No broad rewrite of posting architecture.
- No new accounting services or repositories.
- No route redesign.
- No mass migration of deletion logic in one prompt.
- No simultaneous refactor of Treasury, Payroll, Inventory, and Projects.

## Done Criteria

- Every risky direct mutation path has a ranked owner and a smallest next step.
- The first task is controller-local and test-backed.
- The next tasks move only one destructive accounting path at a time toward reversal-only behavior.
- Posted accounting documents remain the highest-protection asset in the plan.
