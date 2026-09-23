# Customer Statement Balance Bug Health Check

Date: 2026-06-26

Scope:
- `app/Services/FinancialReportService.php`
- `app/Repositories/FinancialReportRepository.php`
- `app/Http/Controllers/FinancialReportController.php`
- `app/Services/AccountingPostingService.php`
- `app/Models/Party.php`
- `app/Models/AccountingDocument.php`
- `app/Models/AccountingDocumentLine.php`
- `app/Models/Invoice.php`
- `app/Models/TreasuryTransaction.php`
- `docs/refactor/reports-registry-health-check.md`
- `tests/Feature/FinancialReportsTest.php`

Customers under review:
- `محسن اخلاصی`
- `حمید علیزاده`

## Executive Summary

The customer statement is not built from `detail_account_id`. It is built from posted accounting document lines, filtered by:

- `party_id`
- the receivables/payables account tree
- posted status only

The exact statement path is:

- `FinancialReportService::partyStatementReport()`
- `FinancialReportService::partyStatementByAccount()`
- `FinancialReportRepository::postedLineQuery()`

The most important architectural risk is that the statement groups by `party_id` and only looks at posted lines under account `1101` for customers or `2101` for suppliers, including descendants of those control accounts. If source documents attach the wrong party, omit the party, or duplicate the party record, the statement balance for only some customers can drift.

The exact row-level reason for `محسن اخلاصی` and `حمید علیزاده` cannot be proven from code alone. That requires checking the live database rows with the SQL or Tinker queries listed below.

## How the Statement Currently Finds Ledger Lines

### Customer / supplier statement

`FinancialReportController::statement()` calls:

- `FinancialReportService::partyStatementSummaries($accountId, $partyId, $filters)`

`FinancialReportService::partyStatementSummaries()` calls:

- `FinancialReportService::partyStatementByAccount($accountId, $partyId, $filters)`

For the customer path, `partyStatementReport('customer', ...)` calls:

- `partyStatementByAccount($this->accountIdLike('1101'), $filters['party_id'] ?? null, ...)`

For the supplier path, it uses:

- `partyStatementByAccount($this->accountIdLike('2101'), $filters['party_id'] ?? null, ...)`

`partyStatementByAccount()` then:

1. Expands the control account to all descendant chart accounts with `accountAndDescendantIds()`.
2. Loads posted lines from `postedLines()`.
3. Filters by `chart_account_id` in that account tree.
4. Filters by `party_id` if a party is provided.
5. Sorts by document date and line id.
6. Groups the final rows by `party_id`.
7. Sums debit and credit per party.

### Filters used

The statement uses:

- `party_id`: yes
- `chart_account_id`: yes, through the account tree filter
- account hierarchy: yes, through `accountAndDescendantIds()`
- account code: yes, indirectly through `accountIdLike('1101')` / `accountIdLike('2101')`
- `detail_account_id`: no

### Separate model clarification

There is no `app/Models/DetailAccount.php` file in the current tree. `detail_account_id` on `AccountingDocumentLine` points to `ChartAccount` through the `detailAccount()` relation.

## Whether Only Posted Accounting Documents Enter

Yes, but with an important caveat.

`FinancialReportRepository::postedLineQuery()` joins `accounting_documents` and applies:

- `where('accounting_documents.status', 'posted')`

That means:

- draft documents are excluded
- posted documents are included
- generated documents are included if they are posted

However, the query does not check:

- `accounting_documents.deleted_at`
- `accounting_documents.voided_at`
- any reversal flag or reversal relationship

Because it uses a raw join against the documents table, soft-delete behavior is not enforced by an Eloquent global scope here. A soft-deleted document can still enter the statement if it still has `status = posted` in the table.

## Can Void, Draft, Soft-Deleted, Reversed, or Generated Documents Enter?

| Document type | Can enter statement? | Why |
|---|---|---|
| Draft | No | `postedLineQuery()` filters to `status = posted`. |
| Posted | Yes | This is the only status explicitly included. |
| Soft-deleted posted | Yes, potentially | The query joins raw tables and does not filter `deleted_at`. |
| Voided posted | Yes, potentially | The query does not filter `voided_at`. |
| Reversed document | Only if still posted | There is no separate reversal filter in the statement query. |
| Generated document | Yes, if posted | Source type does not matter; posted status does. |

## Posting-Side Consistency

### Sale invoices

`AccountingPostingService::fromInvoice()` does attach `party_id` consistently on sale invoice lines.

It posts:

- receivable line with `party_id`
- revenue line with `party_id`
- VAT line with `party_id` when tax exists

It does not use `detail_account_id` on the sale invoice lines.

### Treasury receipts and payments

`AccountingPostingService::fromReceiptVoucher()`, `fromPaymentVoucher()`, and `fromTreasuryTransaction()` generally attach `party_id` to the counterparty line.

For treasury-side lines, they may attach:

- `detail_account_id` when a bank account is used
- `bank_account_id`
- `cashbox_id`

This is consistent for treasury ownership, but the customer statement still only sees rows that have the correct `party_id` and are posted under the 1101/2101 tree.

### Manual accounting documents

`AccountingPostingService::createManual()` and `updateManual()` allow line payloads to be built with optional:

- `party_id`
- `detail_account_id`

So manual accounting documents can absolutely create posted lines for a person without the expected detail account, as long as the caller omits it. The statement will still include those rows if the `party_id` and account tree match.

## Why Some Customers Can Show a Higher Credit or Otherwise Wrong Balance

The code supports several data-shape risks that can affect only specific customers:

1. The statement groups only by `party_id`.
2. Duplicate `Party` rows with similar names split the ledger across multiple ids.
3. Posted lines with the wrong `party_id` attach to the wrong customer row.
4. Posted lines with no `party_id` are invisible to the customer statement, even if they are economically related.
5. Soft-deleted or voided posted documents can still contribute if they remain `status = posted`.
6. Manual documents can bypass the expected detail-account shape.

The most likely code path responsible for the mismatch is:

- `FinancialReportService::partyStatementByAccount()`

The most likely upstream data sources are:

- `AccountingPostingService::fromInvoice()`
- `AccountingPostingService::fromReceiptVoucher()`
- `AccountingPostingService::fromPaymentVoucher()`
- `AccountingPostingService::fromTreasuryTransaction()`
- `AccountingPostingService::createManual()`
- `AccountingPostingService::updateManual()`

## Findings

| Severity | Location | Reason | Recommendation | Suggested Smallest Future Codex Task |
|---|---|---|---|---|
| Critical | `app/Services/FinancialReportService.php::partyStatementByAccount()` | The customer statement depends only on `party_id` plus the 1101/2101 account tree. It ignores `detail_account_id`, and it groups rows only by `party_id`. Any source row tagged to the wrong party, or tagged to no party, will distort only certain customer balances. | Keep the statement query narrow, but make sure source posting always uses one canonical `party_id` for the same business partner. | Add a tiny regression test that seeds one Party, one sale invoice, and one treasury receipt/payment, then asserts that the statement uses the same `party_id` rows for the customer. |
| Risky | `app/Repositories/FinancialReportRepository.php::postedLineQuery()` | The query only checks `accounting_documents.status = posted`. It does not exclude `deleted_at` or `voided_at`, so soft-deleted or voided-but-still-posted rows can still leak into the statement. | Add a posted-document safety filter if the business rule says voided or soft-deleted docs must not be visible. | Add a narrow repository test for posted-line filtering against soft-deleted and voided records. |
| Risky | `app/Models/AccountingDocument.php` and `app/Models/AccountingDocumentLine.php` | The report relies on document-level status and line-level party tags, but there is no separate `DetailAccount` model. That makes the data contract easy to misread and easy to populate inconsistently. | Keep using `ChartAccount` as the detail-account target, but document the contract clearly in future refactors. | Add a small architecture note or test fixture that proves `detail_account_id` is a `ChartAccount` relation, not a separate model. |
| Risky | `app/Services/AccountingPostingService.php::fromInvoice()`, `fromReceiptVoucher()`, `fromPaymentVoucher()`, `fromTreasuryTransaction()`, `createManual()`, `updateManual()` | Posting is mostly consistent for sale invoices and treasury flows, but manual documents can omit `party_id` or `detail_account_id`, and treasury-side lines are not customer rows. That can leave the customer statement with incomplete or split source data for only some people. | Normalize customer identity at the posting boundary and ensure the same person always resolves to the same `party_id`. | Add a focused posting test for one customer name that proves every related posted document carries the same `party_id`. |
| Unclear | Live database rows for `محسن اخلاصی` and `حمید علیزاده` | The code alone cannot prove whether these customers have duplicate `Party` rows, missing `party_id` on offsetting lines, or soft-deleted posted documents. | Run the database checks below before changing code. | Add a diagnostic test only after the live rows are understood. |

## Required Database Checks

Because the exact customer-specific imbalance depends on real rows, run these checks before changing application code.

### Tinker

```php
php artisan tinker

Party::whereIn('name', ['محسن اخلاصی', 'حمید علیزاده'])
    ->get(['id', 'code', 'detail_code', 'kind', 'name']);

AccountingDocumentLine::query()
    ->with(['document:id,number,status,document_date,deleted_at,voided_at', 'account:id,code,title', 'detailAccount:id,code,title', 'party:id,name,code'])
    ->whereHas('party', fn ($q) => $q->whereIn('name', ['محسن اخلاصی', 'حمید علیزاده']))
    ->whereHas('document', fn ($q) => $q->where('status', 'posted'))
    ->get(['id', 'accounting_document_id', 'chart_account_id', 'detail_account_id', 'party_id', 'debit', 'credit', 'description']);
```

### SQL

```sql
select id, name, code, detail_code, kind
from parties
where name in ('محسن اخلاصی', 'حمید علیزاده');
```

```sql
select p.id, p.name, count(*) as line_count, sum(adl.debit) as total_debit, sum(adl.credit) as total_credit
from accounting_document_lines adl
join parties p on p.id = adl.party_id
join accounting_documents ad on ad.id = adl.accounting_document_id
where ad.status = 'posted'
  and p.name in ('محسن اخلاصی', 'حمید علیزاده')
group by p.id, p.name
order by p.name;
```

If those two people appear multiple times in `parties`, the statement balance can be split across multiple `party_id` values. If the posted lines exist but have a missing or wrong `party_id`, the statement will undercount or overcount that customer.

## Recommended First Safe Fix Candidate

The safest first candidate is to verify and normalize the `party_id` source rows for the two named customers before touching the report query.

Why this is safest:

- It is source-data centric, not report-engine centric.
- It avoids changing the statement contract before the live rows are understood.
- It reduces the chance of breaking other reports that rely on the same `party_id` grouping.

Suggested smallest future Codex task:

- Add a narrow regression test that seeds one party, one sale invoice, and one treasury receipt/payment for that party, then asserts the statement totals by `party_id` match the posted lines exactly.

