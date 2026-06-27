# Package Audit — `customers`

## 1. Audit metadata

- **Path:** `packages/customers`
- **Version:** self.version (monorepo)
- **Package type:** Library — domain (Laravel package)
- **Language/framework:** PHP 8.4 / Laravel
- **Audit date:** 2026-06-27
- **Commit:** 7d1dc95fa
- **Auditor:** Automated (AI)
- **Overall status:** Ready
- **Overall confidence:** High

## 2. Executive assessment

Customer domain package with 5 models (Customer, Address, Segment, CustomerGroup, CustomerNote), 4 events, 5 actions, 3 enums, 3 concern traits, 2 services, 8 migrations, and translation files. PHPStan and Pint pass clean. 23 test files exist.

All models use `$guarded = ['id']` — not `$fillable`, not `$guarded = []`. While better than no protection, it's weaker than explicit `$fillable`. 7 of 8 migrations contain `down()` methods. No exception hierarchy.

## 3. Key components

- **5 models:** `Customer` (Auditable, HasMedia, Tags, ContactMethods, SocialProfiles, Owner-scoped, lifecycle timestamps), `Address` (billing/shipping defaults), `Segment` (automatic conditions engine), `CustomerGroup` (membership with roles/spending limits), `CustomerNote` (internal/visible, pinned)
- **4 events:** `CustomerCreated`, `CustomerUpdated`, `CustomerAddedToSegment`, `CustomerSegmentChanged`
- **5 actions:** `CreateCustomer`, `UpdateCustomerProfile`, `AssignCustomerToSegment`, `RemoveCustomerFromSegment`, `RebuildAllSegments`
- **2 services:** `CustomerResolver` (665 lines — customer merging, address sync), `SegmentationService` (262 lines)
- **3 enums:** `AddressType`, `CustomerStatus` (with `canPlaceOrders()`), `SegmentType`
- **1 payment driver:** `CustomersPaymentSubjectDriver` for checkout integration
- **2 traits:** `HasCustomerProfile` (contract + concern), `IsCustomerOwned`, `IsCustomerRelated`
- **3 factories** with state macros
- **8 migrations** (7 with `down()`)
- **23 test files** covering models, policies, cross-tenant isolation, and activity logging
- **Translations:** EN + MS locale files

## 4. Findings

### CST-001 `$guarded = ['id']` on all models

All 5 models use `$guarded = ['id']` instead of `$fillable`. Allows mass assignment on any attribute except `id`.

**Recommendation:** Replace with explicit `$fillable` arrays.

### CST-002 7 of 8 migrations have `down()`

Minor convention inconsistency.

### CST-003 No exception hierarchy

No `src/Exceptions/` directory.

### CST-004 No CHANGELOG

## 5. Final rating

| Dimension | Rating | Notes |
|-----------|--------|-------|
| Functional correctness | Excellent | Customer resolver, segment engine, address management |
| Security | Good | `$guarded = ['id']` — better than `[]` but not tight |
| Reliability | Good | 4 events, 5 actions, 23 tests |
| Maintainability | Good | Clean structure, 5 doc files, translations |
| Test quality | Good | 23 test files |
| Documentation | Good | 5 doc files + translations |
| Release readiness | Ready | |

**Summary of findings: 4 (0 Critical, 0 Medium, 4 Low)**
