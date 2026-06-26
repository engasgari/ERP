# Standard Module Structure

Every module MUST follow this structure.

app/
 ├── Http/
 │    ├── Controllers/
 │    ├── Requests/
 │    └── Resources/
 │
 ├── Livewire/
 │
 ├── Models/
 │
 ├── Services/
 │
 ├── Repositories/
 │
 ├── Policies/
 │
 ├── Events/
 │
 ├── Jobs/
 │
 ├── Actions/
 │
 └── Observers/

resources/views/

routes/

tests/

----------------------------------------------------

Every module MUST contain

Controller

Service

Repository

Policy

Requests

Views

Tests

----------------------------------------------------

Optional

Action

Observer

Event

Job

Notification

----------------------------------------------------

Responsibilities

Controller

Receive Request

↓

Validate

↓

Authorize

↓

Call Service

↓

Return Response

----------------------------------------------------

Service

Business Logic

----------------------------------------------------

Repository

Database Queries

----------------------------------------------------

Model

Relations

Scopes

Casts

----------------------------------------------------

Livewire

UI

State

Search

Filter

Modal

Pagination

----------------------------------------------------

Blade

Presentation Only

----------------------------------------------------

Tests

Feature

Unit

Regression