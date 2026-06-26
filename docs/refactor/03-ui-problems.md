# UI Problems

| Severity | Location | Reason | Recommendation | Estimated Difficulty |
| --- | --- | --- | --- | --- |
| High | `resources/views/invoices/show.blade.php:9-14, 188`, `resources/views/invoices/print.blade.php:292-296, 428`, `resources/views/accounting-documents/print.blade.php:66-69`, `resources/views/salaries/show.blade.php:67, 153, 263`, `resources/views/financial-transactions/project-report.blade.php:35, 42, 49, 145-146, 332` | Blade templates are doing aggregation, relationship access, and presentation math. That breaks the rendering-only rule and makes the UI harder to reason about. | Precompute all totals and counts in controllers/services and pass plain view models to Blade. | Medium |
| High | `resources/views/financial-reports/partials/report-shell.blade.php:138-206`, `app/Http/Controllers/FinancialReportController.php:67-78, 160-179` | The report shell decides column sets and row shape using report keys and section titles. The view is effectively acting as a schema interpreter. | Normalize the report contract in the service layer and make the Blade template render a stable shape only. | High |
| Medium | `resources/views/invoices/show.blade.php`, `resources/views/invoices/print.blade.php`, `resources/views/accounting-documents/print.blade.php` | Similar totals, headers, and print formatting logic are duplicated across multiple views. | Extract shared ERP presentation components or shared presenter data so print/show pages stay in sync. | Medium |

