# Security

| Severity | Location | Reason | Recommendation | Estimated Difficulty |
| --- | --- | --- | --- | --- |
| High | `routes/web.php:114-116`, `routes/web.php:182-187` | Mutating `inventory-documents`, `production-orders`, and `project-boms` routes are guarded by view permissions instead of manage permissions. That gives write access to users who should only be able to view. | Split read and write route groups and require `*.manage` or equivalent permissions for create/update/delete actions. | Low |
| High | `app/Policies/` | Only a small subset of modules has policies. Major modules such as invoices, items, projects, employees, work logs, warehouses, bank accounts, chart accounts, users, and roles do not have module policies. | Add policies for the missing modules and wire them into controllers or route authorization where appropriate. | High |
| Medium | `app/Http/Middleware/EnsureUserHasPermission.php` | The middleware is fine structurally, but the project relies on route middleware alone in many places and does not consistently use policy checks on resource actions. | Use policies for model-bound authorization in addition to permission middleware, especially for destructive actions. | Medium |

