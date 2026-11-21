---
name: laravel-dune-correct
description: Turn recurring Commerce package development mistakes into shared boundary fixes, static checks, or focused regression tests.
---

# Laravel Dune — Prevent Repeated Package Mistakes

Read `CONTEXT-MAP.md`, the owning `packages/<pkg>/CONTEXT.md`, its canonical docs, and matching `.ai/rules/index.md` entries before discovery or edits. Use graph tools for code discovery. Follow the root [Dune guideline](../../guidelines/laravel-dune.blade.php) and [verification rule](../../rules/verification-and-hygiene.md).

1. Find repeated failures in the supplied corrections, current diff, or relevant history. Group by root cause and identify affected packages.
2. Prefer one correction at the shared boundary over duplicated guards. Consider types/static analysis next, then behavioral or architecture tests. Use a prose rule for judgment that cannot be enforced mechanically.
3. Reuse installed checking infrastructure. A new check must reject a representative bad case and pass the supported case; its failure should name the supported operation.
4. Keep rule paths and `.ai/rules/index.md` synchronized when changing guidance. Label proposed checks as proposed until implemented and run.
5. Verify affected packages. Retain useful contract/generic docblocks and non-obvious explanations; limit cleanup to the requested scope.

Complete when each accepted mistake has a demonstrated prevention mechanism or an explicitly identified judgment rule. Report evidence and remaining gaps.
