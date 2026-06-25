# AGENTS.md

# ERP Enterprise Development Rules

This document is the highest-level instruction for every AI Agent (Codex, ChatGPT, Claude, Gemini, Cursor, Windsurf).

All development MUST follow these rules.

Violation of these rules is considered an architecture bug.

---

# Project

Iran Enterprise ERP

Framework

Laravel 12

Livewire 4

MySQL

TailwindCSS

AlpineJS

---

# Source Of Truth

Before changing code ALWAYS read

docs/project-context.md

docs/architecture/*

docs/business/*

docs/standards/*

Never guess project architecture.

Never invent new patterns.

Follow existing architecture.

---

# Architecture Rules

Controllers

Receive Request

Authorize

Validate

Call Service

Return Response

Controllers MUST NOT contain Business Logic.

------------------------------------------------

Services

Contain Business Logic.

Coordinate modules.

Use Transactions.

Dispatch Events.

Never render HTML.

------------------------------------------------

Repositories

Database Queries only.

Filtering.

Searching.

Aggregations.

Never contain Business Logic.

------------------------------------------------

Models

Relations

Scopes

Casts

Accessors

Mutators

Never contain complex Business Logic.

------------------------------------------------

Livewire

UI State.

Forms.

Search.

Filters.

Pagination.

Modal.

Never contain Business Logic.

Never calculate Accounting.

Never calculate Payroll.

Never calculate Inventory Cost.

------------------------------------------------

Blade

Rendering only.

Never query database.

Never calculate totals.

Never call Models.

------------------------------------------------

Business Flow

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

# Accounting Rules

Accounting Engine is the heart of ERP.

Every financial operation MUST pass through Accounting Engine.

No module may directly create Ledger entries.

Posted Accounting Documents

Read Only

Never Edit

Never Delete

Reverse Only

---

# Treasury Rules

Treasury owns Cash.

Treasury owns Bank.

Treasury creates Accounting Documents.

Treasury never edits Ledger directly.

---

# Inventory Rules

Inventory owns Quantity.

Accounting owns Value.

Inventory never changes Ledger.

Accounting never changes Quantity.

---

# Payroll Rules

Payroll calculates Salary.

Accounting posts Salary.

Payroll never posts Accounting directly.

---

# Reports

Every report MUST use

FinancialReportService

Repository

Normalized Dataset

Never query inside Blade.

Print

PDF

Excel

must use same dataset.

---

# UI

Use ERP Components.

Never duplicate UI.

Every page must contain

Breadcrumb

Header

Toolbar

Filters

Content

Actions

Empty State

Loading State

---

# Database

Never use float for money.

Use decimal(18,2)

Use Foreign Keys.

Use Transactions.

Store Gregorian Dates.

Convert Jalali only in UI.

---

# Security

Always validate Requests.

Always authorize Actions.

Never bypass Policies.

Never disable Middleware.

Never expose hidden fields.

---

# Performance

Avoid N+1 Queries.

Use eager loading.

Paginate large datasets.

Cache expensive reports.

Queue heavy jobs.

---

# Code Style

Follow

PSR-12

Laravel Conventions

SOLID

DRY

KISS

---

# Naming

Controllers

UserController

Services

UserService

Repositories

UserRepository

Policies

UserPolicy

Actions

CreateInvoiceAction

Events

InvoicePosted

Jobs

ExportReportJob

Requests

StoreInvoiceRequest

---

# Module Structure

Each module SHOULD contain

Controller

Service

Repository

Policy

Requests

Views

Tests

Optional

Action

Observer

Event

Job

Notification

---

# Definition Of Done

Every Feature MUST include

Validation

Permission

Tests

Documentation

Responsive UI

Logging

No Duplicate Code

---

# Before Writing Code

Read existing implementation.

Reuse Components.

Reuse Services.

Reuse Repositories.

Never duplicate functionality.

---

# Before Creating New Files

Search existing project.

If functionality exists

Extend it.

Do not rewrite it.

---

# Refactoring Rules

Prefer Refactoring over Rewriting.

Never break Route Names.

Never break Permission Keys.

Never change Public APIs without reason.

Keep Backward Compatibility whenever possible.

---

# Testing

Every bug fix MUST include Regression Test.

Critical modules

Accounting

Payroll

Treasury

Inventory

Reports

must always remain testable.

---

# Documentation

Every architectural decision

Update documentation.

Every new module

Document it.

Every Business Rule

Document it.

---

# Golden Rules

Business Logic belongs in Services.

Database Logic belongs in Repositories.

Presentation belongs in Blade.

UI belongs in Livewire.

Accounting belongs in Accounting Engine.

Never mix responsibilities.

---

# Final Rule

When architecture conflicts with implementation,

Architecture wins.

When documentation conflicts with assumptions,

Documentation wins.

Never guess.

Always follow the Architecture.