# Report Engine

| Severity | Location | Reason | Recommendation | Estimated Difficulty |
| --- | --- | --- | --- | --- |
| High | `app/Http/Controllers/FinancialReportController.php:67-78, 160-179`, `resources/views/financial-reports/partials/report-shell.blade.php:179-206` | HTML, PDF, and Excel rendering do not share a single stable dataset contract. The controller and Blade template infer schema from report-specific keys and titles. | Define a normalized report DTO in the service layer and have all output formats consume the same structure. | High |
| High | `app/Services/FinancialReportService.php:30-1767` | The report engine is effectively a monolith that owns cataloging, data access, normalization, formatting, and several report families. | Split the service by report family and keep a small orchestration layer that dispatches to focused report builders. | High |
| Medium | `app/Exports/FinancialReportExport.php:11-34`, `app/Http/Controllers/FinancialReportController.php:62-78` | Excel export depends on section metadata and fallback headings instead of a fixed export schema. That makes export consistency brittle when a report shape changes. | Store export rows and headings in the service contract explicitly and validate them before exporting. | Medium |
| Medium | `resources/views/financial-transactions/project-report.blade.php`, `resources/views/financial-reports/statement.blade.php`, `resources/views/financial-reports/print/*.blade.php` | Some report views still perform filtering and aggregation logic rather than rendering a prebuilt normalized dataset. | Move all report math into the report service and keep the views as dumb renderers. | Medium |

