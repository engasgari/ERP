# Accounting Direct Mutation Fix Plan

Date: 2026-06-26

Purpose:
- Prioritize direct accounting mutation fixes from highest risk to lowest risk.
- Keep each future Codex prompt small, surgical, and reversible.
- Preserve the Accounting Engine boundary and posted-document immutability.

## Priority Order

### 1) `app/Services/TreasuryService.php::deleteAccountingDocument`

Current behavior:
- Treasury deletes linked accounting documents and their lines using `lines()->delete()` and `forceDelete()`.

Why it is risky:
- Treasury is erasing accounting history directly.
- This bypasses the Accounting Engine and breaks auditability.

Target service boundary:
- Accounting Engine, specifically the accounting document reversal/void path.

Exact allowed files for a future Codex prompt:
- `app/Services/TreasuryService.php`
- `tests/Feature/TreasuryBankTransactionTest.php` or the closest existing treasury feature test

Forbidden changes:
- Do not change routes.
- Do not change controllers.
- Do not change database schema.
- Do not add new services or repositories.
- Do not change treasury posting semantics beyond replacing the delete path with a boundary-safe call.

Test needed:
- A treasury deletion/update scenario that verifies accounting history is not hard-deleted and posted documents remain immutable.

---

### 2) `app/Services/ProjectDeletionService.php::deleteAccountingDocumentsForProjectLines`, `deleteOverheadAllocationAccountingDocuments`, `deleteInvoiceWithRelatedDocuments`, `deleteFinancialTransactions`

Current behavior:
- Project cleanup cascades into accounting document deletion and line deletion, then force-deletes the documents.

Why it is risky:
- Project deletion is destroying accounting evidence across multiple downstream flows.
- This is one of the broadest accounting-history mutation paths in the codebase.

Target service boundary:
- Accounting Engine, with project deletion requesting reversal/void behavior instead of removal.

Exact allowed files for a future Codex prompt:
- `app/Services/ProjectDeletionService.php`
- `tests/Feature/ProjectDeletionTest.php`

Forbidden changes:
- Do not rewrite project deletion architecture.
- Do not change invoice, treasury, or payroll route behavior.
- Do not create new helpers outside the existing service.

Test needed:
- A project deletion regression test that proves linked accounting documents are not force-deleted.

---

### 3) `app/Services/PayrollCalculationService.php::deletePeriod`

Current behavior:
- Payroll period deletion removes payroll-linked accounting documents and force-deletes them.

Why it is risky:
- Payroll cleanup is destroying salary-related accounting history.
- Payroll must calculate salary, but Accounting must own the ledger lifecycle.

Target service boundary:
- Accounting Engine for document reversal/void handling.

Exact allowed files for a future Codex prompt:
- `app/Services/PayrollCalculationService.php`
- `tests/Feature/PayrollPeriodsTest.php`

Forbidden changes:
- Do not change payroll calculation formulas.
- Do not alter payroll period routing or permission keys.
- Do not add a new payroll posting service.

Test needed:
- A payroll period deletion regression test that verifies posted accounting documents are preserved or voided, not force-deleted.

---

### 4) `app/Services/FiscalPeriodService.php::removeGeneratedClosingArtifacts`

Current behavior:
- Fiscal-period cleanup deletes closing/opening accounting documents and force-deletes them.

Why it is risky:
- Year-end accounting artifacts should be auditable historical records.
- Hard deletion here undermines closing workflow traceability.

Target service boundary:
- Accounting Engine, with explicit reversal/void handling for generated artifacts.

Exact allowed files for a future Codex prompt:
- `app/Services/FiscalPeriodService.php`
- `tests/Feature/FiscalPeriodClosingWorkflowTest.php`

Forbidden changes:
- Do not change fiscal period close/open route names.
- Do not alter accounting posting rules.
- Do not add new migration or document type behavior.

Test needed:
- A fiscal-period closing workflow regression test that confirms generated accounting documents are not hard-deleted.

---

### 5) `app/Services/RelatedDocumentDeletionService.php::deleteInventoryDocumentWithRelated`

Current behavior:
- Inventory document deletion cascades to accounting document deletion and line deletion.

Why it is risky:
- Inventory is mutating accounting history directly.
- Quantity and value boundaries become blurred.

Target service boundary:
- Accounting Engine for all accounting document cleanup.

Exact allowed files for a future Codex prompt:
- `app/Services/RelatedDocumentDeletionService.php`
- the closest inventory deletion feature test file if one exists

Forbidden changes:
- Do not change inventory document routes.
- Do not change stock calculation rules.
- Do not create a new inventory posting layer.

Test needed:
- An inventory deletion regression test that proves linked accounting documents remain protected from hard delete.

---

### 6) `app/Http/Controllers/InvoiceController.php::deleteDownstreamDocuments`

Current behavior:
- The controller removes downstream inventory and accounting documents, including force-deleting accounting documents.

Why it is risky:
- The controller is performing cross-module accounting cleanup directly.
- This mixes presentation-layer control with ledger history mutation.

Target service boundary:
- Accounting Engine, with invoice lifecycle cleanup delegated out of the controller.

Exact allowed files for a future Codex prompt:
- `app/Http/Controllers/InvoiceController.php`
- `tests/Feature/InvoiceVatAccountingTest.php`
- `tests/Feature/InvoiceSettlementTest.php` if needed for route coverage

Forbidden changes:
- Do not change invoice route names.
- Do not change invoice number generation.
- Do not rewrite invoice business rules.

Test needed:
- An invoice deletion regression test that confirms accounting documents are not force-deleted.

---

### 7) `app/Services/AccountingPostingService.php::deleteFinancialTransactionDocument`

Current behavior:
- The service collects related accounting documents, deletes their lines, and force-deletes the documents.

Why it is risky:
- This is a direct destructive mutation of accounting history.
- It should be reversal-only if the document is posted or otherwise historically relevant.

Target service boundary:
- Accounting Engine, but with reversal/void behavior instead of hard deletion.

Exact allowed files for a future Codex prompt:
- `app/Services/AccountingPostingService.php`
- `tests/Feature/FinancialTransactionGeneralEntriesTest.php`
- `tests/Feature/BankLedgerRepairTest.php` if the repair path is involved

Forbidden changes:
- Do not add a new posting service.
- Do not change routes or controller signatures.
- Do not alter financial transaction business semantics beyond replacing hard delete.

Test needed:
- A financial transaction deletion/repair regression test that verifies accounting documents are preserved or voided.

---

### 8) `app/Services/AccountingPostingService.php::unpost`

Current behavior:
- The service moves a posted document back to `draft` and clears posting metadata.

Why it is risky:
- This violates posted-document immutability and the reverse-only policy.

Target service boundary:
- Accounting Engine reversal workflow.

Exact allowed files for a future Codex prompt:
- `app/Services/AccountingPostingService.php`
- `tests/Feature/FiscalPeriodRestrictionTest.php`
- `tests/Feature/InvoiceVatAccountingTest.php`

Forbidden changes:
- Do not change posting creation flow.
- Do not change route names.
- Do not add new document types outside the accounting boundary.

Test needed:
- A regression test proving a posted document cannot be unposted and must instead be reversed in the future flow.

---

### 9) `app/Http/Controllers/AccountingDocumentController.php::destroy`

Current behavior:
- The controller directly calls `$accountingDocument->delete()` for non-automatic documents.

Why it is risky:
- This is direct controller-layer mutation of accounting documents.
- It bypasses the Accounting Engine boundary and can remove history if the document is posted or later becomes posted through edge cases.

Target service boundary:
- Accounting Engine, or a strict controller guard that blocks posted-document mutation entirely.

Exact allowed files for a future Codex prompt:
- `app/Http/Controllers/AccountingDocumentController.php`
- `tests/Feature/FiscalPeriodRestrictionTest.php`

Forbidden changes:
- Do not change routes.
- Do not move logic into a new service.
- Do not change view names or permissions.

Test needed:
- A controller regression test proving posted accounting documents cannot be deleted through the existing destroy route.

---

## First Safest Small Refactor Candidate

Recommended first candidate:
- `app/Http/Controllers/AccountingDocumentController.php::destroy`

Why this is the safest first step:
- It is a single controller action.
- The change can be limited to a posted-document guard.
- It does not require moving logic across modules.
- It has an existing regression test home in `tests/Feature/FiscalPeriodRestrictionTest.php`.

Smallest future Codex prompt:
- Add a posted-document guard to `AccountingDocumentController::destroy` and verify it with one regression test.

## Secondary Small Candidates

- `AccountingDocumentController::unpost`
- `AccountingPostingService::unpost`
- `AccountingPostingService::deleteFinancialTransactionDocument`
- `TreasuryService::deleteAccountingDocument`

These are next because they still mutate accounting history, but each one should be handled as a separate prompt after the first controller guard lands.

## Forbidden Plan Shapes

- No broad rewrite of posting architecture.
- No new accounting services or repositories.
- No route redesign.
- No mass migration of deletion logic in one prompt.
- No simultaneous refactor of Treasury, Payroll, Inventory, and Projects.

## Done Criteria For The Fix Plan

- Every risky direct mutation path has a ranked owner and a smallest next step.
- The first task is controller-local and test-backed.
- The next tasks move only one destructive accounting path at a time toward reversal-only behavior.
- Posted accounting documents remain the highest-protection asset in the plan.

