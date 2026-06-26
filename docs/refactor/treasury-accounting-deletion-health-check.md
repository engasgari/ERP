# Treasury Accounting Deletion Health Check

Date: 2026-06-26

Scope:
- `app/Services/TreasuryService.php`
- `app/Services/AccountingPostingService.php`
- `tests/Feature/TreasuryBankTransactionTest.php`

## 1) Exact implementation of `TreasuryService::deleteAccountingDocument`

`TreasuryService::deleteAccountingDocument(TreasuryTransaction $transaction)` does the following in plain language:

- It builds a list of accounting document IDs from two sources:
  - the treasury transaction’s own `accounting_document_id`
  - any accounting documents whose `source_type` is `TreasuryTransaction` and whose `source_id` matches the treasury transaction ID
- It removes empty values and duplicates from that ID list
- For each matching accounting document:
  - it loads the document, including soft-deleted documents
  - it deletes all related accounting document lines with `lines()->delete()`
  - it permanently removes the accounting document with `forceDelete()`

## 2) Every place this method is called

The method is called in exactly two places:

- `app/Services/TreasuryService.php::updateAndPost()`
- `app/Services/TreasuryService.php::deleteWithAccounting()`

## 3) What accounting documents and lines it deletes

It can delete:

- the accounting document linked directly on the treasury transaction
- any accounting document created from the treasury transaction itself through `source_type = TreasuryTransaction::class`
- every line belonging to those documents

So the mutation is not limited to one document record. It deletes the lines first, then deletes the whole accounting document.

## 4) Whether it can delete posted accounting documents

Yes.

There is no check in `deleteAccountingDocument()` that blocks posted documents.

Because it queries with `AccountingDocument::withTrashed()` and then force-deletes the matching record, a posted accounting document can be permanently removed if it is referenced by the treasury transaction.

## 5) Whether it uses `forceDelete`

Yes.

The method explicitly calls `forceDelete()` on each matching accounting document after deleting its lines.

## 6) What business flow triggers it

It is triggered by:

- `updateAndPost()` during a treasury transaction update flow
  - the existing accounting document is removed
  - the treasury transaction is reset to draft-like state
  - a new accounting document is then posted
- `deleteWithAccounting()` during a treasury transaction delete flow
  - the linked accounting document is removed
  - the treasury transaction itself is deleted

So the triggers are:

- update
- delete

It is not a rollback helper and it is not a reversal flow.

## 7) What existing tests cover this behavior

`tests/Feature/TreasuryBankTransactionTest.php` covers:

- creating and posting treasury bank receipts
- posting both sides of treasury transfers

But it does not cover:

- `TreasuryService::deleteAccountingDocument()`
- treasury transaction update-and-repost deletion of the old accounting document
- treasury delete-with-accounting hard deletion
- the behavior of the helper against posted accounting documents

## 8) What test gap exists

The gap is a missing regression test for treasury-side accounting document deletion.

In particular, there is no existing test proving that:

- a treasury transaction update does not hard-delete a posted accounting document that should remain auditable
- a treasury transaction delete path does not force-delete posted accounting history
- the method deletes the intended accounting document lines and documents only when that behavior is intentionally allowed

## 9) Safest future refactor option

The safest minimal option is:

- delegate treasury cleanup to `AccountingPostingService`
- preserve the accounting document and mark the treasury transaction voided or otherwise logically cancelled

Among the available choices, this is safer than:

- blocking only posted-document deletion while leaving hard delete for drafts

Why:

- the current behavior is already deleting accounting history directly
- treasury should not own the lifecycle of accounting records
- a voided treasury transaction keeps the business event auditable without erasing ledger history

## 10) Exact smallest future Codex task with allowed files

Smallest next Codex task:

- update `app/Services/TreasuryService.php` so `deleteAccountingDocument()` no longer hard-deletes posted accounting documents
- add one focused regression test to `tests/Feature/TreasuryBankTransactionTest.php` that proves the treasury update/delete flow preserves posted accounting history or blocks the deletion path

Allowed files for that future task:

- `app/Services/TreasuryService.php`
- `tests/Feature/TreasuryBankTransactionTest.php`

Forbidden changes for that future task:

- do not change routes
- do not change controllers
- do not change models
- do not change migrations
- do not change views
- do not add new services or repositories
- do not change treasury posting semantics beyond the minimum needed to stop hard-deleting posted accounting history

