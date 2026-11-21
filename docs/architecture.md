# Laras Bumi OS architecture

Phase 0 design for CV LARAS BUMI GRUP, Kabupaten Jember. See [requirements](requirements.md), [implementation plan](implementation-plan.md), and [decisions](decisions.md).

## Repository and runtime

```text
apps/api/              Laravel entry points, config, routes, migrations, seeders and tests
apps/api/app/Modules/  Domain boundaries, application services, policies and adapters
apps/web/              Next.js App Router, Indonesian office UI and driver PWA
apps/web/src/features/ Feature routes, forms, API integration and driver sync
apps/web/public/       Driver manifest, icons and service worker entry
contracts/             OpenAPI contract and generated client instructions
infra/                 Docker images and Nginx routing
apps/web/tests/e2e/    Playwright office and driver journeys
docs/                  Design, operational guides and release evidence
compose.yaml           Local integration topology
```

Nginx serves one HTTPS origin. It routes `/api/v1/*` and `/sanctum/csrf-cookie` to Laravel and all application pages to Next.js. Laravel PHP-FPM, worker and scheduler share an image. PostgreSQL is the system of record. Redis serves sessions, cache and queues, with worker queues isolated from evictable cache data. The API and worker use private S3-compatible object storage and a shared local storage adapter in development. Only the edge is publicly exposed.

Use Next.js App Router with TypeScript, Tailwind CSS, shadcn/ui, React Hook Form and Zod. Validate forms locally for feedback and map server errors back to controls. Protected screens load through the same-origin API using session cookies. Prefer client-side protected-data queries in the initial slice to keep authentication and caching behavior explicit. Laravel remains authoritative for authorization and domain decisions.

## Module ownership

| Boundary | Owns | Cross-module interface |
|---|---|---|
| IdentityAccess | Users, sessions, RBAC and actor status | Actor and permission resolution |
| Customers | Customer, category, contacts, location, billing profile | Customer and location lookups |
| Contracts | Contract lifecycle, priced services, effective rates and recurrence configuration | Active service and rate on a business date |
| Collections | Dispatch, vehicles, assignments, events and pickup evidence | Completed pickups and event-based activity |
| WasteTracking | Intake, weighing, sorting, variance and stock ledger | Transactional lot consumption, creation, release and reversal |
| Production | Processes, templates, batches, measurements, QC and products | Approved release through the shared stock ledger |
| Finance | Billing runs, invoice documents, payments and expenses | Reads eligible service occurrences and verified allocations |
| SharedInfrastructure | Private files, audit, idempotency and job delivery | Infrastructure services and transaction helpers |

Use a thin controller with a Form Request, typed inputs, Laravel policy, application service and API Resource. Apply ownership and status checks again inside the service transaction when concurrent changes can invalidate them. Call across module boundaries through explicit service methods and read projections. Production changes inventory through WasteTracking in the same PostgreSQL transaction. Finance cannot update operational source data. Reports aggregate authorized read models and include timestamps, date filters and drill-down scopes. Keep the five business modules as the product boundary. Identity, reporting and settings support those workflows.

## Cross-cutting conventions

- PSR-12 via Pint, typed PHP services and enums, strict TypeScript, ESLint, database constraints for durable invariants and explicit API field allowlists. Use English identifiers and Bahasa Indonesia for user-facing text. Pass quantity and money as decimal strings. Use `Asia/Jakarta` for business dates and UTC instants at storage and transport boundaries.
- Use row locks in stable order, bounded retries on deadlocks and transactional audit records. Publish jobs after commit via a durable outbox for required work such as invoice PDF creation. A scheduled sweep redispatches unsent jobs. Consumers use stable DB keys and can recover after repeated delivery.
- Files are private and authorized at download time. Stage driver uploads through Laravel with byte, dimension and decoded-image checks, quarantine until accepted and strip EXIF. Sync links only validated files owned by that driver and bound to that task. Allow short-lived signed downloads or authenticated streaming. Configure upload and privacy limits and reconcile abandoned server-side staging objects against a grace period.
- Same-origin Sanctum session authentication, CSRF, secure HTTP-only session cookies, session rotation on login, invalidation on logout, rate limits and TLS. Keep secrets in environment or a secret manager. Sanitize logs and use request IDs. Record critical changes with actor, reason, resource and safe metadata. See [permissions](permissions.md). [Sanctum SPA authentication](https://laravel.com/docs/13.x/sanctum).

## Driver PWA

Scope the service worker to `/driver/`. Cache a versioned offline shell and required static assets. Fetch protected API data over the network and store only a minimal dated task snapshot and user-scoped queue in IndexedDB. A previously authenticated driver on the same device can capture evidence and transitions for downloaded tasks. Write the client event UUID, local sequence, expected task revision, captured time, GPS and photo blobs durably before acknowledging capture. Keep camera, GPS and upload failures visible. Treat device timestamps and GPS as user evidence.

On foreground, reconnection or manual sync, upload photos under stable file UUIDs, then post dependent task events in capture order per task using a tab-safe queue. Reconcile committed server acknowledgments by event UUID. A lost acknowledgment leads to replay. Authentication expiry pauses sync for same-user reauthentication. Task reassignment or revision conflict stops that task queue for review with evidence retained. Explicitly show pending, syncing, confirmed and attention-required counts. Clear acknowledged evidence under retention rules. Warn before a shared-device sign-out with unsent records, lock queued data from the next login and require same-account recovery. Browser-managed storage can be evicted, so request persistence when supported and test the actual field devices.

## Delivery and operations

Run PostgreSQL integration tests against the deployment major. CI gates lint, type checks, builds, migration from a clean DB, safe upgrade, PHPUnit or Pest, OpenAPI schema and Playwright. Use synthetic fixtures in isolated test databases. Production seeding is limited to approved configuration and securely provisioned admin access. Phase 7 exercises backups of PostgreSQL and objects, restoration and cross-store reconciliation, worker recovery, migration rollback strategy, and dispatch on target phones. Schedule Laravel dispatch and billing with overlap protection and database-enforced idempotency.

Run web, API, worker and scheduler as distinct Compose services. Use runtime health checks, separate liveness and readiness endpoints, and run migrations as a controlled release step before enabling new code paths. Track queue age, failed jobs, sync conflicts, stale billing runs and ledger reconciliation errors. Store operation identifiers and request IDs for triage.
