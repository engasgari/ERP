# Accounting Direct Mutation Audit

Date: 2026-06-26

Scope searched:
- `app/`
- direct accounting document and line mutation paths
- posted-document mutation paths

## Findings

| File path | Method / function | What mutation happens | Safe or risky | Should later move to `AccountingPostingService`? | Smallest future Codex task |
|---|---|---|---|---|---|
| `app/Services/AccountingPostingService.php` | `createManual`, `updateManual`, `post`, `unpost`, `automatic` | Creates accounting documents, updates draft documents, posts documents, and in `unpost()` moves a posted document back to `draft` while clearing `posted_at` / `posted_by`. `automatic()` also stamps source linkage. | Mixed. `createManual`, `updateManual`, and `post` are expected in this service. `unpost()` is risky because it violates posted-document immutability. | No. This is already the correct boundary, but `unpost()` should be replaced by reversal-only behavior. | Split `unpost()` into a reversal-safe path and keep posted documents immutable. |
| `app/Services/AccountingPostingService.php` | `fromInvoice`, `fromInventoryDocument`, `fromReceiptVoucher`, `fromPaymentVoucher`, `fromTreasuryTransaction`, `fromFinancialTransaction`, `fromSalary`, `fromPayrollPayment` | Creates posted accounting documents from business events and writes the resulting `accounting_document_id` back onto the source model. | Safe if the source event is approved and the transaction is balanced, but still business-critical. | No. The accounting document creation itself belongs here. The source-link updates should stay adjacent to the posting flow. | Extract one source-specific posting path at a time if future cleanup is needed. |
| `app/Services/AccountingPostingService.php` | `deleteFinancialTransactionDocument` | Hard-deletes accounting documents and their lines via `lines()->delete()` and `forceDelete()`. | Risky. This destroys accounting history and auditability. | Yes. The destructive accounting part should be replaced with reversal/void behavior inside the accounting boundary. | Replace `forceDelete()` with a void/reversal-safe flow for financial transaction documents. |
| `app/Services/AccountingPostingService.php` | `replaceLines` | Deletes all existing document lines and recreates them. | Safe only for draft documents inside the transaction; risky if ever reused on posted documents. | No, but it should remain draft-only inside this service. | Add an explicit posted-document guard before line replacement. |
| `app/Http/Controllers/AccountingDocumentController.php` | `destroy` | Calls `$accountingDocument->delete()` directly from the controller for non-automatic documents. | Risky. This is direct document mutation from the controller layer. | Yes. Deletion rules should be enforced in the accounting service boundary or blocked entirely for posted documents. | Remove direct controller deletion for posted documents and keep only reversal-safe paths. |
| `app/Services/TreasuryService.php` | `deleteAccountingDocument` | Deletes accounting documents and their lines for treasury transactions with `lines()->delete()` and `forceDelete()`. | Risky. Treasury is mutating accounting history directly. | Yes. Treasury should request accounting cleanup through the accounting boundary, not delete documents itself. | Replace treasury-side accounting deletion with a single accounting reversal/void call. |
| `app/Services/RelatedDocumentDeletionService.php` | `deleteInventoryDocumentWithRelated` | Deletes related accounting documents and their lines while removing inventory documents. | Risky. Inventory-related accounting history is being hard-deleted. | Yes. The accounting-document portion should move to the accounting boundary. | Extract the accounting cleanup from inventory deletion into a reversal-safe accounting method. |
| `app/Services/ProjectDeletionService.php` | `deleteAccountingDocumentsForProjectLines`, `deleteOverheadAllocationAccountingDocuments`, `deleteInvoiceWithRelatedDocuments`, `deleteFinancialTransactions` | Hard-deletes accounting documents and lines tied to project cleanup flows. | Risky. Project deletion is cascading into accounting history removal. | Yes. The accounting deletion pieces should be delegated to Accounting Engine. | Replace one project accounting hard-delete path with reversal-only cleanup. |
| `app/Services/PayrollCalculationService.php` | `deletePeriod` | Deletes payroll-linked accounting documents for calculations and payments with `lines()->delete()` and `forceDelete()`. | Risky. Payroll cleanup is erasing posted accounting evidence. | Yes. The accounting part should move to the accounting boundary. | Convert one payroll accounting deletion branch from hard-delete to reversal/void. |
| `app/Services/FiscalPeriodService.php` | `createAccountingDocument` | Creates posted accounting documents and their lines for closing/opening workflows. | Safe as an accounting-engine responsibility, but it exists outside `AccountingPostingService`. | Yes, if you want all accounting document construction centralized. At minimum, the document assembly should be harmonized with Accounting Engine. | Move one fiscal-period accounting-document creation path into a centralized accounting workflow. |
| `app/Services/FiscalPeriodService.php` | `removeGeneratedClosingArtifacts` | Deletes accounting documents and their lines for generated closing/opening artifacts via `forceDelete()`. | Risky. It removes accounting history tied to period close/open flows. | Yes. The accounting cleanup belongs in the accounting boundary. | Replace generated closing artifact deletion with a reversible/voided workflow. |
| `app/Http/Controllers/InvoiceController.php` | `deleteDownstreamDocuments` | Deletes inventory-linked accounting documents and lines when an invoice is removed. | Risky. The controller is deleting accounting history directly through a helper. | Yes. The accounting cleanup should be delegated to Accounting Engine. | Move invoice downstream accounting cleanup out of the controller into one accounting service call. |
| `app/Http/Controllers/SalaryController.php` | `deleteSalaryAccountingDocument` | Clears the salary’s `accounting_document_id` and deletes the linked accounting document lines and the document itself. | Risky. This directly removes salary accounting history. | Yes. Salary cleanup should not delete accounting documents directly. | Replace salary accounting document deletion with reversal-only handling and a detached link update. |

## Not found

- No direct `AccountingDocumentLine::create()` hits were found in application code.
- No direct `AccountingDocumentLine::insert()` hits were found in application code.
- In application code, accounting line creation happens through relation `->lines()->create()` in services that already own document construction.

## Summary

- The safest direct accounting mutations are the ones already inside `AccountingPostingService` for draft creation, posting, and automatic document generation.
- The highest-risk paths are hard deletes and posted-document demotion, especially in treasury, payroll, project deletion, fiscal-period cleanup, invoice deletion, and salary cleanup flows.
- The smallest future Codex task for the risky paths is to replace one hard-delete branch at a time with reversal-only or void-safe behavior, keeping the rest of the flow intact.

