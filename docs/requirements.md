# MVP 1.0 requirements

LARAS BUMI OS supports waste collection, inorganic recycling and organic processing for CV LARAS BUMI GRUP. User interfaces are in Bahasa Indonesia. Business dates use Asia/Jakarta, amounts use IDR and default mass is kg. The 1,000 kg/day organic capacity and IDR 42,600,000/month collection scenario are planning assumptions. Operational dashboards use recorded data. Product sales, purchasing, distribution, and a general ledger are outside MVP.

## Functional acceptance

| Module | Required workflows and outcomes |
|---|---|
| A · Customers and contracts | Register and search customers by name, status and category, capture contacts and multiple pickup locations, maintain billing profile and contract history, configure service, frequency and monthly, per-pickup, per-kg or one-time prices, and show customer pickup and billing history. Activation validates eligible locations, schedules and rates. Rate changes have effective dates and issued invoices keep their snapshots. |
| B · Collection | Dispatch board by local service date, manual and recurring pickups, driver and vehicle assignment, pickup location and external map link, mobile task list and history, timestamps, GPS, proof photos, estimated or field-measured mass and failed reasons. Offline capture and foreground retries preserve evidence and synchronize each event once. |
| C · Waste tracking | Register intake against one or more completed pickups, record gross/tare or direct net, classify and create lot, sort into material/residual lots, record losses and investigate discrepancies. Show intake dashboard, material balance, available stock, movements and lot genealogy. Mixed weights remain at intake level until explicit verified attribution exists. |
| D · Production | Create identified batches for BSF, compost, fermentation, liquid fertilizer or recyclables. Allocate and consume source lots, record measurements from versioned templates, notes, water and ingredients, losses, outputs and wet/dry basis yield. Review QC, maintain available and quarantined finished stock, and report batch status and lineage. Feed-designated batches require approved input eligibility evidence. |
| E · Finance | Generate recurring contract invoices and manual invoices with line items, IDR totals, configurable tax, numbering, issue and due dates and PDFs. Support pending and verified payments, partial allocations, receivables, overdue view, expenses, revenue and payment history. Prevent duplicate charges and protect issued invoices and verified payments through approved corrections. |

The office interface includes Dasbor, Pelanggan, Kontrak, Pengambilan, Pelacakan Sampah, Produksi, Keuangan, Laporan and Pengaturan. The dashboard shows today’s pickups and completions, verified intake, processing and active batches, pending invoices, outstanding balances and authorized recent activity. Use #174D3B, #B69A62, #FFFFFF, #F7F9F7 and #1F2937 with accessible focus, labels, contrast and responsive layouts. Every screen handles loading, empty, validation, error, success and role-specific access. Driver controls prioritize tap targets, GPS, camera, confirmation, offline state and queue recovery.

## State machines

| Resource | Allowed transitions and guards |
|---|---|
| Customer | Proposed `active ↔ inactive`, then `archived` after open work and active agreements are resolved. Historical references remain intact. |
| Contract | `draft → active`, `active ↔ suspended`, `active/suspended → expired/terminated`. Draft may be terminated with a reason. Expiry is derived by date for job eligibility even before the status job records it. Activation checks effective dates, services, location and applicable rates. |
| Pickup | `scheduled → assigned → en_route → arrived → completed`. Assigned, en_route or arrived may become failed with a reason. Dispatch may cancel scheduled, assigned, en_route or arrived with reason. Completion requires accepted photos, GPS and explicitly typed weight under the proposed policy. Compare expected revision and current assignee on each driver event. |
| Intake and sorting | Intake `draft → posted → reversed` and sorting `draft → pending_review → posted → reversed` with direct draft-to-posted for in-tolerance sorting. An over-tolerance session requires distinct reviewer, reason and unchanged weighed inputs before posting. Reversal uses compensating stock movements and requires downstream stock to be eligible. |
| Production | `draft → ready → in_progress → qc_pending → completed/rejected`. In-progress work may go `on_hold → in_progress`. `draft/ready → canceled`. Start validates actual available and eligible stock atomically. QC pending creates quarantined lots. Approved QC sets passed, releases stock and completes in one transaction. Failed QC rejects and blocks release. |
| Invoice | `draft → issued`, `issued → partially_paid/paid`, `partially_paid → paid`. Approved payment reversals can move status back to issued or partially paid. Draft may be voided with reason. Issued may be voided with independent approval and zero active verified allocation. Voided is terminal. Overdue is computed from Jakarta due date and positive outstanding. |
| Payment | `pending → verified/rejected`. Verification snapshots allocations and appends history. An authorized reversal posts a separate record and recalculates invoices. Verified payment documents remain immutable. |

## Critical business rules

- Recurrence is generated for active contracts and effective schedule occurrences only. Repeated jobs converge on one task per occurrence. Proposed suspension behavior retains existing tasks for dispatch review and prevents new generation.
- A driver completion weight and facility-verified weight are separate records. For per-kg billing, use verified pickup attribution only. Without it, show an exception and retain the pickup as open for later billing review.
- Material balance is input = allocated output + documented loss + signed variance. Residual is classified output. An explicit signed variance and tolerance snapshot are retained even when within tolerance. Production tracks added inputs and loss independently and labels wet/dry measurement bases.
- Invoice amounts freeze on issue. The contract charge occurrence identifies duplicate billing across runs. A correction chain may replace a voided invoice with one active successor using the same charge occurrence. Per-date pricing and month-boundary policies are tracked in [decisions](decisions.md).
- Proposed command guards use explicit transactions and immutable transition records. For recurrence, evaluate both the contract and the occurrence date against active and effective ranges. For billing, keep historical work eligible according to its occurrence even if the agreement was later suspended or terminated. Review this with Finance under B07.

## Required release evidence

Exercise all five workflows using UI and API together. Test active/inactive scheduling, driver completion with GPS/photos, exactly-once offline replay, mixed-load provenance, sorting/production overdraw rejection, simultaneous stock writers, QC release gate, billing retry, partial payments and unauthorized resource access. Tests include unit, PostgreSQL integration, API, Playwright, and mobile-device validation. See [implementation plan](implementation-plan.md).
