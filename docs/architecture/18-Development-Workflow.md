# Development Workflow

Every new feature follows this order.

Step 1

Business Rule

↓

Step 2

Database Design

↓

Step 3

Model

↓

Step 4

Repository

↓

Step 5

Service

↓

Step 6

Policy

↓

Step 7

Request Validation

↓

Step 8

Controller

↓

Step 9

Livewire

↓

Step 10

Blade

↓

Step 11

Feature Test

↓

Step 12

Refactor

----------------------------------------------------

Never

View

↓

Model

----------------------------------------------------

Never

Controller

↓

Model

----------------------------------------------------

Always

Controller

↓

Service

↓

Repository

↓

Database

----------------------------------------------------

Every Feature must include

Permission

Validation

Logging

Tests

Documentation

----------------------------------------------------

Definition Of Done

✓ Feature Works

✓ Tests Pass

✓ Permission Added

✓ Translation Added

✓ Documentation Updated

✓ No Duplicate Code

✓ UI Matches ERP Design System