# Test Suite Baseline Health Check

Date: 2026-06-26

Scope:
- `tests/Unit/NavigationBreadcrumbServiceTest.php`
- `tests/Feature/AccountingDocumentDetailAccountTest.php`
- `tests/Feature/AttendanceRequestNormalizationTest.php`
- `tests/Feature/FinancialReportsTest.php`
- `tests/Feature/InvoiceVatAccountingTest.php`
- `tests/Feature/PayrollPeriodsTest.php`
- `tests/Feature/ProjectDeletionTest.php`
- `tests/Feature/TreasuryBankTransactionTest.php`

Baseline note:
- This report reflects the actual failing test output that was pasted in the thread, plus the related files inspected during the baseline pass.
- Repeated `PDO::MYSQL_ATTR_SSL_CA` deprecation notices appeared in the suite output. They are noisy, but they are not assertion failures.
- The latest `ManagementReportService` extraction is not the main cause of these failures. The observed issues are mostly missing service/class contracts, fiscal-period setup, payroll setup, and accounting/report contract drift.

## Executive Summary

The current baseline has five real failure groups:

- a missing breadcrumb service/class;
- fiscal-period validation blocking attendance setup;
- payroll setup/calculation failures;
- accounting behavior mismatch failures;
- report assertion/data mismatch failures.

Deprecated PDO notices are present throughout the suite, but they should be treated separately from real failures.

## 1) Missing Class / Service Failures

| Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| `tests/Unit/NavigationBreadcrumbServiceTest.php::test_it_builds_project_creation_breadcrumbs` | `Target class [App\Services\NavigationBreadcrumbService] does not exist.` | Unrelated | The test expects a concrete breadcrumb service, but the class or binding is missing from the application contract. | Restore the missing service class or container binding and keep breadcrumb generation isolated. | High | Reintroduce `App\Services\NavigationBreadcrumbService` as a thin service and rerun only the breadcrumb unit tests. |
| `tests/Unit/NavigationBreadcrumbServiceTest.php::test_it_builds_financial_report_breadcrumbs` | Same missing service/class failure. | Unrelated | Same as above. | Same as above. | High | Restore the missing breadcrumb service contract for financial report routes. |
| `tests/Unit/NavigationBreadcrumbServiceTest.php::test_it_builds_report_center_breadcrumbs` | Same missing service/class failure. | Unrelated | Same as above. | Same as above. | High | Restore the missing breadcrumb service contract for report-center routes. |

## 2) Features / APIs Missing or Not Yet Aligned

No separate feature/API gap was needed once the breadcrumb failure was classified correctly as a missing service/class issue. The observed baseline did not show a distinct second bucket here.

## 3) Fiscal Period Validation / Setup Failures

The attendance normalization failures are not pure attendance-contract failures in this baseline. They are blocked first by fiscal-period validation.

| Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| `tests/Feature/AttendanceRequestNormalizationTest.php::test_hourly_leave_request_persists_zero_total_days_and_duration_minutes` | `ValidationException: تاریخ انتخاب‌شده خارج از بازه مالی فعال است.` from `app/Services/FiscalPeriodService.php:81`. | Unrelated | The request reaches fiscal-period validation before the attendance normalization contract can complete. | Seed or resolve the active fiscal period in the test/setup path first, then revisit the attendance normalization assertion if needed. | High | Align the attendance test setup with the active fiscal period contract before changing request normalization logic. |
| `tests/Feature/AttendanceRequestNormalizationTest.php::test_daily_leave_request_computes_total_days_and_duration_minutes` | Same fiscal-period validation failure. | Unrelated | Same as above. | Same as above. | High | Fix the attendance setup so the request lands inside the active fiscal period. |
| `tests/Feature/AttendanceRequestNormalizationTest.php::test_hourly_mission_request_persists_zero_total_days_and_duration_minutes` | Same fiscal-period validation failure. | Unrelated | Same as above. | Same as above. | High | Fix the attendance setup so the mission request can proceed past fiscal validation. |
| `tests/Feature/AttendanceRequestNormalizationTest.php::test_daily_mission_request_computes_total_days_and_duration_minutes` | Same fiscal-period validation failure. | Unrelated | Same as above. | Same as above. | High | Fix the attendance setup so the mission request can proceed past fiscal validation. |

## 4) Payroll Calculation / Setup Failures

The payroll failures in the baseline output split across two setup problems: fiscal-period validation in the surrounding attendance flow, and missing `PayrollCalculation` rows in the payroll setup path.

| Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| `tests/Feature/PayrollPeriodsTest.php::test_payroll_period_calculation_uses_work_logs` | The payroll period workflow did not reach a stable calculation state in the captured output. | Unrelated | Payroll setup is not consistently producing the calculation rows the test expects, and the surrounding date/setup flow is fragile. | Ensure the payroll period is seeded with the required calculation rows before asserting the work-log summary. | High | Inspect the payroll period setup path and make sure `PayrollCalculation` rows are created before the assertion runs. |
| `tests/Feature/PayrollPeriodsTest.php::test_payroll_payment_creates_separate_accounting_document_after_calculation` | The payment flow did not reach the expected accounting-document creation state. | Unrelated | The payroll calculation/payment chain is missing the prerequisite rows or is blocked by upstream setup. | Keep payroll calculation and payroll payment as separate stages with the required seed data in between. | High | Verify the calculation stage creates the rows needed for the payment stage to post its accounting document. |
| `tests/Feature/PayrollPeriodsTest.php::test_hourly_employee_receives_only_hours_times_rate` | The hourly payroll assertion did not stabilize because the setup path is incomplete. | Unrelated | Some cases are blocked by fiscal-period validation; others by missing `PayrollCalculation` rows. | Keep the hourly wage computation isolated from setup guards and seed the required payroll rows first. | High | Add the missing payroll setup data before asserting the hourly pay contract. |
| `tests/Feature/PayrollPeriodsTest.php::test_unapproved_payroll_period_can_be_deleted_and_recalculated` | Recalculation/deletion behavior did not complete cleanly in the captured output. | Unrelated | Missing calculation rows and setup order issues make the period state unstable. | Make the unapproved-period path create or reuse the same calculation rows before delete/recalculate assertions. | High | Revisit the payroll recalculation setup so the period has the expected `PayrollCalculation` records. |

## 5) Accounting Behavior Mismatch Failures

These are real accounting contract mismatches, not missing fixtures.

| Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| `tests/Feature/AccountingDocumentDetailAccountTest.php::test_it_can_create_and_edit_documents_with_detail_accounts` | Expected description `سند با تفصیل ویرایش شد`, but the actual persisted description stayed `سند با تفصیل`. | Unrelated | The edit/update flow is not persisting the updated description for detail-account documents as the test expects. | Keep the existing edit flow and verify the update path writes the changed description back to the document. | Medium | Inspect the document update path and make sure the edited description is saved for detail-account documents. |
| `tests/Feature/InvoiceVatAccountingTest.php::test_sale_invoice_posts_value_added_tax_to_tax_payable_account` | VAT account mapping mismatch. | Unrelated | The invoice posting path is selecting the wrong tax account or not using the expected accounting mapping contract. | Keep VAT mapping inside the accounting posting boundary and align the account selection rules. | Critical | Reconcile the sale invoice VAT account mapping without changing unrelated invoice behavior. |
| `tests/Feature/InvoiceVatAccountingTest.php::test_posted_accounting_documents_are_reversed_not_unposted` | `undefined method App\Services\AccountingPostingService::reverse()` in the observed output. | Unrelated | The posted-document lifecycle expects a reverse API that is not currently available on the service. | Add or restore the expected reverse contract before changing lifecycle behavior further. | Critical | Restore the `AccountingPostingService::reverse()` contract expected by the posted-document flow. |
| `tests/Feature/InvoiceVatAccountingTest.php::test_repair_command_rebuilds_legacy_sale_invoice_posting_with_cogs` | Missing accounting command contract in the repair path. | Unrelated | The repair flow depends on an artisan command that is not currently registered or available. | Keep the repair path isolated and reintroduce only the missing command contract. | High | Restore the missing accounting repair command used by the legacy sale invoice rebuild path. |

## 6) Report Assertion / Data Mismatch Failures

These failures point to dataset or output contract drift rather than missing functionality.

| Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| `tests/Feature/FinancialReportsTest.php::test_account_statement_page_shows_posted_ledger_lines` | The account statement page is missing the expected posted document number in the rendered output. | Tangential only | The screen dataset or the route-to-dataset mapping does not match the test’s contract. | Keep the account statement on one normalized dataset and make the screen render the same posted line source the test expects. | Medium | Inspect the account-statement dataset builder and ensure the posted document number is carried into the rendered page. |
| `tests/Feature/FinancialReportsTest.php::test_financial_reports_use_service_invoice_postings_consistently` | `project->total_income` remains `0.0` instead of the expected `750000.0`. | Tangential only | The report aggregation is not reading the same invoice-posting source used by the business flow. | Normalize the data contract between invoice postings and report aggregation. | High | Reconcile the project income aggregation with the invoice posting source used by financial reports. |

## 7) Deprecated Warnings That Are Not Real Failures

| Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| Multiple test files in the baseline run | Repeated `PDO::MYSQL_ATTR_SSL_CA` deprecation notices were emitted. | Unrelated | Database configuration still passes a deprecated PDO constant in the test environment. | Remove the deprecated PDO option from the connection config path used by the suite. | Low | Update the database connection setup so `PDO::MYSQL_ATTR_SSL_CA` is no longer emitted in the test environment. |

## Baseline Interpretation

- The most urgent real regression is the missing `NavigationBreadcrumbService` class or binding because it is isolated and blocks a unit test directly.
- Attendance failures are setup-gated by fiscal-period validation, so they should be fixed before treating them as pure normalization bugs.
- Payroll failures are a mix of setup and row-creation problems, not a single arithmetic bug.
- Accounting behavior mismatches are the highest-risk domain issues because they touch posted-document lifecycle, VAT mapping, and repair/reversal contracts.
- Report failures are dataset-contract mismatches and are only tangentially related to the latest report-service extraction.

## Recommended First Safe Fix

`tests/Unit/NavigationBreadcrumbServiceTest.php` should be the first safe fix target.

Why this first:

- It is isolated and unit-level.
- It does not touch accounting, payroll, treasury, or inventory behavior.
- The failure is a missing service/class contract, so the blast radius is much smaller than the finance-related failures.
- Restoring the breadcrumb service binding/class should quickly unblock three tests without affecting business logic.

Smallest future Codex task:

- Restore `App\Services\NavigationBreadcrumbService` as a thin service or binding that satisfies the existing breadcrumb unit tests, then rerun only `tests/Unit/NavigationBreadcrumbServiceTest.php`.

