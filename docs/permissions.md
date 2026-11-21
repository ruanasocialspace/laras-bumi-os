# Authorization and role design

Seed stable role and permission codes. Laravel policies enforce action permissions plus row context on both reads and writes, including nested routes, files, exports, job entry points and replay requests. The frontend consumes capabilities to present appropriate navigation, and server decisions remain authoritative.

Phase 1 enforces `identity.manage` on account and role-catalog access, and `audit.read` on audit access. The API applies these checks before looking up an account ID. A signed-in user can read their own identity and operational reference defaults. The approved role definitions are configured in the role catalog seeder and assigned or revoked per account. Domain-specific codes are seeded for future phases. Approval staffing in B09 remains open.

Phase 2 uses Laravel permission gates on every customer and contract route. Operations Admin can draft contracts and define schedules. Operations Manager can also create customer categories, set rates and transition contracts. Finance Officer receives `customers.read` to find customers before maintaining their billing profiles. Billing reads and writes still require `finance.read` and `finance.bill` respectively. Operations permissions alone grant neither. Driver accounts cannot access the customer or contract directories.

| Role | Proposed scope |
|---|---|
| Super Admin | Identity provisioning, role assignment, system settings, audit review and explicitly granted domain permissions |
| Owner | Read business reports and domain records, approve high-impact changes through distinct-actor checks |
| Operations Manager | Customer/contract oversight, pricing approval rights, dispatch, inventory and production oversight, designated operations approvals |
| Operations Admin | Customer registration, contract drafting, schedules, vehicles and dispatch |
| Driver / Collection Crew | Currently assigned tasks and own eligible history, own uploads, authorized state progression |
| Weighing Officer | Intake and weighing, sorting drafts/posting within tolerance, attribution proposals and correction requests |
| Production Operator | Production drafts, input planning, start, measurements, loss/output capture and QC submission |
| Finance Officer | Billing profiles, finance reports, invoice drafting and issuing, billing runs, expenses and payment recording. Verification requires explicit grant and a distinct recorder. |

## Capability and policy rules

| Actions | Permission codes and checks |
|---|---|
| Identity and configuration | `identity.manage`, `settings.manage`, `audit.read`. Never disable the final active super admin. Audit access and privilege changes. |
| Customer and contract | `customers.read`, `customers.write`, `customers.categories.manage`, `contracts.read`, `contracts.write`, `contracts.activate`, `contracts.pricing.write`. Operational authors lack pricing unless separately granted. Check customer ownership of service locations and effective date constraints. Require `finance.read` to read billing profiles through finance and `finance.bill` to write them. |
| Collection | `collections.read`, `collections.dispatch`, `collections.drive`. Drivers lack organizational list access. Events require current assignment and files require uploader/task match. Driver history is scoped to tasks the driver personally performed and omits unrelated customer and billing information. |
| Weighing, sorting and stock | `waste.read`, `waste.record`, `waste.post`, `waste.approve`, `waste.configure`. Distinct actors approve corrections, finalized attribution and over-tolerance sessions. Finance can read the billing eligibility projection through finance permissions. |
| Production | `production.read`, `production.operate`, `production.qc.approve`, `production.eligibility.approve`, `production.configure`. QC and eligibility approval require documented authority. QC approver differs from submitter and eligibility approver differs from requester. |
| Finance | `finance.read`, `finance.bill`, `finance.payments.record`, `finance.payments.verify`, `finance.adjustments.request`, `finance.adjustments.approve`, `finance.expenses.record`, `finance.tax.configure`. Void and reversal approvers differ from requester. Verification forbids self-approval and applies only to pending payments and eligible allocations. |

Scheduled jobs use a restricted system actor and audited command context. Any elevated path still passes approval guards. Apply denied-list and denied-object tests for each role, plus revoked/disabled session, reassignment, nested resource mismatch, file isolation and maker/checker tests. Limit GPS, contact and payment data to operational purposes. Confirm retention, visibility and privacy policy before field use.
