# Architecture Decision Rules

## When to use Controller

Only

- Request
- Authorization
- Validation
- Response

Never

Business Logic

----------------------------------------------------

## When to use Service

If business rule exists.

Examples

Create Invoice

Approve Payroll

Post Accounting Document

Transfer Inventory

----------------------------------------------------

## When to use Repository

If querying database.

Examples

Search

Filters

Reports

Statistics

----------------------------------------------------

## When to use Action

One reusable business operation.

Examples

CreateInvoiceAction

PostDocumentAction

CloseFiscalPeriodAction

----------------------------------------------------

## When to use Event

Business event happened.

Examples

InvoicePosted

PayrollApproved

EmployeeCreated

----------------------------------------------------

## When to use Listener

Reaction to Event.

Examples

Send Notification

Create Accounting Entry

Update Inventory

----------------------------------------------------

## When to use Job

Heavy Process

Examples

Export Excel

Generate PDF

Import Data

Send Email

----------------------------------------------------

## When to use Observer

Model lifecycle only.

created

updated

deleted

----------------------------------------------------

## When NOT to use Observer

Business Logic

Accounting

Payroll

Inventory

----------------------------------------------------

## When to use Livewire

UI only.

----------------------------------------------------

## When to use Blade

Rendering only.