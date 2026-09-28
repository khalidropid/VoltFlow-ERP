# VoltFlow ERP — Final Database Architecture

## Runtime contract

- PHP 8.2.12
- Laravel 12.69.2
- Filament 3.3.55
- MariaDB 10.4.32
- Flutter client is unchanged and remains an integration contract.

## Rules

1. Core ERP tables are authoritative for new ERP business transactions.
2. Existing Flutter/legacy tables remain available for compatibility.
3. Monetary, energy, inventory, fuel, payroll, and meter quantities use DECIMAL; financial logic must not use floating-point arithmetic.
4. Posted financial transactions are reversed/voided rather than deleted.
5. Cross-station data access must be prevented at service/API authorization boundaries and reinforced by station-scoped keys and indexes.
6. Idempotency applies to API and offline-sync transaction ingestion.
7. Cached balances must reconcile to transaction ledgers; they are not an independent source of truth.
8. Production migration of legacy data requires validation and explicit mapping; historical anomalies are preserved and classified rather than silently rewritten.

## Domains

### Core
stations, station_settings, document_sequences, users, station_user, roles, permissions

### Geography and customer service
service_areas, streets, collector_assignments, customers, customer_contacts, customer_change_requests

### Metering and billing
meters, meter_installations, meter_readings, billing_periods, billing_cycles, tariffs, tariff_slabs, customer_tariffs, invoices, invoice_items, invoice_adjustments, credit_notes, credit_note_items, debit_notes, debit_note_items

### Collections and cash
payments, payment_allocations, collector_accounts, collection_settlements, cash_accounts, cash_movements, cash_closings, bank_transactions, bank_reconciliations, bank_reconciliation_items

### Accounting
chart_of_accounts, fiscal_periods, journal_entries, journal_entry_lines, accounting_rules, cost_centers, customer_account_links, supplier_account_links, employee_account_links

### Generation and distribution
generators, generator_runtime_logs, generation_readings, feeders, feeder_readings, customer_connections

### Fuel
fuel_types, fuel_tanks, fuel_receipts, fuel_issues, fuel_adjustments, fuel_stock_movements

### Assets and maintenance
asset_categories, assets, maintenance_plans, maintenance_work_orders, maintenance_work_order_parts, maintenance_work_order_labor, maintenance_work_order_expenses

### Inventory and procurement
item_categories, units_of_measure, items, warehouses, warehouse_stocks, stock_movements, suppliers, supplier_contacts, purchase_requests, purchase_request_items, purchase_orders, purchase_order_items, goods_receipts, goods_receipt_items, supplier_invoices, supplier_invoice_items, supplier_payments, supplier_payment_allocations

### HR and payroll
departments, positions, employees, employee_contracts, employee_bank_accounts, shifts, shift_assignments, attendance_logs, attendance_adjustments, leave_types, leave_requests, overtime_records, employee_advances, advance_repayments, salary_components, employee_salary_components, payroll_periods, payroll_runs, payroll_slips, payroll_slip_lines, payroll_payments

### Integration and audit
audit_logs, idempotency_keys, integration_sources, integration_batches, integration_events, legacy_id_mappings, user_devices, collector_locations

## Flutter compatibility layer

Existing legacy/integration tables are preserved:

Customer, Employees, geathers, Months, State_Cos, Streets_Line, Take_up_Chits, Sandaat_paying_up, app_collections, app_readings, app_invoices, modification_requests, employee_locations.

- app_collections: Flutter collection ingress.
- app_readings: Flutter meter-reading ingress.
- app_invoices: Flutter collection receipt/voucher ingress; not the ERP invoice master.
- modification_requests: Flutter customer-change request ingress.
- employee_locations: Flutter location ingress.

## Primary business flow

Station -> Customer -> Meter -> Reading -> Consumption -> Tariff -> Invoice -> Payment -> Collector Custody -> Settlement -> Cash/Bank -> Journal.

Generation flow:

Station -> Generator -> Runtime/Production -> Fuel -> Maintenance/Cost -> Accounting.

Procurement flow:

Request -> Purchase Order -> Goods Receipt -> Inventory -> Supplier Invoice -> Accounts Payable -> Supplier Payment -> Accounting.

Payroll flow:

Employee -> Contract -> Attendance/Overtime/Allowances/Deductions/Advances -> Payroll Run -> Payroll Slip -> Payment -> Accounting.

## Migration policy

The current production/legacy cloud database must not be rebuilt or renamed as part of this architecture. VoltFlow introduces canonical ERP structures beside the compatibility layer. Legacy IDs are mapped using legacy_id_mappings, and ingestion is processed through integration_events with idempotent external identifiers.

## Verification sequence

1. Run migrations only against the dedicated testing database.
2. Run the complete PHPUnit suite.
3. Add domain-level feature tests for each new workflow.
4. Add reconciliation tests for balances and accounting.
5. Perform a dry-run legacy mapping report.
6. Only then plan production deployment.
