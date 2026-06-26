# Iran Enterprise ERP - Project Context

This project is an Iranian-style enterprise ERP built with Laravel, Livewire, MySQL, TailwindCSS and AlpineJS.

The goal is to gradually evolve the existing application into a structured ERP similar in discipline to Iranian ERP systems such as Hamkaran/System Group and Sepidar, without rewriting the project.

## Core Principle

This project is not a CRUD app.

It is a modular ERP where every module must follow clear business ownership, accounting boundaries, permissions, auditability, and reportability.

## Main Modules

### System & Access Control
Owns users, roles, permissions, employee access, menus and security boundaries.

### Base Information
Owns parties, items, warehouses, chart of accounts, fiscal years, fiscal periods, organization units, jobs and positions.

### Accounting Engine
The heart of ERP. Owns accounting documents, journal entries, ledgers, fiscal periods, posting, reversal and financial reports.

No module may directly create ledger entries.

### Treasury
Owns cashboxes, bank accounts, receipts, payments, bank/cash transactions and settlement flows.

Treasury creates accounting requests through Accounting Engine and never edits ledger directly.

### Inventory
Owns item quantity, warehouse documents, stock movements and Kardex quantity.

Inventory never owns financial value. Accounting owns value.

### Sales & Purchase
Owns invoices, invoice lines, parties, settlement status and commercial documents.

Sales/Purchase may request inventory and accounting operations but must not directly change stock ledger or accounting ledger.

### Human Resources
Owns employees, contracts, positions, organization structure, personnel documents and employment orders.

### Attendance
Owns work logs, calendars, shifts, leaves, missions, monthly attendance and attendance summaries.

### Payroll
Owns payroll periods, payroll items, salary calculation, payslips, insurance and tax calculations.

Payroll calculates salary but Accounting posts salary.

### Projects & Production
Owns projects, BOM, production orders, material consumption and project costing.

### Reports
Reports must use services and repositories to produce normalized datasets.

Screen, print, PDF and Excel must use the same dataset.

## Architecture

Required flow:

Route → Controller → Service → Repository → Model → Database

Controllers only authorize, validate, call services and return responses.

Services contain business logic.

Repositories contain database queries, search, filters and aggregations.

Models contain relations, casts, scopes and simple accessors.

Livewire manages UI state only.

Blade renders prepared data only.

## Development Policy

ChatGPT/User acts as senior developer and architect.

Codex only applies small, explicit changes based on prompts.

No architectural decision may be made by Codex without an explicit prompt.

Every change must preserve backward compatibility.