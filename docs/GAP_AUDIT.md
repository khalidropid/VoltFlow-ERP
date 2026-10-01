# VoltFlow ERP — Gap Audit

## Code-level gaps reviewed
- Duplicate Flutter integration migration: removed; the canonical 2026_09_29_150000 migration is retained.
- Multi-station API context: fixed by binding station.access to StationContext.
- Operations API: no longer selects the user's first station.
- API audit: no longer selects the user's first station.
- Billing adjustments and credit/debit notes: accounting journals added.
- Legacy mapping: dry-run and idempotent mapping service added.
- Reconciliation: inventory, procurement, payroll and GL coverage added.
- Production gate and UAT checklist added.
- CI: PostgreSQL 17 + PHP 8.4 test workflow added.
- Financial float boundary: JournalEntryService no longer accepts float inputs.

## Validation-only gaps
These cannot be truthfully marked passed from repository inspection alone:
1. Regenerate composer.lock with PHP 8.4 / Laravel 13 constraints and run composer update.
2. Run migrations from an empty PostgreSQL 17 database.
3. Run the complete PHPUnit suite.
4. Execute targeted integration, station-isolation, accounting reversal and reconciliation tests.
5. Verify Filament resources render and authorization policies work in a real application session.
6. Execute legacy dry-run against the real legacy dataset.
7. Execute backup/restore against the production-like PostgreSQL dataset.
8. Run UAT scenarios and record evidence.
9. Verify queue worker, scheduler, storage permissions and deployment secrets.
10. Review CI results after the updated dependency contract is committed.

No item above is being represented as passed until its actual execution evidence exists.