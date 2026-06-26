# Architecture

## Layers

Presentation
↓

Application

↓

Domain

↓

Infrastructure

↓

Database

---

## Responsibilities

Controller
- Request
- Response
- Authorization

Service
- Business Logic

Repository
- Database Query

Model
- Entity

Policy
- Authorization

Middleware
- Request Validation

Livewire
- UI State

Blade
- Rendering

---

## Flow

Route

↓

Controller

↓

Service

↓

Repository

↓

Model

↓

Database

---

## Rules

Controller MUST NOT contain business logic.

Repository MUST NOT calculate business rules.

Blade MUST NOT execute queries.

Service MUST NOT return HTML.

Livewire MUST NOT contain accounting logic.