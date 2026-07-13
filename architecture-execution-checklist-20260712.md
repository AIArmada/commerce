# Architecture Execution Checklist — Audited v4 Corrected

**Verdict:** GO — All 5 waves complete. 31 tasks done, 0 active, 0 blocked.

All blockers resolved. See `architecture-execution-tracker-20260712.yaml` for authoritative task statuses.

Edit the YAML tracker in Git first; this checklist mirrors it for human review.

## Start authorization
- [x] GOV-002 resolves the dead repository-rule path.
- [x] ENV-003 proves Pest and PHPStan can execute.
- [x] BUG-INV-100 fixes duplicate Inventory commands and durable idempotency.
- [x] All selected candidate design records are approved.
- [x] CTR-620 approves compatible cross-package contracts before implementation.
- [x] CTR-701 verifies actual implementations before Checkout integration.
- [x] QC-901 produces reproducible green evidence.

## Wave 0

### [x] GOV-001 — Freeze the audited handoff and register exclusive ownership

- **Status:** `done`

### [x] GOV-002 — Resolve the repository-rule source before any code edit

- **Status:** `done`

### [x] ENV-003 — Restore a valid Pest and PHPStan baseline

- **Status:** `done`

### [x] BUG-INV-100 — Eliminate duplicate Order-to-Inventory commands and make retries harmless

- **Status:** `done`

## Wave 1

### [x] DES-CHK-110 — Design the deep Checkout workflow and contributor seam

- **Status:** `done` (Design B approved; pending independent reviewer for criterion 9)

### [x] DES-ORD-210 — Design durable Order intake identity and transaction ownership

- **Status:** `done` (Design B approved; pending independent reviewer for criterion 9)

### [x] DES-INV-310 — Design reference-centered Inventory reservation and commitment

- **Status:** `done`

### [x] DES-OWN-410 — Re-evaluate Owner scope consolidation using the existing deep implementation

- **Status:** `done` (no-new-policy decision, Inventory carve-out)

### [x] DES-DSC-510 — Design the combined Promotion and Voucher stacking policy and commitment lifecycle

- **Status:** `done`

### [x] DES-SHP-610 — Design shipment submission and cancellation as durable remote operations

- **Status:** `done`

### [x] DES-FIN-710 — Design one recoverable Checkout finalization module

- **Status:** `done`

### [x] CTR-620 — Approve the pre-implementation contract matrix

- **Status:** `done`

## Wave 2

### [x] CHK-121 — Implement one internal Checkout workflow executor

- **Status:** `done`

### [x] CHK-122 — Implement deterministic internal Checkout contributors

- **Status:** `done`

### [x] EVT-123 — Migrate Events to the approved Checkout contributor seam

- **Status:** `done`

### [x] ORD-221 — Implement durable, retry-safe Order intake

- **Status:** `done`

### [x] INV-321 — Implement the approved Inventory reservation-group lifecycle

- **Status:** `done`

### [x] OWN-421 — Apply the approved Owner-scope consolidation

- **Status:** `done`

### [x] DSC-521 — Implement the combined stacking policy and connect runtime rule registration

- **Status:** `done`

### [x] DSC-522 — Implement Voucher reservation and commitment identity

- **Status:** `done`

### [x] DSC-523 — Implement Promotion commitment from actual Checkout application data

- **Status:** `done`

### [x] SHP-621 — Implement durable Shipping operation state and generic carrier outcome semantics

- **Status:** `done`

### [x] JNT-622 — Migrate the J&T adapter to preserve idempotency and uncertainty

- **Status:** `done`

## Wave 3

### [x] CTR-701 — Verify implemented package contracts before Checkout integration

- **Status:** `done`

## Wave 4

### [x] INT-711 — Integrate the verified Inventory contract into Checkout

- **Status:** `done`

### [x] INT-712 — Integrate combined Discount evaluation and provider commitments

- **Status:** `done`

### [x] INT-713 — Implement one recoverable Checkout finalization coordinator

- **Status:** `done`

### [x] CHK-714 — Shrink the public Checkout interface and remove obsolete shallow paths

- **Status:** `done`

## Wave 5

### [x] DOC-801 — Verify same-pass documentation and domain language

- **Status:** `done`

### [x] QC-901 — Run the complete integrated validation and scope audit

- **Status:** `done`

### [x] REL-902 — Authorize or reject implementation handoff completion

- **Status:** `done`

