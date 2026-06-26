# Livewire Review

| Severity | Location | Reason | Recommendation | Estimated Difficulty |
| --- | --- | --- | --- | --- |
| High | `app/Livewire/Employees/Form.php:103-178`, `app/Livewire/Employees/Index.php:42-88`, `app/Livewire/Payroll/Periods.php:52-130`, `app/Livewire/WorkLogs/Manager.php:42-57` | Livewire components are mutating models, creating records, deleting records, and coordinating cross-module workflows. That is business logic, not UI state. | Move those operations to services/actions and let Livewire call them through a small use-case API. | Medium |
| Medium | `app/Livewire/Items/Index.php:168-193`, `app/Livewire/Parties/Index.php:122-133`, `app/Livewire/BankAccounts/Index.php:104-136`, `app/Livewire/Payroll/Periods.php:172-198` | Several components assemble rich related summaries from multiple tables during render. They are drifting from UI state into reporting logic. | Preload summary DTOs from repositories or dedicated services and keep Livewire focused on interaction state. | Medium |
| Medium | `app/Livewire/Employees/SelfService.php:141-178` | The self-service component queries attendance, missions, summaries, payroll, and leave balances directly, which makes it a mini dashboard service. | Move the aggregation into a dedicated self-service query object or report service. | Medium |

