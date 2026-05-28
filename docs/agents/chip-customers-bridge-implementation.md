---
title: CHIP Customers Bridge Implementation
---

# CHIP Customers Bridge Implementation

## Goal

Implement the new architecture now, with package-owned storage as the production path and explicit compatibility shims where needed:

- `commerce-support` owns the neutral payment-subject resolution seam.
- `customers` provides the preferred default subject driver.
- `chip` owns the subject ↔ CHIP customer bridge.
- `cashier-chip` owns payment-method and recurring-billing state on top of the CHIP bridge.
- `checkout` consumes the neutral resolver instead of directly owning customer resolution.

## Decisions

- New production paths use package-owned storage instead of billable model columns.
- Legacy-style accessors may remain as compatibility shims, but they must resolve through the package-owned stores.
- No waiting for a second pass; package docs, code, and tests ship together.
- Keep changes package-scoped and auditable.

## Workstreams

### 1. Support seam

- [x] Add `PaymentSubjectContext`, `ResolvedPaymentSubject`, and `PaymentCustomerData`.
- [x] Add driver + resolver contracts and default resolver implementation.
- [x] Register the resolver in `commerce-support`.

### 2. Customers driver

- [x] Add the default `customers` payment-subject driver.
- [x] Register the driver from `CustomersServiceProvider`.

### 3. CHIP customer bridge

- [x] Add `chip_customers` persistence.
- [x] Add bridge model + directory contract/service.
- [x] Register the bridge in `ChipServiceProvider`.

### 4. Cashier CHIP storage refactor

- [x] Add package-owned payment-method persistence.
- [x] Refactor billable customer lookup to use the CHIP bridge.
- [x] Refactor customer/payment-method/charge traits to use the new stores.
- [x] Refactor listeners and payment wrapper code to stop reading model columns.

### 5. Checkout integration

- [x] Add payment billable reference support to checkout sessions.
- [x] Replace direct `CustomerResolver` coupling with `PaymentSubjectResolverInterface`.
- [x] Update payment processors to use the resolved subject.

### 6. Review / audit loop

- [x] Run formatter(s) on changed PHP files/packages.
- [x] Run targeted tests for `commerce-support`, `chip`, `cashier-chip`, `checkout`, `filament-cashier`, and `filament-cashier-chip`.
- [x] Run package-scoped PHPStan where impacted.
- [x] Audit changed code and docs for remaining `chip_id` / payment-method column assumptions.
- [x] Fix findings and repeat until clean.

### 7. Post-checkout remediation sweep

- [x] Fix `chip-customers` admin crashes for billable models without a generic `trial_ends_at` column.
- [x] Preserve multi-word customer names instead of collapsing them to first+last token only.
- [x] Clarify cart snapshot vs cart identifier semantics in order/view metadata.
- [x] Re-verify purchaser company persistence in customer + order checkout flows.
- [x] Capture participant company details in checkout, fulfill them into event registrations, and surface them in Filament registrations.
- [x] Surface persisted customer company data in the Filament CRM customer resource.
- [x] Run a fresh browser checkout and verify the webhook-completed order/session flow against the database and Filament.

## Progress log

- Created implementation plan.
- Added the neutral payment-subject seam in `commerce-support` and registered the default guest resolver.
- Registered the default `customers` payment-subject driver.
- Added the `chip_customers` bridge in `chip` and moved customer lookup onto package-owned linkage.
- Moved `cashier-chip` customer/payment-method state off billable columns and onto package-owned storage.
- Switched `cashier-chip` subscriptions and checkout metadata to billable morphs.
- Updated `checkout` to store and propagate the resolved billable subject.
- Began the review/audit loop across tests, Filament surfaces, and package docs.
- Completed the review/audit loop: fixed runtime webhook renewal typing, Filament payment-method option handling, dynamic billable access in Filament resources, and removed the last stale CHIP-customer comment.
- Verified the changed surfaces with focused Pest runs, package-scoped PHPStan on impacted packages, formatter runs, and grep-based stale-assumption audits.
- Fixed the `chip-customers` Filament resource to tolerate billable models that do not store model-level trial timestamps.
- Preserved multi-word customer surnames in the customers package and locked that behavior with regression tests.
- Stopped exposing the internal cart row UUID as the fallback human reference by persisting and preferring the stable cart identifier alongside the immutable checkout snapshot.
- Verified the `chip-customers` admin list and record detail pages render successfully in the browser against `unfair.test` after the resource fix.
- Added participant company capture in the `unfair` checkout form/request flow, propagated that data into order billing metadata, and persisted it as a first-class `company` field on event registrations.
- Surfaced participant company values in the Filament Events registrations resource and buyer company values in the Filament CRM customer resource.
- Verified a fresh browser checkout (`019e6b63-90ef-71d9-9948-6c6f6db16e48`) through CHIP payment handoff, then simulated both CHIP and checkout webhooks against the live app, confirmed the completed order (`ORD-20260527-O8WNZ6TL`) plus registration/customer data in SQL, and verified the same data in Filament Orders, Registrations, CRM Customers, CHIP Purchases, and CHIP Clients pages.
