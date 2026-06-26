# Coding Standards

## General

- PSR-12
- Laravel Convention
- SOLID
- DRY
- KISS

---

## Controller

Allowed

- Validation
- Authorization
- Service Call

Forbidden

- SQL
- Business Logic

---

## Service

Allowed

- Business Logic
- Transactions
- Events

Forbidden

- HTML
- Blade

---

## Repository

Allowed

- Query
- Filters

Forbidden

- Business Calculation

---

## Model

Allowed

- Relations
- Casts
- Scopes

Forbidden

- Complex Business Logic

---

## Naming

Controllers

UserController

Services

UserService

Repositories

UserRepository

Policies

UserPolicy

Requests

StoreUserRequest

UpdateUserRequest

Events

InvoicePosted

Jobs

PostAccountingDocument

Actions

CreateInvoiceAction