# AI Guidelines Overview (Monorepo Contract)

These files are intentionally split by concern for easier maintenance. Read and apply **all** of them.

## How to apply
- **Follow the strictest rule when in doubt** (security > data isolation > correctness > style).
- **If instructions conflict or are impossible**, say so explicitly, explain why, and propose the safest alternative.
- **Never assume UI scoping is security**. Server-side enforcement and validation are mandatory.

## Runtime assumptions
- **PHP**: Target **PHP 8.4+** only.
- **Filament**: Use Filament v5 APIs. Filament v5 is API-compatible with Filament v4; the primary difference is Livewire (v5 uses Livewire v4, v4 uses Livewire v3). When official v5 docs are missing, Filament v4 docs/examples are acceptable.
- **Octane compatibility**: Assume long-lived workers. Avoid request-leaking static mutable state, prefer request-scoped/container-scoped state, and ensure code is safe under Laravel Octane.

## Verification mindset
- Prefer **small, auditable changes** over broad refactors.
- Use per-package checks (tests/PHPStan) instead of repo-wide runs.
- When a guideline requires verification, either run it (if feasible) or call out what must be run by the user.
