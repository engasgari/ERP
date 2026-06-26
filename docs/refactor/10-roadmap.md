# Roadmap

## Phase 1: Finance Safety

1. Replace hard delete and unpost flows for posted accounting documents with explicit reversal documents.
2. Remove floating-point math from financial posting and reporting paths.
3. Make the accounting engine the only entry point for financial document creation and reversal.

## Phase 2: Architecture Cleanup

1. Split the biggest controllers into services/actions/repositories.
2. Move Livewire persistence and cross-model orchestration into services.
3. Normalize the report contract so Blade only renders already-shaped data.

## Phase 3: Permission Hardening

1. Fix route permissions so all mutating actions require manage-level permissions.
2. Add missing module policies and use them for model-bound authorization.
3. Review destructive flows in finance, inventory, payroll, and projects.

## Phase 4: Performance and Test Coverage

1. Move Blade aggregation and repeated summary queries into services or repositories.
2. Break the large report service into smaller builders with caching where needed.
3. Add regression tests for finance reversal, permission boundaries, and missing module CRUD paths.

## Phase 5: Database Hygiene

1. Resolve migration timestamp collisions.
2. Decide whether the schema dump remains part of the source of truth.
3. Standardize access-control bootstrapping around a single seeder path.

