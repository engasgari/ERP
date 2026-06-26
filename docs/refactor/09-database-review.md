# Database Review

| Severity | Location | Reason | Recommendation | Estimated Difficulty |
| --- | --- | --- | --- | --- |
| Medium | `database/migrations/2026_06_21_000004_add_detail_account_to_bank_accounts.php`, `database/migrations/2026_06_21_000004_add_settlement_fields_to_invoices.php` | Two migrations share the same timestamp prefix and sequence number. Laravel can still read them, but this makes migration history harder to reason about and increases the risk of ordering confusion during maintenance. | Renumber one of the migrations or consolidate adjacent schema changes into a clearer migration sequence. | Low |
| Medium | `database/erp_full_schema.sql`, `database/migrations/*`, `database/migrations/2026_06_08_000001_repair_erp_database_schema.php`, `database/migrations/2026_06_09_000001_create_commercial_accounting_core_tables.php` | The project carries both a full schema snapshot and many repair-style migrations. That increases the risk of schema drift and makes the canonical database state harder to audit. | Treat migrations as the source of truth and regenerate or retire the schema dump unless it is actively needed for a deployment workflow. | Medium |
| Medium | `database/access_control_seed.sql`, `database/seeders/AccessControlSeeder.php` | Access control data exists in both raw SQL and seeder form, which can split the bootstrap path for permissions and roles. | Keep one authoritative seeding path and derive the other from it if both are required. | Low |

