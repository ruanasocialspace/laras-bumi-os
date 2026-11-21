# REST API architecture

Version application routes under `/api/v1`. Keep Sanctum’s framework CSRF bootstrap at `/sanctum/csrf-cookie`. Develop `contracts/openapi.yaml` alongside endpoints and generate TypeScript request/response types from it. This document defines the initial API shape.

## Phase 1 endpoints

The executable foundation contract is in [OpenAPI](../contracts/openapi.yaml). It covers liveness and readiness, login/logout/current account, reference defaults, the role catalog, account creation and updates, and the paginated audit log. Browser sessions use a host-only `laras_session` cookie. Bootstrap CSRF before a write and send the decoded `XSRF-TOKEN` cookie in the `X-XSRF-TOKEN` header. The API returns `X-Request-ID` and disables caching of responses. Role changes take effect on the next server check. Changing a password or active status revokes existing sessions. A change that would leave no active super admin returns 409.

The endpoint map below also includes the planned business modules.

## Conventions

- Resource bodies use `{"data": ...}`. Lists use `data`, `links` and `meta`. Default page size 25, maximum 100. Allowlist filters and sort fields, with ID as a deterministic final sort key. Use UUIDs, ISO 8601 instants with offset, YYYY-MM-DD Jakarta dates and decimal strings.
- Return 200 for reads and successful transitions, 201 for creation, 202 for accepted asynchronous work, and 204 for logout. Errors use `error.code`, `error.message`, optional `error.fields`, and `error.request_id`. Use 401 unauthenticated, 403 action forbidden, 404 absent or outside object visibility, 409 state/revision/idempotency conflict, 413 oversize, 415 unsupported media, 419 invalid CSRF, 422 validation and 429 rate limit. Preserve usable field errors in Indonesian.
- Use `Idempotency-Key` on critical commands and uploads. Normalize validated payloads including file digests, claim by actor/operation/key and persist outcome with the mutation. Same key and same payload returns the original safe result after authorization. A changed payload returns 409. Long-lived business uniqueness remains in PostgreSQL. Check replay identity and visibility before expected revision on driver retries. Clients never infer commitment from a timeout.
- Deny by default, scope resource binding and repeats to the actor, and derive actor identity server-side. Send `Cache-Control: no-store` for protected responses. Apply limits to auth, sync, downloads and reports.

## Endpoint map

All relative paths in the table have the `/api/v1` prefix. Nested resources are scoped through their parent. Updates through PATCH apply to permitted mutable fields only. Critical transitions use POST subresources and preserve history.

| Group | Initial endpoints and behavior |
|---|---|
| Auth and settings | `POST /auth/login`, `GET /auth/me`, `POST /auth/logout`; scoped `/users`, `/roles` and `/settings` for restricted administration. Return actor and permission codes from `/auth/me`. |
| Customers | `GET/POST /customers`, `GET/PATCH /customers/{id}`, `GET/POST /customer-categories`, `GET/POST /customers/{id}/locations`, `PATCH /customers/{id}/locations/{locationId}`, `GET/POST /customers/{id}/contacts`, `PATCH /customers/{id}/contacts/{contactId}`, `GET/PUT /customers/{id}/billing-profile`, `GET /customers/{id}/pickup-history` and `GET /customers/{id}/billing-history`. Search by text, category and status. Billing history also needs finance read access. |
| Contracts | `GET/POST /contracts`, `GET/PATCH /contracts/{id}`, `POST /contracts/{id}/transitions`, `GET /contracts/{id}/history`, `GET/POST /contracts/{id}/services`, `GET/POST /contracts/{id}/services/{serviceId}/rates`. Rates use effective ranges. |
| Recurrence and dispatch | `GET/POST /pickup-schedules`, `GET/PATCH /pickup-schedules/{id}`, `GET/POST /pickup-tasks`, `GET /pickup-tasks/{id}`, `POST /pickup-tasks/{id}/assignments`, `GET/POST /vehicles`, `PATCH /vehicles/{id}`. The same task list serves dispatch and the driver with different scope and fields. Generator is a scheduler/queue job. |
| Driver workflow | `POST /pickup-tasks/{id}/evidence` stages a multipart image and client file UUID. `GET /pickup-tasks/{id}/evidence/{fileId}` returns current validation state. `GET/POST /pickup-tasks/{id}/events` reads history or commits one event. |
| Intake and ledger | `GET/POST /waste-types`, `PATCH /waste-types/{id}`, `GET/POST /waste-intakes`, `GET/PATCH /waste-intakes/{id}`, `GET/POST /waste-intakes/{id}/weighings`, `POST /waste-intakes/{id}/posting`, `POST /waste-intakes/{id}/corrections`, `POST /waste-intakes/{id}/corrections/{correctionId}/approval`, `GET /material-lots`, `GET /material-lots/{id}`, and nested `GET` for `movements` and `traceability`. |
| Sorting | `GET/POST /sorting-sessions`, `GET/PATCH /sorting-sessions/{id}`, `POST /sorting-sessions/{id}/posting`, `POST /sorting-sessions/{id}/review`, `POST /sorting-sessions/{id}/reversal-requests` and `POST /sorting-sessions/{id}/reversal-requests/{requestId}/approval`. Posting accepts input, classified and residual outputs, documented loss and reasons. Server computes variance. Reversal uses independent approval. |
| Production | `GET/POST /production-batches`, `GET/PATCH /production-batches/{id}`, and nested `POST` for `transitions`, `inputs`, `measurements`, `outputs`, `losses` and `qc-decisions`. `GET/POST /products`, `GET/POST /process-measurement-templates` and `GET/POST /material-lots/{id}/eligibility-approvals`. Batch detail includes authorized lineage, yield and QC evidence. |
| Invoices and billing | `GET/POST /invoices`, `GET/PATCH /invoices/{id}`, and nested `POST` for `issue`, `void-requests` and `void-requests/{requestId}/approval`. `GET /invoices/{id}/pdf` returns a private download when ready. `GET/POST /billing-runs`, `GET /billing-runs/{id}` returns progress and unresolved occurrences. |
| Payments and expenses | `GET/POST /payments`, `GET /payments/{id}`, `POST /payments/{id}/decisions`, `POST /payments/{id}/reversal-requests`, `POST /payments/{id}/reversal-requests/{requestId}/approval`, `GET/POST /expenses` and `GET /expenses/{id}`. Accept allocations on a proposed payment, then apply them at verification. |
| Reports | `GET /reports/dashboard`, `/reports/collection`, `/reports/intake`, `/reports/production`, `/reports/receivables`, `/reports/revenue` and `/reports/variance`. Apply section-specific permissions and local date ranges, with as-of timestamps and distinct verified versus estimated measures. |

## Mutation contracts to implement first in each domain

- Driver event requires `client_event_id`, `expected_revision`, `type`, `captured_at` and type-specific fields. Completion includes GPS coordinates with accuracy, accepted evidence IDs and `weight_kg` with `weight_basis`. Failure includes reason. Response returns committed event ID, sequence, new status and revision. A 409 includes current revision only for actors with task visibility.
- Intake draft accepts completed pickup IDs without implied attribution. Weighing accepts either gross and tare or direct net, plus waste type and scale/operator evidence as configured. Propose attributions using `POST /pickup-tasks/{id}/weight-verifications` with intake IDs and documented kg, then finalize via `POST /pickup-tasks/{id}/weight-verifications/{verificationId}/approval` under weighing permissions. Ledger endpoints expose original and reversal links.
- Production start accepts planned inputs with referenced lots, quantity and basis. QC approval links reviewer evidence and the output set it approves. Release and batch completion share the same commit.
- Billing run accepts an explicit local period and billing mode. The response includes a run ID and status. Manual invoice drafts accept descriptions, quantities and rates. Issue returns number, totals and PDF state. Payment creation requires customer, amount, method, paid date and invoice allocations. Decisions include outcome and reason, with the verifier resolved from the session.
