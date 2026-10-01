# VoltFlow ERP — Production Readiness Gate

## Required gates
1. Environment variables are production values and APP_DEBUG=false.
2. PostgreSQL migrations run cleanly from an empty database.
3. Seeders complete without duplicate or cross-station records.
4. Queue worker and scheduler are enabled.
5. Database backup and restore procedure has been executed successfully.
6. Storage/log permissions are correct.
7. Health endpoint reports database, cache, queue, audit and integration readiness.
8. Station isolation and authorization suites pass.
9. Reconciliation reports are balanced for opening balances and all posted transactions.
10. Legacy migration dry-run reports zero blocking anomalies before import.
11. Flutter/API idempotency replay tests pass.
12. Rollback procedure is documented and rehearsed.

## Final release commands
- php artisan migrate:fresh --seed
- php artisan test --no-coverage
- php artisan route:list
- php artisan about

No release is considered approved until the final test output is reviewed.