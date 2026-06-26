# Accounting Document Risk Checklist

Date: 2026-06-26

Scope reviewed:
- [AccountingDocumentController.php](../../app/Http/Controllers/AccountingDocumentController.php)
- [AccountingPostingService.php](../../app/Services/AccountingPostingService.php)
- [AccountingDocumentRepository.php](../../app/Repositories/AccountingDocumentRepository.php)
- [AccountingDocument.php](../../app/Models/AccountingDocument.php)
- [AccountingDocumentLine.php](../../app/Models/AccountingDocumentLine.php)
- [routes/web.php](../../routes/web.php)
- Related accounting Blade views under `resources/views/accounting-documents/`

## Checklist

### 1) Current edit/delete/unpost/post/reverse flows

- [x] `store()` creates documents through `AccountingPostingService::createManual()`.
- [x] `update()` updates documents through `AccountingPostingService::updateManual()` unless the document is automatic.
- [x] `destroy()` deletes documents directly from the controller for non-automatic documents.
- [x] `post()` posts documents through `AccountingPostingService::post()`.
- [x] `unpost()` moves documents back to `draft` through `AccountingPostingService::unpost()`.
- [ ] No explicit reverse flow exists in the controller, service, or routes.

Risk note:
- The current flow still allows a posted document to be changed through `unpost()` or removed through `destroy()` in the controller. That is the highest-priority accounting integrity issue.

### 2) Posted-document mutation risk

- [x] `AccountingDocumentController::destroy()` calls `$accountingDocument->delete()`.
- [x] `AccountingPostingService::unpost()` changes a posted document back to `draft` and clears `posted_at` / `posted_by`.
- [x] `routes/web.php` exposes both `DELETE /accounting-documents/{accountingDocument}` and `POST /accounting-documents/{accountingDocument}/unpost`.
- [x] `resources/views/accounting-documents/show.blade.php` renders delete and unpost actions for editable documents.
- [x] `resources/views/accounting-documents/show.blade.php` hides those actions only for automatic documents, not for posted manual documents.

Risk note:
- Posted document immutability is not enforced consistently. The current behavior is closer to mutable draft lifecycle management than to a reverse-only accounting system.

### 3) Direct ledger/accounting line mutation outside `AccountingPostingService`

- [x] `AccountingDocumentController::destroy()` removes accounting documents directly instead of delegating delete/reversal behavior to the accounting service boundary.
- [x] `AccountingPostingService::deleteFinancialTransactionDocument()` hard-deletes accounting documents and their lines.
- [x] `AccountingPostingService::replaceLines()` deletes all existing lines and recreates them.
- [ ] No direct line mutation was found in `routes/web.php`, but the route surface still exposes actions that can lead to deletion or unposting.

Risk note:
- The service is still the main mutation boundary, but it currently contains hard-delete behavior that should later be replaced with reversal-safe handling.

### 4) Controller business logic that should later move to service

- [x] `AccountingDocumentController::destroy()` contains the delete decision logic.
- [x] `AccountingDocumentController::post()` and `unpost()` are thin, but they still participate in lifecycle decisions that should stay service-owned.
- [x] `AccountingDocumentController::formData()` queries `ChartAccount`, `Party`, and `Project` directly.
- [x] `AccountingDocumentController::normalizedFilters()` converts date inputs inside the controller.
- [x] `AccountingDocumentController::redirectIfAutomatic()` contains lifecycle guard logic.

Suggested future task sizes:
- Extract `formData()` lookup queries into a repository-backed method.
- Move the `redirectIfAutomatic()` policy into a service or authorization layer.
- Split delete and reverse handling into a dedicated accounting lifecycle method.

### 5) Query/filter/report logic that should later move to repository

- [x] `AccountingDocumentRepository::paginate()` already owns the list query and eager loading.
- [x] `AccountingDocumentRepository::paginate()` also owns search, status, type, and date filters.
- [x] `AccountingDocument::scopeSearch()` and `scopeDateRange()` duplicate some repository-level filtering behavior.
- [x] `AccountingDocument::scopeForBranch()` performs schema inspection inside the model.

Suggested future task sizes:
- Consolidate list/search/date filtering in the repository only.
- Remove schema-dependent query checks from the model scope.
- Keep model scopes limited to stable, reusable query predicates.

### 6) Blade calculations or relation queries

- [x] `resources/views/accounting-documents/index.blade.php` calculates debit and credit totals with `$document->lines->sum(...)`.
- [x] `resources/views/accounting-documents/index.blade.php` builds a unique list of detail accounts with collection operations in Blade.
- [x] `resources/views/accounting-documents/show.blade.php` iterates over loaded relations and renders them directly.
- [x] `resources/views/accounting-documents/print.blade.php` calculates totals and counts inline.
- [x] `resources/views/accounting-documents/print.blade.php` uses relation traversal such as `pluck('detailAccount')` and `unique('id')`.
- [x] `resources/views/accounting-documents/form.blade.php` renders line data and account options directly, which is acceptable for presentation, but it also reflects a data-heavy form structure.

Risk note:
- The views are not querying the database directly in the files reviewed, but they do perform collection-based accounting calculations and relation traversal that should ideally come from a prepared dataset.

### 7) Suggested future refactor tasks, each small enough for one Codex prompt

- [ ] Add a dedicated reversal action for posted accounting documents and keep the original document immutable.
- [ ] Remove direct document deletion from `AccountingDocumentController` and route all lifecycle changes through the accounting service.
- [ ] Replace `AccountingPostingService::unpost()` with reversal-safe behavior or make it unavailable for posted documents.
- [ ] Extract `AccountingDocumentController::formData()` lookup queries into a repository method.
- [ ] Centralize accounting document list filters in `AccountingDocumentRepository` and remove model-level overlap.
- [ ] Replace `AccountingPostingService::deleteFinancialTransactionDocument()` hard deletes with reversible/voidable handling.
- [ ] Move Blade totals in `resources/views/accounting-documents/index.blade.php` and `print.blade.php` into a normalized dataset.
- [ ] Review `routes/web.php` accounting endpoints to ensure posted-document mutations are not exposed as ordinary edit/delete actions.

## Summary

Highest-risk issues:
- Posted documents can still be deleted or moved back to draft.
- The accounting service still contains hard-delete behavior.
- Blade views compute accounting totals inline.
- The controller still owns some lifecycle guard and query assembly logic.

Recommended next prompt-sized refactor targets:
1. Add reversal-only accounting workflow.
2. Remove direct delete/unpost behavior from the controller surface.
3. Normalize document queries and view datasets.
4. Replace hard-delete paths with audit-preserving flows.

