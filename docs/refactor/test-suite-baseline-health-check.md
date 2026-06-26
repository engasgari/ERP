# Test Suite Baseline Health Check

Date: 2026-06-26

Scope:
- `tests/Unit/NavigationBreadcrumbServiceTest.php`
- `tests/Feature/AttendanceRequestNormalizationTest.php`
- `tests/Feature/FinancialReportsTest.php`
- `tests/Feature/InvoiceVatAccountingTest.php`
- `tests/Feature/PayrollPeriodsTest.php`
- `tests/Feature/ProjectDeletionTest.php`
- `tests/Feature/TreasuryBankTransactionTest.php`

Baseline note:
- This report is based on the currently observed failing test suite output and the related code paths reviewed during the baseline pass.
- Several tests emit `PDO::MYSQL_ATTR_SSL_CA` deprecation notices. Those notices are noisy, but they are not assertion failures and should not be treated as broken behavior in this baseline.
- The latest `ManagementReportService` extraction does not appear to be the primary cause of the observed failures. Most failures are older contract drift, with only the report assertion bucket being tangentially adjacent to reporting architecture.

## Executive Summary

The current baseline shows three real failure clusters:

- navigation breadcrumb expectations drifting from the current route metadata;
- attendance request normalization contract drift;
- accounting and reporting assertion mismatches.

I did not capture confirmed failures in the fiscal period setup group, the payroll calculation setup group, or the missing class/service group from the observed baseline run. Those areas should still be watched, but they are not current blockers in the captured failure set.

## 1) Missing Class / Service Failures

No confirmed failures were captured in this group.

| Status | Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| None observed | None captured in the baseline run | No `Class not found`, container binding, or missing service failures were present in the observed output. | No | No current evidence of a missing class/service regression. | Keep this bucket as a guardrail for future runs. | Low | Re-run the suite after the next refactor and only investigate if a real missing binding or class error appears. |

## 2) Tests Expecting Features or APIs That Do Not Exist Yet

This group captures stale contract expectations around breadcrumbs and attendance normalization behavior.

| Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|
| `tests/Unit/NavigationBreadcrumbServiceTest.php::test_it_builds_project_creation_breadcrumbs` | Breadcrumb labels or route metadata do not match the test’s expected `داشبورد / پروژه‌ها / ثبت جدید` sequence. | Unrelated | Breadcrumb registry or route-label mapping is out of sync with current navigation metadata. | Align the breadcrumb map with the current route names and canonical labels. | Medium | Review the breadcrumb definition for the project creation route and update only the affected breadcrumb entry. |
| `tests/Unit/NavigationBreadcrumbServiceTest.php::test_it_builds_financial_report_breadcrumbs` | The financial report breadcrumb labels do not match the expected `داشبورد / گزارش‌های مالی / سود و زیان` path. | Unrelated | Breadcrumb metadata for financial reports is stale or incomplete. | Normalize breadcrumb titles for report routes in one place. | Medium | Update the breadcrumb mapping for the financial report route group without touching page rendering. |
| `tests/Unit/NavigationBreadcrumbServiceTest.php::test_it_builds_report_center_breadcrumbs` | The report-center breadcrumb path does not match the expected `داشبورد / مرکز گزارش‌ها / لیست پرسنل` labels. | Unrelated | The navigation metadata for the report center appears to be based on a different current route/title contract. | Keep breadcrumb generation centralized and align the route title mapping. | Medium | Adjust the breadcrumb entry for the report center route only. |
| `tests/Feature/AttendanceRequestNormalizationTest.php::test_hourly_leave_request_persists_zero_total_days_and_duration_minutes` | The hourly leave normalization contract does not persist the expected zero day count and minute duration. | Unrelated | Attendance request normalization is not consistently enforcing the expected hourly leave shape. | Move normalization rules into the attendance request/service boundary and keep controller behavior thin. | Medium | Inspect the hourly leave normalization path and reconcile the persisted `total_days` and duration values. |
| `tests/Feature/AttendanceRequestNormalizationTest.php::test_daily_leave_request_computes_total_days_and_duration_minutes` | Daily leave duration math does not match the expected stored values. | Unrelated | Attendance duration calculation is drifting between request input and persistence. | Centralize leave duration computation in one service path. | Medium | Update the daily leave normalization path so the stored minutes and days are derived once. |
| `tests/Feature/AttendanceRequestNormalizationTest.php::test_hourly_mission_request_persists_zero_total_days_and_duration_minutes` | Hourly mission normalization does not persist the expected zero day count and minute duration. | Unrelated | Mission request normalization is not aligned with the test’s contract. | Reuse the same normalization rules for mission and leave request variants. | Medium | Review the hourly mission request flow and align its persisted duration fields with the existing contract. |
| `tests/Feature/AttendanceRequestNormalizationTest.php::test_daily_mission_request_computes_total_days_and_duration_minutes` | Daily mission duration math does not match the expected persisted values. | Unrelated | The daily mission calculation path likely duplicates or diverges from the leave path. | Consolidate the mission normalization logic into a shared service method. | Medium | Fix the daily mission normalization branch so the saved totals come from one shared calculator. |

## 3) Fiscal Period Validation / Setup Failures

No confirmed failures were captured in this group.

| Status | Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| None observed | None captured in the baseline run | No fiscal-period validation or setup failure appeared in the observed output. | No | No current evidence of a fiscal-period regression in the captured baseline. | Keep this as a watchlist area because fiscal validation affects accounting, payroll, and project flows. | Low | Re-run the suite around fiscal-period scenarios only if a new refactor touches date-range validation. |

## 4) Accounting Behavior Mismatch Failures

These failures are the most important because they touch posted-document semantics, VAT posting, and accounting repair/reversal behavior.

| Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| `tests/Feature/InvoiceVatAccountingTest.php::test_sale_invoice_posts_value_added_tax_to_tax_payable_account` | The sale invoice VAT posting does not land in the expected tax payable account contract. | Unrelated | The invoice posting flow and VAT account selection are not fully aligned with the accounting engine contract. | Keep VAT posting logic inside the accounting posting path and verify account selection with a focused regression test. | Critical | Inspect the sale invoice posting branch and reconcile the VAT account mapping without changing unrelated invoice behavior. |
| `tests/Feature/InvoiceVatAccountingTest.php::test_posted_accounting_documents_are_reversed_not_unposted` | The posted-document lifecycle is not matching the reversal-only rule expected by the test. | Unrelated | Posted document mutation rules are either not enforced consistently or are enforced in the wrong layer. | Block direct unpost behavior for posted documents and preserve reversal-only handling. | Critical | Review the posted-document update/unpost path and ensure it exits through the reversal contract only. |
| `tests/Feature/InvoiceVatAccountingTest.php::test_repair_command_rebuilds_legacy_sale_invoice_posting_with_cogs` | The repair command does not rebuild the legacy sale invoice posting with the expected COGS behavior. | Unrelated | The repair flow is not fully reproducing the historical accounting posting contract. | Keep repair logic isolated and make it rebuild only the accounting documents it owns. | High | Revisit the legacy sale repair branch and align the recreated COGS posting with the current accounting-engine rules. |

## 5) Payroll Calculation / Setup Failures

No confirmed failures were captured in this group.

| Status | Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| None observed | None captured in the baseline run | `tests/Feature/PayrollPeriodsTest.php` was part of the inspected suite, but no confirmed payroll failure was present in the captured output. | No | No current evidence of a payroll calculation regression in the observed baseline. | Keep payroll logic covered by focused regression tests, especially for attendance, leave, and payment accounting. | Low | Re-run payroll-only scenarios if a future refactor touches calculation or posting boundaries. |

## 6) Report Assertion / Data Mismatch Failures

These failures point to dataset or output contract drift rather than missing functionality.

| Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| `tests/Feature/FinancialReportsTest.php::test_account_statement_page_shows_posted_ledger_lines` | The rendered account statement page does not surface the expected posted ledger-line trace or document-number text in the current output. | Tangential only | The statement page dataset or route selection does not match the test’s expected report contract. | Keep the statement page on a single normalized dataset and reconcile the line source used by screen rendering. | Medium | Inspect the account-statement dataset builder and make the page output include the same posted line source the test expects. |
| `tests/Feature/FinancialReportsTest.php::test_financial_reports_use_service_invoice_postings_consistently` | The project income total remains `0.0` instead of reflecting the expected posted invoice value. | Tangential only | Report aggregation is not seeing the same posting dataset as the invoice accounting flow. | Normalize the data contract between invoice postings and report aggregation. | High | Reconcile the project income aggregation with the invoice posting source used by financial reports. |

## 7) Deprecated Warnings That Are Not Real Failures

| Failing test file and test name | Failure reason | Related to latest `ManagementReportService` refactor? | Likely architectural cause | Safest future fix strategy | Priority | Smallest future Codex task |
|---|---|---|---|---|---|---|---|
| Multiple test files in the baseline run | Repeated `PDO::MYSQL_ATTR_SSL_CA` deprecation notices are emitted during test execution. | Unrelated | The database configuration or driver options still include a deprecated PDO constant. | Remove the deprecated option from the test/database configuration path so it stops polluting the suite output. | Low | Update the database connection setup to stop passing `PDO::MYSQL_ATTR_SSL_CA` in environments that do not need it. |

## Baseline Interpretation

- The strongest regressions are in accounting behavior, especially posted-document lifecycle rules and VAT posting.
- The report failures are data-contract mismatches, not a sign that the latest report-service extraction broke the suite.
- The breadcrumb and attendance issues look like stale contract expectations that should be handled in small, isolated fixes.
- Deprecated notices are widespread but should be treated separately from real test failures.

