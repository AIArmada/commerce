---
name: inventory-development
description: Implement stock movements and scoped capacity using Inventory domain boundaries.
---

# Inventory

Use named stock actions for receiving, shipping, transfer and adjustment. Use the checkout reservation contract with immutable request/result DTOs. Use `ScopedCapacityReservationServiceInterface` for non-warehouse capacity and never access feature-private capacity models. Resolve explicit owner context before mutations. Verify stock and reservation invariants with Pest and package architecture checks.
