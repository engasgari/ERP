# Reports

## Architecture

Controller

↓

Report Service

↓

Repository

↓

Normalized Data

↓

Blade

↓

Print

↓

PDF

↓

Excel

---

## Rules

Reports never query directly inside Blade.

Reports never calculate accounting.

Reports receive normalized data.

HTML

Print

PDF

Excel

must use identical dataset.

---

## Report Contract

title

subtitle

summary

sections

comparison

charts

export_rows

---

## Responsibilities

ReportService

Business Logic

Repository

Queries

Blade

Rendering