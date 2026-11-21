---
paths:
  - packages/*/src/Contracts/**
  - packages/*/src/Contracts.php
  - packages/*/src/Data/**
  - packages/*/src/DTOs/**
  - packages/*/src/Api/**
  - demo/app/Features/*/Api/**
  - demo/app/Shared/Contracts/**
---

# Public API & Shared Contracts

> **Monorepo root:** Applies to package contract/DTO/API surfaces under `packages/*/src/{Contracts,Data,DTOs,Api}` as well as `demo/app/Features/*/Api/**` and `demo/app/Shared/Contracts/**`.

## Pure Immutable Boundary Shapes
- All classes in `app/Features/*/Api/**` and `app/Shared/Contracts/**` must be `interface`, `enum`, or `final readonly class` value objects / DTOs.
- NEVER expose Eloquent `Model`, `Builder`, `Collection`, `Request`, `Response`, or any `Illuminate\*` runtime type in public signatures or DTO properties. Use scalar/branded IDs, backed enums, `CarbonImmutable`, or immutable DTOs.

## Parse Once at the Boundary
- Validate and parse external/untrusted payloads once at the transport or Host boundary into typed DTOs. Inside features, trust typed domain values—do not duplicate defensive null/array checks.
- Public contracts may depend only on PHP built-ins, backed enums, `App\Shared\Contracts\*`, or another feature's `Api\*` types.
