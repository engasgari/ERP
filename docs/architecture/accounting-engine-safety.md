# Accounting Engine Safety Plan

## 1) Accounting Engine Ownership

Accounting Engine owns:

- Chart of accounts
- Accounting documents
- Journal entries
- General ledger
- Subsidiary ledger
- Trial balance
- Financial statements
- Fiscal periods
- Posting and reversal workflows

Accounting Engine is the system of record for financial value. No other module owns ledger state.

## 2) Posted Accounting Document Immutability Rule

Once an accounting document is posted:

- It becomes read-only
- It cannot be edited
- It cannot be deleted
- It cannot be rewritten in place

Any change to a posted document must happen through a controlled reversal or compensating document, not by mutating the original posted record.

## 3) Reversal-Only Policy For Posted Documents

Posted accounting documents may only be handled through reversal.

Allowed:

- Reverse a posted document
- Create a compensating document
- Leave the original posted document intact

Forbidden:

- Unposting a posted document back to draft
- Editing line amounts after posting
- Deleting posted documents
- Force deleting posted accounting history

Reversal must preserve auditability and the full posting trail.

## 4) Modules That May Request Accounting Posting

The following modules may request posting actions through Accounting Engine:

- Treasury
- Sales & Purchase
- Inventory
- Payroll
- Projects & Production
- Assets, where applicable

These modules may initiate accounting requests, but they do not own the ledger and must not post directly outside Accounting Engine.

## 5) Modules That Must Never Directly Create Ledger Or Accounting Document Lines

The following modules must never directly create ledger entries or accounting document lines:

- Treasury
- Inventory
- Payroll
- Sales & Purchase
- Human Resources
- Attendance
- Projects & Production

If any of these modules need financial impact, they must send the request through Accounting Engine services.

## 6) Required Service Boundary For `AccountingPostingService`

`AccountingPostingService` must remain the only business service responsible for:

- Creating manual accounting documents
- Updating draft accounting documents
- Posting accounting documents
- Reversing posted accounting documents
- Creating automatic accounting documents from approved business events
- Auditing accounting lifecycle changes

It must also:

- Use database transactions for every state-changing workflow
- Validate fiscal period state before changing accounting status
- Enforce balance rules before posting
- Preserve historical audit records

It must not:

- Render HTML
- Query Blade views
- Act as a UI helper
- Allow direct mutation of posted documents without reversal

## 7) Required Repository Boundary For Accounting Document Queries

`AccountingDocumentRepository` must be the database query boundary for accounting document retrieval and filtering.

It should own:

- Document list queries
- Search and filter behavior
- Date range filtering
- Relationship eager loading
- Report-source query preparation

It should not:

- Perform business posting logic
- Create or mutate accounting documents
- Decide reversal rules
- Change posting state

## 8) Forbidden Flows

The following flows are forbidden:

- Edit posted accounting documents
- Delete posted accounting documents
- Unpost posted accounting documents back to draft
- Direct ledger mutation from Treasury
- Direct ledger mutation from Inventory
- Direct ledger mutation from Payroll
- Direct ledger mutation from Sales
- Direct creation of accounting document lines from non-accounting modules
- Force deletion of posted accounting history

If any business module needs a financial outcome, it must pass through Accounting Engine.

## 9) Safe Future Refactor Steps

### AccountingDocumentController

Safe refactor direction:

- Keep the controller thin
- Authorize and validate at the edge
- Delegate all accounting state changes to `AccountingPostingService`
- Remove any direct delete or unpost behavior for posted documents

Do not:

- Add business logic to the controller
- Move posting calculations into the controller
- Add direct ledger manipulation here

### AccountingPostingService

Safe refactor direction:

- Split lifecycle methods by intent if needed
- Introduce explicit reversal behavior
- Keep transactional boundaries inside the service
- Preserve audit trails and posted-document immutability

Do not:

- Convert posted documents back to draft
- Hard delete accounting documents
- Bypass fiscal period checks
- Bypass balance validation

### AccountingDocumentRepository

Safe refactor direction:

- Consolidate query patterns
- Normalize eager loading for views and reports
- Keep filtering and searching in the repository

Do not:

- Add posting logic
- Add reversal logic
- Add accounting state transitions

### Treasury

Safe refactor direction:

- Treasury should create accounting requests through the Accounting Engine boundary
- Keep cash and bank ownership in Treasury
- Preserve transaction and reconciliation behavior

Do not:

- Write to ledger directly
- Edit accounting documents directly
- Delete accounting records to simulate reversal

### Payroll

Safe refactor direction:

- Payroll should calculate salary and produce posting requests
- Accounting should post salary outcomes
- Keep payroll calculation separate from accounting persistence

Do not:

- Post directly to the ledger from Payroll
- Mutate accounting documents from payroll UI or Livewire components

### Inventory

Safe refactor direction:

- Inventory should own quantity documents and stock movement
- Accounting should own financial value
- Inventory should request accounting impact through the accounting boundary

Do not:

- Mix quantity updates with ledger edits
- Create accounting lines directly from inventory actions

## 10) Done Criteria For Future Code Refactors

A future accounting refactor is only done when all of the following are true:

- Posted accounting documents are immutable
- Reversal is the only supported way to change posted financial history
- Treasury, Inventory, Payroll, and Sales request accounting actions without direct ledger access
- `AccountingPostingService` owns posting and reversal business logic
- `AccountingDocumentRepository` owns query and eager-loading behavior
- Controllers remain thin and focused on authorize, validate, call service, return response
- Report paths consume normalized data rather than reimplementing accounting logic
- Regression tests cover create, update, post, reverse, and forbidden mutation flows
- Public route names and permission keys remain stable unless explicitly changed by the architect

