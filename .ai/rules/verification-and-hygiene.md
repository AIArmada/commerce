---
paths:
  - packages/**
  - tests/**
  - demo/**
  - .ai/**
---

# Package Verification and Hygiene

## Scope and evidence

Define observable success criteria. Locate the owning package's tests through Composer mappings and test configuration. Run affected tests with `--parallel`, including cross-tenant read/write cases for ownership changes. Verify affected paired adapters and contract consumers when applicable.

Run Pint on changed PHP files and PHPStan on changed package source at level 6. Pipe test/analysis output with `2>&1 | tee /tmp/<task-specific-log>.txt`; preserve failure status with shell `pipefail`. Avoid repository-wide formatting and test sweeps.

Use existing tests before creating a separate verification harness. For runtime-only integration behavior, use the existing demo/test host and inspect observable responses and persisted effects in isolated test data. Avoid mutating developer data or caches as routine verification.

## Honest enforcement

Use only checks actually installed and wired in this repository. The standalone-app Dune scaffold is not the root architecture. Do not claim its AST ownership rules or manifest/cache tooling enforce package boundaries.

For a recurring mistake, prefer a shared boundary correction, types/static analysis, or a focused behavioral/architecture test. Prove any new check rejects the bad behavior and accepts the supported path. Record judgment-only rules in the narrowest matching rule file and synchronize the index.

## Surgical changes

Retain useful PHPDoc generics, public contracts, config section headers, and explanations of non-obvious behavior. Remove only comments and code made obsolete by the requested change. Fix static analysis at its source; inspect runtime macros and extensions before deleting apparently undefined methods.
