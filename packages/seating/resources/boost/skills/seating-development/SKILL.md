---
name: seating-development
description: Develop and integrate seating using owner-safe domain operations.
---

# Seating development

Seating owns seat maps, sections, seats, holds and allocations. Use EnsureSeatHoldAction for hold creation, ConvertHoldsToAllocationsAction for conversion, EnsureSectionAllocationAction for capacity allocation, and ReleaseAllocationsAction for release. The allocator delegates to the canonical hold action. Always preserve owner scoping and transaction locking. Livewire selections are not reservations.

## Verification

Run the package architecture checks, affected monorepo Pest tests, PHPStan and Pint. Update callers directly; do not introduce compatibility aliases or backfill migrations.
