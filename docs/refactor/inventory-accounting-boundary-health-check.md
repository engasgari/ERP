# Inventory / Accounting Boundary Health Check

Date: 2026-06-26

Source note: the requested root-level `docs/04-Accounting.md` and `docs/06-Inventory.md` files were not present. I used the architecture copies under `docs/architecture/` plus the existing implementation to prepare this audit.

## Summary

Inventory ownership is mostly respected in the stock posting path, but there are several destructive cross-boundary flows where inventory cleanup can hard-delete accounting history. The biggest risk is not normal stock posting; it is cleanup and deletion flows that cross from inventory, invoice, project, or fiscal-period code into accounting document deletion.

I did not find inventory code directly creating, updating, deleting, or force-deleting accounting documents outside the cleanup paths below. The main safe accounting boundary consumer is `AccountingPostingService::fromInventoryDocument()`, which reads inventory value and creates accounting entries without mutating stock quantity.

## Inventory Area Map

- Inventory documents and stock movements
  - `app/Http/Controllers/InventoryDocumentController.php`
  - `app/Services/InventoryPostingService.php`
  - `app/Models/InventoryDocument.php`
  - `app/Models/InventoryDocumentLine.php`
  - `app/Services/RelatedDocumentDeletionService.php`
- Warehouses and stock quantity
  - `app/Models/Warehouse.php`
  - `app/Http/Controllers/WarehouseController.php`
  - `app/Livewire/Warehouses/Index.php`
  - `resources/views/warehouses/show.blade.php`
- Items and stock presence
  - `app/Livewire/Items/Index.php`
  - `resources/views/livewire/items/index.blade.php`
- Production consumption
  - `app/Http/Controllers/ProductionOrderController.php`
  - `app/Models/ProductionMaterialConsumption.php`
- Inventory reports and Kardex-style views
  - `app/Http/Controllers/ManagementReportController.php`
  - `resources/views/management-reports/warehouse-cardex.blade.php`
  - `resources/views/management-reports/warehouse-inventory.blade.php`
  - `resources/views/inventory-documents/index.blade.php`
- Cross-boundary accounting consumers
  - `app/Services/AccountingPostingService.php`
  - `app/Services/FiscalPeriodService.php`
  - `app/Http/Controllers/InvoiceController.php`
  - `app/Services/ProjectDeletionService.php`

## Findings

| Severity | Location | Reason | Recommendation | Suggested Smallest Future Codex Task | Estimated Difficulty |
|---|---|---|---|---|---|
| Critical | `app/Services/RelatedDocumentDeletionService.php::deleteInventoryDocumentWithRelated()` | This method collects linked accounting document ids, deletes accounting lines with `lines()->delete()`, and force-deletes the accounting documents before deleting the inventory document. It is the clearest direct inventory-to-accounting destructive path in the codebase. | Keep inventory cleanup, but guard posted accounting documents before any hard delete and keep accounting deletion behind the accounting boundary. | Add a posted-document guard before `forceDelete()` inside `deleteInventoryDocumentWithRelated()` and add one regression test for a posted linked document. | Small |
| Critical | `app/Http/Controllers/InvoiceController.php::deleteDownstreamDocuments()` | Invoice cleanup deletes linked inventory documents, then gathers related accounting ids and force-deletes them too. This can erase stock history and accounting history from a sales/purchase delete path. | Move destructive cleanup into a dedicated boundary method later and block posted accounting documents before deletion. | Add a posted-document guard for invoice-linked accounting cleanup without changing the invoice route or delete flow. | Small to Medium |
| Critical | `app/Services/ProjectDeletionService.php::deleteAccountingDocumentsForProjectLines()`, `deleteOverheadAllocationAccountingDocuments()`, `deleteInvoiceWithRelatedDocuments()`, `deleteTreasuryTransactions()` | Project deletion cascades into accounting document deletion from project lines, overhead allocations, invoices, treasury transactions, and inventory-related cleanup. The service currently force-deletes accounting records from a project cleanup flow. | Keep the project cleanup orchestration, but split accounting destruction behind a single guarded helper and never delete posted accounting documents. | Add one shared posted-document guard for every accounting hard-delete branch in `ProjectDeletionService`. | Medium |
| Critical | `app/Services/FiscalPeriodService.php::createOpeningInventoryDocuments()` and `removeGeneratedClosingArtifacts()` | A financial period service creates inventory opening documents from year-end balances and later deletes inventory documents during closing artifact cleanup. That means a financial service is directly mutating inventory documents. | Preserve the fiscal workflow, but separate inventory document creation/deletion into inventory-owned boundaries when refactoring later. | Extract the inventory-opening and inventory-cleanup steps into an inventory service wrapper with a posted-document guard on any accounting cleanup. | Medium |
| Risky | `app/Http/Controllers/ProductionOrderController.php::update()` | The controller updates material consumption quantities, creates inventory consumption documents, calculates `line_total` from `actual_quantity * unit_cost`, and then calls accounting posting. It mixes quantity, valuation, and posting orchestration in one place. | Keep the posting call delegated, but move the production-to-inventory orchestration out of the controller later. | Extract the consumption-document creation block into a small service method and keep the accounting posting call unchanged. | Medium |
| Risky | `app/Http/Controllers/InventoryDocumentController.php::store()`, `update()`, `syncLines()`, `assertManualIssueStock()` | The controller creates and updates inventory documents, deletes and recreates lines, computes `line_total`, checks available quantity, and conditionally posts accounting. That is valid behavior, but it is too much orchestration for a controller. | Keep behavior intact, but move document lifecycle orchestration into a service later and leave the controller as a thin entry point. | Extract `syncLines()` and the posting/reposting orchestration into an inventory service without changing routes or accounting behavior. | Medium |
| Unclear | `app/Models/Warehouse.php::getInventoryAttribute()`, `app/Models/Project.php::getTotalWarehouseCostAttribute()`, `app/Models/ProductionMaterialConsumption.php::getActualCostAttribute()` | These accessors calculate quantity, total value, average price, warehouse cost, and actual cost inside models. The math is not wrong, but it is hidden inside accessors and therefore harder to audit and reuse. | Prefer moving these calculations into repository or report datasets later so the model layer stays simple. | Extract one accessor calculation, starting with `Warehouse::getInventoryAttribute()`, into a reusable query/service helper. | Small |
| Unclear | `app/Http/Controllers/ManagementReportController.php::warehouseCardexData()`, `warehouseDocumentTotals()`, `warehouseInventoryData()` | The controller builds inventory report datasets by summing quantities and values, calculating running balances, and computing average price. This is report logic, but it lives in a controller. | Keep the output unchanged, but move dataset assembly to a report service/repository layer later. | Move one report dataset method, starting with `warehouseInventoryData()`, behind a normalized reporting service boundary. | Medium |
| Unclear | `resources/views/warehouses/show.blade.php` | The view sums `total_value`, counts inventory documents, and displays average price and total value from model accessors. Blade is doing some display-level aggregation that should ideally be precomputed. | Pass prepared totals into the view instead of calculating them in Blade. | Replace the inline `collect(...)->sum('total_value')` usage with a precomputed summary array from the controller. | Small |
| Unclear | `resources/views/inventory-documents/index.blade.php` | The view calculates document total value with `sum('line_total')` and renders quantities and line totals directly from the collection. It is still presentation, but it duplicates report math in Blade. | Keep Blade rendering only and move totals into the controller or a report DTO later. | Replace the inline inventory document total calculation with a prepared field from the controller. | Small |
| Unclear | `app/Livewire/Items/Index.php`, `app/Livewire/Warehouses/Index.php`, `resources/views/livewire/items/index.blade.php`, `resources/views/livewire/warehouses/index.blade.php` | These UI pieces query related counts and display stock quantities or inventory counts. They are mostly safe, but they still reach into inventory-related data while acting as UI state holders. | Keep them display-focused and avoid moving any valuation or quantity math into Livewire in future changes. | If calculation appears later, move it out of Livewire and into a service or repository before rendering. | Small |

## Boundary Notes

- `app/Services/AccountingPostingService.php::fromInventoryDocument()` is the correct accounting boundary consumer for inventory value. It reads inventory document lines, computes the amount, creates the accounting document, and updates the inventory document foreign key. It does not mutate inventory quantity or stock movement.
- `app/Services/InventoryPostingService.php` owns quantity availability checks and inventory document creation. Its `line_total` calculation belongs to inventory valuation, not to accounting ledger mutation.
- The most dangerous behavior in the current code is not normal inventory posting. It is hard deletion of accounting documents from inventory/project/invoice/fiscal-period cleanup flows.

## Recommended First Safe Refactor Candidate

`app/Services/RelatedDocumentDeletionService.php::deleteInventoryDocumentWithRelated()`

Why this is the best first step:

- It is a single, isolated service method.
- It contains the clearest inventory-to-accounting boundary breach.
- A guard can be added without changing routes, controllers, or public behavior for draft data.
- It is small enough to complete in one Codex prompt and easy to test.

Suggested next change:

- Add a posted-accounting-document guard before any accounting line delete or force delete.
- Keep draft-document cleanup unchanged for now.
- Add one regression test for a posted accounting document linked to an inventory document cleanup path.
