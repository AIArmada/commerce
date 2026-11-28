---
name: filament-inventory-development
description: Develop inventory administration through owner-scoped stock and reservation operations.
---

# Filament Inventory

Use InventoryService for stock changes. Validate writable location scope before accepting form submissions, use integer quantities, and retain canonical ledger movements. Keep widget statistics in InventoryStatsAggregator and cache operations in InventoryStatsCache. Verify package architecture and affected Pest tests, including forged location IDs, global read-only locations and cycle count variances.
