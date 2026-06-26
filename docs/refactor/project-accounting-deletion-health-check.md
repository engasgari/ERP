# Project Accounting Deletion Health Check

Date: 2026-06-26

Scope:
- `app/Services/ProjectDeletionService.php`
- related project deletion test coverage
- accounting document deletion paths triggered during project cleanup

## Executive Summary

`ProjectDeletionService` is a high-risk cleanup service because it removes accounting history as part of project deletion. The service does not distinguish draft from posted accounting documents in its direct deletion branches, and it also delegates to other services that remove accounting documents and lines during project cleanup. The current test coverage proves the cleanup path deletes linked accounting records, but it does not protect posted accounting documents from being hard-deleted.

## 1) Methods In `ProjectDeletionService` That Delete Accounting Documents Or Lines

| Method | Location | What it deletes | Delete style | Can it remove posted accounting documents? | Triggered by |
|---|---|---|---|---|---|
| `deleteAccountingDocumentsForProjectLines` | `app/Services/ProjectDeletionService.php:46-65` | Finds accounting documents linked to `accounting_document_lines.project_id = $projectId`, deletes all lines through `lines()->delete()`, then hard-deletes the document with `forceDelete()`. | Direct hard delete | Yes. There is no posted-document guard before `forceDelete()`. | Project cleanup during `ProjectController::destroy`. |
| `deleteOverheadAllocationAccountingDocuments` | `app/Services/ProjectDeletionService.php:67-87` | Reads `project_overhead_allocations.accounting_document_id`, deletes linked lines with `lines()->delete()`, then hard-deletes the document with `forceDelete()`. | Direct hard delete | Yes. There is no posted-document guard before `forceDelete()`. | Project cleanup during `ProjectController::destroy`. |
| `deleteInvoiceWithRelatedDocuments` | `app/Services/ProjectDeletionService.php:99-127` | Collects invoice and related inventory accounting document IDs, deletes each document’s lines with `lines()->delete()`, then hard-deletes the document with `forceDelete()`. Also deletes invoice lines and the invoice record. | Direct hard delete | Yes. The method does not check whether the accounting document is posted. | Invoice cleanup inside project cleanup. |
| `deleteFinancialTransactions` | `app/Services/ProjectDeletionService.php:137-145` | Does not delete accounting documents directly itself, but delegates to `AccountingPostingService::deleteFinancialTransactionDocument($transaction)` and then deletes the financial transaction. | Delegated hard delete | Yes, through the delegated service path. The project service does not block posted documents before delegation. | Financial transaction cleanup during project cleanup. |
| `deleteTreasuryTransactions` | `app/Services/ProjectDeletionService.php:148-181` | Collects treasury-linked accounting document IDs, deletes each document’s lines with `lines()->delete()`, then hard-deletes the document with `forceDelete()`. Then force-deletes or deletes the treasury transaction. | Direct hard delete | Yes. There is no posted-document guard before `forceDelete()`. | Treasury cleanup during project cleanup. |
| `deleteInventoryDocuments` and `deleteProductionOrders` | `app/Services/ProjectDeletionService.php:129-135`, `app/Services/ProjectDeletionService.php:184-197` | These methods do not delete accounting documents directly, but they call `RelatedDocumentDeletionService::deleteInventoryDocumentWithRelated()`, which is part of the same accounting-removal cleanup chain. | Indirect accounting cleanup | Likely yes, via the related document deletion path. The project service does not enforce posted-document protection here. | Inventory and production cleanup during project cleanup. |

## 2) Every Place Those Methods Are Called

| Caller | Location | Call chain |
|---|---|---|
| `ProjectController::destroy` | `app/Http/Controllers/ProjectController.php:104-109` | Resolves `ProjectDeletionService` and calls `delete($project)`. |
| `ProjectDeletionService::delete` | `app/Services/ProjectDeletionService.php:25-43` | Calls `deleteAccountingDocumentsForProjectLines`, `deleteOverheadAllocationAccountingDocuments`, `deleteInvoices`, `deleteInventoryDocuments`, `deleteFinancialTransactions`, `deleteTreasuryTransactions`, and `deleteProductionOrders` in one transaction. |
| `ProjectDeletionService::deleteInvoices` | `app/Services/ProjectDeletionService.php:89-97` | Calls `deleteInvoiceWithRelatedDocuments` for every invoice in the project. |
| `ProjectDeletionService::deleteInventoryDocuments` | `app/Services/ProjectDeletionService.php:129-135` | Delegates each inventory document to `RelatedDocumentDeletionService::deleteInventoryDocumentWithRelated`. |
| `ProjectDeletionService::deleteProductionOrders` | `app/Services/ProjectDeletionService.php:184-197` | Delegates source inventory documents to `RelatedDocumentDeletionService::deleteInventoryDocumentWithRelated`. |

## 3) What Accounting Documents And Lines It Deletes

- `deleteAccountingDocumentsForProjectLines` deletes all `accounting_document_lines` for the found documents, then force-deletes the matching `accounting_documents` rows.
- `deleteOverheadAllocationAccountingDocuments` deletes all lines of the overhead allocation document, then force-deletes the accounting document.
- `deleteInvoiceWithRelatedDocuments` deletes all lines for invoice-linked accounting documents, then force-deletes each accounting document, and finally deletes the invoice lines and invoice row.
- `deleteFinancialTransactions` removes the financial transaction after delegating accounting-document deletion to `AccountingPostingService`.
- `deleteTreasuryTransactions` deletes all lines for treasury-linked accounting documents, then force-deletes the accounting documents, and finally force-deletes or deletes the treasury transaction.

## 4) Can It Delete Posted Accounting Documents?

Yes.

Reasons:
- Every direct accounting-document deletion branch in `ProjectDeletionService` uses `AccountingDocument::withTrashed()->find(...)` and then calls `lines()->delete()` followed by `forceDelete()`.
- None of the direct branches checks `status`, `posted_at`, or `posted_by` before deletion.
- The financial-transaction cleanup path delegates to `AccountingPostingService::deleteFinancialTransactionDocument`, which is already identified in the architecture audit as a hard-delete path.

## 5) What Business Flow Triggers It

- The public project-destroy flow triggers the entire cleanup sequence.
- The controller action is `ProjectController::destroy`, which calls `ProjectDeletionService::delete($project)`.
- The service runs inside a database transaction and performs project cleanup across accounting, treasury, invoice, inventory, production, work log, overhead allocation, and project snapshot data.

## 6) Cleanup Type Classification

- `deleteAccountingDocumentsForProjectLines`: project cleanup.
- `deleteOverheadAllocationAccountingDocuments`: project cleanup and overhead cleanup.
- `deleteInvoiceWithRelatedDocuments`: invoice cleanup within project cleanup.
- `deleteFinancialTransactions`: financial transaction cleanup within project cleanup.
- `deleteTreasuryTransactions`: treasury cleanup within project cleanup.
- `deleteInventoryDocuments` and `deleteProductionOrders`: inventory cleanup and production cleanup within project cleanup.

## 7) Existing Tests That Appear Related

| Test file | Coverage observed | Relevance |
|---|---|---|
| `tests/Feature/ProjectDeletionTest.php:33-286` | Builds a project with an invoice, accounting documents, a financial transaction, treasury-related data, inventory data, production orders, and work logs, then deletes the project and asserts all related records are removed. | This is the primary coverage for the current deletion behavior. It confirms that hard-deletion occurs. |

## 8) Test Gap

- There is no regression test that proves posted accounting documents survive project deletion cleanup.
- There is no regression test that asserts a project cleanup branch refuses to hard-delete a posted accounting document or posted accounting lines.
- The current project deletion test creates draft accounting documents for the direct project and overhead cases, so it does not exercise the posted-document immutability rule.
- There is no focused test for the delegated financial-transaction accounting deletion path either.

## 9) Safest Future Refactor Option

The safest next step is to block hard deletion of posted accounting documents inside `ProjectDeletionService` before any broader architectural cleanup.

Recommended ordering:
- First add a posted-document guard in the most direct branch, `deleteAccountingDocumentsForProjectLines`.
- Then extend the same guard to `deleteOverheadAllocationAccountingDocuments`, `deleteInvoiceWithRelatedDocuments`, and `deleteTreasuryTransactions`.
- Keep non-posted draft cleanup behavior unchanged for now.
- Defer reversal-only redesign to a later prompt so the project cleanup flow stays small and testable.

Why this is safest:
- It protects the most important accounting invariant first.
- It avoids a broad rewrite of project deletion.
- It keeps the diff small and local to one service and one test file.

## 10) Smallest Future Codex Task

Allowed files for the next smallest prompt:
- `app/Services/ProjectDeletionService.php`
- `tests/Feature/ProjectDeletionTest.php`

Suggested next task:
- Add a posted-accounting-document guard to `ProjectDeletionService::deleteAccountingDocumentsForProjectLines`, and add one regression test in `tests/Feature/ProjectDeletionTest.php` proving a posted accounting document linked to a project is not hard-deleted during project cleanup.

## Notes

- This report intentionally does not change application code.
- The current project deletion flow is broader than accounting cleanup, but the accounting-risk center is the direct `forceDelete()` behavior on accounting documents and lines.
