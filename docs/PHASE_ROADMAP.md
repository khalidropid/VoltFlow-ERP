# VoltFlow ERP — Master Phase Roadmap

This document is the authoritative delivery sequence for the project. Phase names in old branches or pull requests do not override this sequence.

| Phase | Official scope | Current classification |
| --- | --- | --- |
| 6E | Supplier Procurement Services | Completed foundation |
| 6F | Controls & Reports | Current normalization phase |
| 6G | Management / Operations | Next structural phase |
| 6H | Billing & Metering | Not started as the canonical phase |
| 6I | Collections & Cash | Partially implemented foundation |
| 6J | Generation | Models/resources prepared; domain workflow still required |
| 6K | Fuel & Maintenance | Models/resources prepared; domain workflow still required |
| 6L | HR & Payroll | Payroll domain/UI exists as forward-prepared work |
| 6M | Accounting & Treasury | Accounting foundation exists; treasury completion required |
| 6N | Flutter / API Integration | Existing integration branch is reserved for this phase |
| 6O | Security & Audit Hardening | Existing audit work is reserved for this phase |
| 7 | Reconciliation + Reports | Final subledger/GL reconciliation phase |
| 8 | Legacy Migration | Mapping, dry-run, anomaly detection, staged migration |
| 9 | Production Readiness | Performance, backups, queues, logging, security review, deployment |
| 10 | UAT / Go-Live | Full business scenarios, UAT fixes, release |

## Phase completion standard

A phase is complete only when the applicable layers exist and are validated:

Database
→ Domain Service
→ Business Rules / Validation
→ Filament / API
→ Feature Tests
→ Accounting / Reconciliation Tests where applicable
→ Station Isolation / Authorization Tests
→ Idempotency / Immutability Tests where applicable

## Historical implementation classification

Some functionality was implemented before its official phase. This is intentionally retained rather than rewritten out of the repository.

- HR/payroll code currently present in the foundation is forward-prepared and belongs to 6L.
- Integration/audit code in the open integration branch is forward-prepared and belongs to 6N/6O.
- Generation, fuel, maintenance, banking, and other domain models may exist before their service/workflow phase.
- Presence of a Resource or Model never constitutes phase completion by itself.

## Branch policy

Canonical phase branches use these names:

`phase-6e-supplier-procurement`
`phase-6f-controls-reports`
`phase-6g-management-operations`
`phase-6h-billing-metering`
`phase-6i-collections-cash`
`phase-6j-generation`
`phase-6k-fuel-maintenance`
`phase-6l-hr-payroll`
`phase-6m-accounting-treasury`
`phase-6n-flutter-api-integration`
`phase-6o-security-audit-hardening`

Historical/mistaken branch names are retained for traceability but must not be treated as canonical phase labels.
