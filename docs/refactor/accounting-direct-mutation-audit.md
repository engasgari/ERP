# Accounting Direct Mutation Audit

Date: 2026-06-26

Scope searched:
- `app/`
- direct accounting document and accounting line mutation paths
- posted-document mutation paths

## Findings

| File path | Class and method name | Exact mutation found | Safe, risky, or unclear? | Why it matters architecturally | Should later move to `AccountingPostingService`? | Suggested smallest future Codex task |
|---|---|---|---|---|---|---|
| `app/Services/AccountingPostingService.php` | `AccountingPostingService::createManual`, `updateManual`, `post`, `unpost`, `automatic` | Creates accounting documents, updates draft documents, posts documents, and `unpost()` moves a posted document back to `draft` while clearing `posted_at` and `posted_by`. `automatic()` also writes the source linkage. | Mixed | `createManual`, `updateManual`, and `post` are the correct accounting boundary. `unpost()` breaks posted-document immutability and conflicts with reverse-only rules. | No for creation/posting; yes for replacing `unpost()` | Replace `unpost()` with reversal-only behavior for posted documents. |
| `app/Services/AccountingPostingService.php` | `AccountingPostingService::fromInvoice`, `fromInventoryDocument`, `fromReceiptVoucher`, `fromPaymentVoucher`, `fromTreasuryTransaction`, `fromFinancialTransaction`, `fromSalary`, `fromPayrollPayment` | Creates posted accounting documents from business events and writes the resulting `accounting_document_id` back onto the source model. | Safe, but critical | This is the intended Accounting Engine entry point for cross-module financial impact, so it is high-value but still sensitive. | No | Extract one source-specific posting path only if a future cleanup needs isolation. |
| `app/Services/AccountingPostingService.php` | `AccountingPostingService::deleteFinancialTransactionDocument` | Loads accounting documents and hard-deletes them using `lines()->delete()` and `forceDelete()`. | Risky | Hard-deleting accounting documents destroys audit history and bypasses reverse-only accounting rules. | Yes | Replace the hard delete with a void or reversal-safe workflow. |
| `app/Services/AccountingPostingService.php` | `AccountingPostingService::replaceLines` | Deletes all existing lines with `lines()->delete()` and recreates them with `lines()->create(...)`. | Unclear for draft, risky if reused elsewhere | This is acceptable only for draft rewrites inside the transactional accounting boundary. It would be a boundary violation if reused on posted documents. | No, but keep it inside the service | Add an explicit posted-document guard before line replacement. |
| `app/Http/Controllers/AccountingDocumentController.php` | `AccountingDocumentController::destroy` | Calls `$accountingDocument->delete()` directly from the controller for non-automatic documents. | Risky | The controller is mutating accounting documents directly instead of delegating lifecycle control to the accounting boundary. | Yes, or block it entirely for posted documents | Remove direct controller deletion for posted documents and keep only reversal-safe paths. |
| `app/Services/TreasuryService.php` | `TreasuryService::deleteAccountingDocument` | Deletes accounting document lines and force-deletes accounting documents linked to treasury transactions. | Risky | Treasury is removing accounting history directly, which conflicts with the rule that Treasury requests accounting operations but does not own the ledger. | Yes | Replace treasury-side accounting deletion with a single Accounting Engine reversal or void call. |
| `app/Services/RelatedDocumentDeletionService.php` | `RelatedDocumentDeletionService::deleteInventoryDocumentWithRelated` | Deletes related accounting documents and lines while removing inventory documents. | Risky | Inventory cleanup is cascading into accounting-history deletion, blurring quantity/value boundaries. | Yes | Extract the accounting cleanup into a reversal-safe accounting method. |
| `app/Services/ProjectDeletionService.php` | `ProjectDeletionService::deleteAccountingDocumentsForProjectLines`, `deleteOverheadAllocationAccountingDocuments`, `deleteInvoiceWithRelatedDocuments`, `deleteFinancialTransactions` | Hard-deletes accounting documents and lines during project cleanup. | Risky | Project deletion is erasing accounting evidence across multiple downstream flows and bypassing the accounting boundary. | Yes | Replace one project accounting hard-delete path with a reversal-only cleanup branch. |
| `app/Services/PayrollCalculationService.php` | `PayrollCalculationService::deletePeriod` | Deletes payroll-linked accounting documents with `lines()->delete()` and `forceDelete()`. | Risky | Payroll cleanup is erasing salary accounting history instead of preserving it through reversal or voiding. | Yes | Convert one payroll accounting deletion branch from hard delete to reversal-safe handling. |
| `app/Services/FiscalPeriodService.php` | `FiscalPeriodService::createAccountingDocument` | Creates posted accounting documents and their lines for closing/opening workflows. | Safe, but outside the preferred single-service boundary | This is valid accounting behavior, but it duplicates accounting-document construction outside `AccountingPostingService`, making the boundary less consistent. | Yes, if centralization is desired later | Move one fiscal-period accounting-document creation path into a centralized accounting workflow. |
| `app/Services/FiscalPeriodService.php` | `FiscalPeriodService::removeGeneratedClosingArtifacts` | Deletes accounting documents and lines for generated closing/opening artifacts using `lines()->delete()` and `forceDelete()`. | Risky | Generated closing artifacts are part of accounting history and should not be hard-deleted. | Yes | Replace generated closing artifact deletion with a reversible or voided workflow. |
| `app/Http/Controllers/InvoiceController.php` | `InvoiceController::deleteDownstreamDocuments` | Deletes downstream inventory-linked accounting documents and lines, including force-deletion of accounting documents. | Risky | The controller is performing cross-module accounting cleanup directly, which violates the controller/service boundary. | Yes | Move invoice downstream accounting cleanup into one accounting service call. |
| `app/Http/Controllers/SalaryController.php` | `SalaryController::deleteSalaryAccountingDocument` | Clears the salary `accounting_document_id`, deletes the linked accounting document lines, and deletes the document itself. | Risky | Salary cleanup is directly removing accounting history, which should remain auditable. | Yes | Replace salary accounting document deletion with reversal-only handling and a detached link update. |

## Not found

- No direct `AccountingDocumentLine::insert()` hits were found in application code.
- No direct `AccountingDocumentLine::create()` hits were found outside relation-based service flows.
- No direct `AccountingDocument::update()` or `AccountingDocument::delete()` calls were found outside the controller/service paths listed above.

## Summary

- The safe core is `AccountingPostingService` for draft creation, posting, and automatic posting from approved business events.
- The risky core is every hard-delete path that removes accounting documents or lines, especially when triggered by Treasury, Payroll, Projects, Fiscal Period cleanup, Invoice deletion, or Salary cleanup.
- The smallest future Codex task for the highest-risk items is to replace one hard-delete branch at a time with reversal-only or void-safe behavior, keeping the rest of the flow intact.
