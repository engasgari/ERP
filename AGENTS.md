# ERP — Agent Instructions

## Stack
Laravel 12, PHP 8.2, MySQL, Livewire 4, Alpine.js, Tailwind CSS 3, Vite, Vazirmatn font.

Key packages often relevant: `hekmatinasser/verta` (Jalali dates), `barryvdh/laravel-dompdf` (PDF), `maatwebsite/excel`, `laravel/breeze`.

## Commands

| Command | What |
|---|---|
| `composer test` | `config:clear` then `artisan test` — runs PHPUnit |
| `composer dev` | Concurrent: `php artisan serve`, `queue:listen`, `pail` (logs), `npm run dev` |
| `composer setup` | Fresh install from scratch |
| `npm run build` / `npm run dev` | Vite frontend build/dev |

Run `php artisan test --filter=TestName` for a single test.

## Testing

All feature tests use `DatabaseTransactions` + seed `DatabaseSeeder` (or `$this->seed(DatabaseSeeder::class)`). Admin user is `admin@aale.ir` / `password` with `admin` role. Never run tests against the app's live database.

Test files: `tests/Feature/`. Smoke test covers all parameterless GET routes. Financial reports have dedicated coverage in `FinancialReportsTest.php`.

## Persian Dates (Jalali)

Used throughout for display, filtering, and reporting. Do NOT use `Carbon` for display — use `hekmatinasser\Verta\Verta`.

Auto-loaded helpers in `app/helpers.php`:
- `gregorianToJalaliDate($carbonDate)` — display formatting
- `jalaliToGregorianDate($jalaliStr)` — user input → DB
- `formatJalaliDateSafe($value)` — safe formatting with fallback
- `getPersianMonthName($month)` — month name in Persian
- `jalaliMonthRangeGregorianSafe($year, $month)` — month bounds

Filters using Jalali dates must be normalized to Gregorian before querying.

## Architecture

- **Livewire 4** with `#[Rule]` PHP 8 attributes. Base classes in `app/Livewire/Core/UI/`: `BaseListPage`, `BaseFormPage`, `BaseReportPage`, `BaseDetailsModal`, `BaseFilterBar`, `BaseDataTable`, etc.
- **Blade components** in `resources/views/components/erp/ui/`: `page-shell`, `panel`, `page-header`, `filter-bar`, `data-table`, `details-modal`, `action-menu`, `report-viewer`, `print-layout`, `empty-state`, `status-badge`.
- **Services** in `app/Services/` — business logic.
- **Repositories** in `app/Repositories/` — query scopes.
- **Exports** in `app/Exports/` — Excel export classes.
- **Routes** in `routes/web.php` (auth group) and `routes/api.php` (auth:sanctum). Resources often have dual routing with different permissions (view vs manage).
- **Permissions** via `permission:X` middleware. Granular suffixes: `.view`, `.manage`, `.create`, `.edit`, `.delete`, `.approve`.
- **Database**: MySQL. Session, cache, and queue all use `database` driver.

## UI Conventions

- All visible text is Persian RTL.
- Use shared components, never one-off table/filter/modal markup.
- Report screens use the shared report shell, not hand-built tables.
- Reports must output normalized data to drive HTML, print, PDF, and Excel consistently.

## Key Files

| File | Purpose |
|---|---|
| `docs/project-context.md` | Full architecture reference |
| `docs/erp-ui-framework.md` | UI framework guidelines (Persian) |
| `app/helpers.php` | Jalali date + Persian digit/word helpers |
| `database/erp_full_schema.sql` | Schema snapshot |
| `routes/web.php` | All main app routes |

When adding or changing report logic, update controller, service, repository, views, and tests together. Check HTML, print, PDF, and Excel output.
