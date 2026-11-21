# Architecture decisions and business questions

## Adopted for the Phase 0 design

| ID | Decision and rationale |
|---|---|
| ADR-01 | One Laravel modular monolith, one PostgreSQL system of record and one Next.js app. Transaction boundaries span related changes such as QC and stock release. An offline driver route shares the deployment and identity model. |
| ADR-02 | One HTTPS origin and Sanctum cookie sessions. Browser clients use CSRF protection and reauthenticate before replay if a session expires. Local captured evidence stays bound to its account. |
| ADR-03 | Ledger with locked balance projections for stock, separate capture and verification records for weight, durable occurrence keys for scheduled tasks and billed charges. These protect business invariants under retries and concurrency. |
| ADR-04 | Additive corrections, effective-dated pricing, issued snapshots and maker/checker approval preserve historical invoices, stock and verified payments. |
| ADR-05 | Phase-by-phase delivery with a review gate. This phase produces design documents. Product data fixtures are synthetic and test-only. |

## Proposed dependency baselines

Candidates checked against upstream support and runtime requirements on 21 November 2026. Phase 1 resolves exact patches, builds images, validates dependency compatibility and commits lockfiles. Choose the latest security patch in the selected supported series at pin time.

- Laravel 13 with PHP 8.5 and a Composer-resolved compatible Sanctum release. Laravel 13 lists PHP 8.3–8.5. Verify required extensions including PostgreSQL, Redis and image processing in the container. [Laravel support](https://laravel.com/docs/13.x/releases) · [PHP supported versions](https://www.php.net/supported-versions.php)
- Next.js 16 with Node.js 24 LTS. Next.js 16 requires Node.js 20.9 or later. Resolve compatible React, TypeScript, Tailwind, shadcn/ui, React Hook Form and Zod together. [Next.js support](https://nextjs.org/support-policy) · [Next.js runtime requirements](https://nextjs.org/docs/app/guides/upgrading/version-16) · [Node.js release status](https://nodejs.org/en/about/previous-releases)
- PostgreSQL 18 as a supported, established major for managed hosting and CI. Verify `btree_gist` availability. Redis 8 series is the proposed queue/session/cache target. Confirm deployment provider support, Redis minor and queue client compatibility during scaffolding. [PostgreSQL versioning](https://www.postgresql.org/support/versioning/) · [Redis installation documentation](https://redis.io/docs/latest/operate/oss_and_stack/install/install-stack/)

## Open business decisions

| ID | Decision owner and timing | Proposal or implementation guard |
|---|---|---|
| B01 | Operations and Finance before Phase 2 | Confirm customer categories, service packages and billing terms. Use configurable master data. Confirm rate effective date and whether future amendments can be entered before becoming active. |
| B02 | Operations before Phase 3 | Confirm weekday recurrence, holidays, service windows, rescheduling and suspension of already generated jobs. Propose weekly weekday and slot rules, stable occurrence IDs and dispatch review of open tasks when a contract is suspended. |
| B03 | Operations and privacy owner before Phase 3 | Confirm required evidence count, blocked GPS/camera handling, shared-device policy, retention period and eligible history. Propose at least one photo and valid GPS for completion, and retain drafts when field evidence cannot be captured. Approve any exception workflow before enabling it. |
| B04 | Weighing lead and Operations before Phase 4 | Confirm scale precision, tolerance formula and thresholds by waste type, correction and attribution authority, reconciliation across several intakes. Propose absolute kg tolerance in the first release, signed variance and supervised exceptions. No tolerance value is assumed. |
| B05 | Production lead and authorized quality approver before Phase 5 | Confirm measurement templates, permitted inputs and eligibility evidence by intended use, hold/rework procedures, QC criteria and yield reporting bases. Feed-designated operations remain blocked without approved rules and source records. |
| B06 | Finance and accounting advisor before Phase 6 | Confirm treatment of taxes, exemptions, IDR rounding rule, invoice legal fields, series, due terms and whether numbers may have gaps. Model effective policies and immutable snapshots. Issuing is blocked until policy is configured. |
| B07 | Finance and Operations before Phase 6 | Confirm proration, effective dates within monthly periods, missed collections, billable manual pickups, backdated attributions and late charges. Propose monthly and one-time rates selected by agreed period or occurrence anchor, pickup rates by service date, and unresolved per-kg charges surfaced for review. |
| B08 | Finance before Phase 6 | Confirm definition of revenue, payment methods, overpayment handling, deposit treatment and authorized proof. Propose separate labeled issued billing, verified cash collections and receivables. Initial verified allocation must equal receipt. |
| B09 | Owner before Phase 1 RBAC review and domain release | Confirm approver staffing and segregation of duties. Proposed policy requires a distinct recorder and verifier on payment and distinct makers/checkers on material corrections, QC and voids. |
| B10 | Owner and deployment operator before Phase 7 | Approve environment ownership, data retention, target phones and acceptable outage/data-loss windows. Validate documented backup restoration and incident procedures against those targets. |

### Phase 2 operating assumptions

- B01 uses four generic starter categories with manager-created additions, contract-specific service names and expressly entered payment terms. The definitive business category and service lists remain open. The app accepts future rate amendments under pricing permission and protects the historical rate amount.
- B02 starts with weekly schedules specified by weekday, time in WIB and date range. Activation requires an eligible customer, a service location and at least one dated weekly occurrence with a rate for each service. Dispatch, holiday overrides and handling of generated tasks follow in Phase 3.

### Phase 3 operating assumptions

- Generate today's eligible recurring pickups at 00:05 WIB and allow an authorized dispatcher to generate a selected current or future date. Keep previously generated work when contracts are suspended and flag it on the dispatch board. Dispatch can cancel an obsolete task with a reason and create a replacement if needed. Holiday calendars and automatic rescheduling depend on the confirmed B02 policy.
- Require a JPEG or PNG photo, GPS and a weight basis for completion. Keep queued evidence on the device until acknowledgment, including after an ambiguous server response or assignment conflict. A driver can resume after reconnecting and signing in with the same account. Device retention, server-side retention, and field testing on the operating fleet remain open under B03.

- Shared-device queue loss, browser storage eviction and permissions revocation require real-device acceptance. Design the pilot around the phones and browsers B10 identifies.
- Concurrent stock changes, out-of-order sync and retries after partial job failures can compromise data if only application validation is used. Require PostgreSQL uniqueness, transactional locks and race tests.
- Mixed-load per-kg billing, feed eligibility and tax configuration are operational decision points. Block individual actions without validated policy or evidence and show explainable exceptions to authorized staff.
