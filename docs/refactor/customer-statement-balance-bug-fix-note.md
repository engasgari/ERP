# Customer Statement Balance Bug Fix Note

Date: 2026-06-26

Scope:
- `app/Services/FinancialReportService.php`
- `app/Repositories/FinancialReportRepository.php`
- `app/Services/AccountingPostingService.php`
- live party/accounting rows for:
  - `محسن اخلاصی`
  - `حمید علیزاده`

## Conclusion

This is a live-data / source-document problem, not a report-formula bug.

The customer-statement report code still filters posted lines by the receivable/payable tree as designed. The extra balance comes from posted supplier-side documents already tied to the same `party_id` values, which affects any party-level balance view that sums all accounting lines for that person/company.

## What Was Found

For both named parties, the live database contains posted accounting documents that are not customer receivable lines. They are ordinary expense/service purchase documents with these characteristics:

- `chart_account_id = 5201` on the expense side
- `chart_account_id = 2101` on the payable side
- `party_id` set to the same party record
- `status = posted`
- `deleted_at = null`
- `voided_at = null`

Observed examples:

- `محسن اخلاصی`
  - `ACC-00259`
  - `ACC-00260`
  - `ACC-00261`
- `حمید علیزاده`
  - `ACC-00256`
  - `ACC-00257`
  - `ACC-00262`

Those are posted purchase/service expense documents, not receivable customer invoices.

## Why the Balance Looks High

The same party record is being used on both customer and supplier-side activity. That is enough to inflate a party-level balance or general statement that groups by `party_id`, even when the customer-statement report itself is still filtering receivables correctly.

In other words:

- the report query is not inventing the balance;
- the balance is coming from real posted rows tied to those parties;
- the issue is the live source data or the business decision to reuse the same party for supplier-side purchase/service documents.

## Safe Correction Approach

Do not hard-delete, force-delete, or unpost posted accounting documents.

Use the existing reversal-only workflow:

1. Identify the source purchase/service documents that should not belong to the party balance under review.
2. Reverse those posted documents using the current accounting reversal flow.
3. Recreate the source document with the correct party assignment, or split the business partner into separate customer and supplier party records if that is the intended accounting design.
4. Recheck the statement after reversal and re-entry.

## Important Boundary Note

The following should not be changed for this issue:

- customer-statement report formula
- repository posted-line filtering
- posted-document delete/unpost behavior

Those are not the root cause here.

## When Code Would Need a Change

Only if the business rule is that a single `party_id` must never carry both customer and supplier balances.

If that is the policy, then the model/data-entry rules need to be tightened in a separate task. This note does not change application code because the current rows already explain the inflated balance.

