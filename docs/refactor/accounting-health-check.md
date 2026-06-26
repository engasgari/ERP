# Accounting Module Health Check

Scope:
- `app/Models/AccountingDocument.php`
- `app/Models/AccountingDocumentLine.php`
- `app/Http/Controllers/AccountingDocumentController.php`
- Directly used accounting services and form requests

## Findings

| Severity | Location | Reason | Recommendation | Estimated Difficulty |
|---|---|---|---|---|
| Critical | `app/Http/Controllers/AccountingDocumentController.php:95-121` <br> `app/Services/AccountingPostingService.php:100-115` | Posted accounting documents can be taken back to `draft` through `unpost()`, and the controller exposes a direct delete path for non-automatic documents. This breaks the accounting rule that posted documents are read-only and can only be reversed. It also weakens audit integrity because the lifecycle is mutable after posting. | Remove destructive delete/unpost behavior for posted documents. Replace it with explicit reversal flow that creates compensating accounting entries while preserving the original posted document. Keep posted documents immutable. | High |
| Critical | `app/Services/AccountingPostingService.php:388-408` | `deleteFinancialTransactionDocument()` force-deletes accounting documents and deletes their lines first. That erases accounting history and can orphan business events that should remain auditable. | Replace hard delete with reversal or voiding logic. Preserve document and line history, and mark the financial transaction as reversed/voided instead of physically removing accounting records. | High |
| High | `app/Http/Controllers/AccountingDocumentController.php:95-121` | The controller performs state-changing operations without explicit authorization calls inside the action methods. It also mixes policy-sensitive behavior with redirect logic, which makes the security boundary less obvious and easier to regress. | Add explicit policy authorization in each mutating action, even if route middleware exists. Keep the controller thin and delegate state transitions to the service layer only after authorization succeeds. | Medium |
| Medium | `app/Http/Controllers/AccountingDocumentController.php:46-77` | `show()`, `print()`, `pdf()`, and `edit()` repeat almost the same eager-loading graph. This is duplicated controller code and makes future relation changes easy to miss in one path. | Extract the shared load graph into one private helper or repository method so all document views use the same data shape. | Low |
| Medium | `app/Http/Controllers/AccountingDocumentController.php:124-131` | `formData()` queries `ChartAccount`, `Party`, and `Project` directly from the controller. That leaks database access into the presentation layer and bypasses the repository boundary used elsewhere. | Move lookup queries into a repository or service method and let the controller only assemble the response. | Low |
| Medium | `app/Models/AccountingDocument.php:87-105` | `debit_total`, `credit_total`, and `is_balanced` are computed from the loaded `lines` collection each time the accessors are accessed. That can become expensive and can contribute to repeated collection scans or N+1-like behavior when documents are listed without careful eager loading. | Prefer query-side aggregation for list/report paths, or memoize totals when the model is hydrated with lines. Keep these accessors only for narrow detail views. | Low |
| Medium | `app/Models/AccountingDocument.php:117-121` | `scopeForBranch()` depends on `Schema::hasColumn()` inside the model. That couples an entity scope to schema inspection and makes the model responsible for infrastructure awareness. | Move schema-dependent filtering into a repository or query service, or guard the column at a higher layer where branching logic belongs. | Low |
| Low | `app/Http/Requests/StoreAccountingDocumentRequest.php:11-50` | The request accepts `status=posted` during creation. That may be intentional, but in combination with mutable post/unpost/delete flows it increases the risk of inconsistent accounting state transitions. | If posted creation is required, make the transition explicit in the service and keep the post state immutable after creation. Otherwise restrict manual creation to `draft`. | Low |

## Notes

- `app/Models/AccountingDocumentLine.php` is structurally sound for the reviewed concerns: monetary columns are cast as decimals, and the model itself does not contain obvious business logic violations in this slice.
- The most serious risk is lifecycle integrity, not calculation correctness. The current implementation allows accounting documents to be changed or removed in ways that conflict with the posted-document rules.
- No source code was modified while producing this report.
