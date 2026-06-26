# Performance

| Severity | Location | Reason | Recommendation | Estimated Difficulty |
| --- | --- | --- | --- | --- |
| High | `resources/views/financial-transactions/project-report.blade.php:35, 42, 49, 145-146, 332`, `resources/views/salaries/show.blade.php:67, 153, 263`, `resources/views/accounting-documents/print.blade.php:66-69` | Views are issuing aggregation work at render time. This creates extra queries or forces repeated collection scans during page generation. | Move summary calculations into the controller/service and pass cached aggregates to the view. | Medium |
| High | `app/Services/FinancialReportService.php:1160-1161, 1187-1197, 1215-1269, 1473-1493, 1614-1634` | Large report paths materialize collections and then group/filter them in memory. That is risky for large ledgers and long date ranges. | Push grouping and filtering into repository queries where possible, and paginate or cache expensive report shapes. | High |
| Medium | `app/Http/Controllers/WorkLogExcelController.php:24-425`, `app/Http/Controllers/ItemController.php:80-580` | Spreadsheet import and parsing logic is handled inside controllers with a lot of synchronous processing. | Move file parsing and import normalization into jobs or dedicated services, especially for large uploads. | Medium |
| Medium | `app/Livewire/Items/Index.php:168-193`, `app/Livewire/Parties/Index.php:122-133`, `app/Livewire/BankAccounts/Index.php:104-136` | Several Livewire components compute related counts and summaries by querying multiple models during a single render path. | Cache or precompute related summaries, or load them in a single repository query. | Medium |

