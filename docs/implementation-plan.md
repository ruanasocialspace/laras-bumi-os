# Implementation plan and backlog

Deliver each phase as a reviewable increment. Before coding, inspect repository instructions and confirm the phase-specific assumptions. Within each phase, implement migrations, validation, policies, application services, API contract, Indonesian UI and tests. Run available checks, resolve failures and record evidence. Pause for review at each phase boundary.

| Phase | Slice and dependencies | Review gate |
|---|---|---|
| 0 · Design | Inventory workspace, define architecture and ERD, map roles, states, endpoints and risks. | Eight design files reviewed for consistency and Phase 1 authorization pending. |
| 1 · Foundation | Bootstrap applications and Compose, CI, baseline design system, identity migrations and seeders, Sanctum sessions and RBAC, API errors and OpenAPI. | Clean bootstrap and seed replay, build, same-origin browser login/logout and role/disabled-user denial tests. |
| 2 · Customers and contracts | Implement categories, profiles, contacts, locations, searchable index, contracts, effective pricing, status history and recurring schedule definition. Add pickup and billing history integrations as Modules B/E land. | Activate, suspend and review history from UI, prove overlapping-rate and cross-customer location denial, and protect price edits. |
| 3 · Collection and driver PWA | Implement recurring generation for active agreements, manual tasks, dispatch, vehicle/driver assignment, events, private evidence, offline sync and history. | Active/inactive and retry generation tests. Real-device and Playwright journeys for GPS/photos, offline restart/retry, duplicate replay, reassignment and account isolation. |
| 4 · Waste tracking and ledger | Implement intake, configurable types, gross/tare and net workflows, source links, lot/ledger, sorting, attribution, tolerance review, correction and reporting. | Verify mixed-load provenance, signed balance formula, reversal integrity and simultaneous stock writers without negative balances. |
| 5 · Production | Implement batch workflow, template versions, lot consumption, eligibility evidence, losses, output bases, QC approvals and finished-stock views. | Reject insufficient stock, ineligible feed inputs and pre-QC release. Validate input/output genealogy and atomic QC publication. |
| 6 · Finance and billing | Implement all billing models, manual drafts, issuance and PDFs, jobs, correction and void flows, payments and expenses, receivables and revenue. | Concurrent and replayed billing produce one active charge. Verify immutable issue amounts, partial payment balances, reversal, overdue and unauthorized void. |
| 7 · Release | Integrate dashboards and histories, full E2E, security and deployment checks, pilot readiness, handover and release evidence. | All mandatory suites pass, backup restore drill and device pilot signed off, operational docs delivered and known limitations accepted. |

## First implementation backlog

| Order | Work item | Acceptance |
|---|---|---|
| F01 | Verify toolchain, dependency ranges and container base images. Scaffold `apps/api`, `apps/web`, proxy, postgres, redis, queue and scheduler. Supply environment examples and setup commands. | Clean checkout resolves reproducible lockfiles and Compose starts healthy. Proxy routes web, API and CSRF on one origin. |
| F02 | Add identity schema, stable permission catalog, repeatable seeding and secure initial admin provisioning. Configure runtime and database users with appropriate privileges. | Fresh PostgreSQL migrations pass, reseeding preserves role assignments, and production setup contains no demo accounts or embedded secrets. |
| F03 | Implement login, logout and current actor, request IDs, validation errors, role-aware middleware/policies, auth rate limits and security tests. | Browser session and CSRF checks work. Anonymous, disabled and unauthorized users receive the expected response through the normal API. |
| F04 | Build Indonesian auth and navigation shell using brand tokens and responsive accessibility patterns. Connect to actual actor capabilities and start `contracts/openapi.yaml`. | Operator and driver see appropriate navigation, errors are actionable, and the client uses the working auth endpoints. |
| F05 | Wire formatter/lint, static analysis, typecheck, build, PostgreSQL test/migration, schema validation and minimal Playwright into CI. Document test and run procedures. | Fresh checkout and CI execute the same checks with isolated synthetic fixtures. |

## Test design and release evidence

| Test target | Evidence to capture |
|---|---|
| Recurrence | Active and inactive contracts in one fixture, clock on Jakarta boundary, two overlapping scheduler executions and suspension/reschedule edge cases. |
| Driver | Seed an assigned task and attach a test image with browser GPS. Disconnect, capture and reload, then reconnect and simulate a lost HTTP response. Assert one persisted event, one set of linked evidence, and preserved queue on auth/revision conflict. |
| Intake and inventory | Multi-pickup load and pickup across two intakes, no inferred attribution, over-tolerance review, sort reversal and rejected overdraw. Use two real PostgreSQL connections with competing requests and assert reconciled balances and movements. |
| Production | Batch with concurrent input depletion, QC pending quarantine, reviewer denial and atomic release. Compare measured output and loss by basis. |
| Finance | Effective rate change, mixed-load skipped per-kg charge, monthly/per-pickup/one-time duplicate protection, issue and void audit, two partial verifications and reversal. Assert totals as decimals and overdue independent of lifecycle. |
| Authorization | Each write role has a permitted and forbidden action. Cover another user's driver task, unauthorized pricing, backend file download, reporting scope and self-approval denials. |

Before production, record build/test versions and outcomes, verify migrations and restoration on a staging copy, review dependency and access findings, sign off the field workflow and privacy settings, and provide setup, deployment, operations, testing and known-limitations guides. Approve launch only against the recorded evidence.
