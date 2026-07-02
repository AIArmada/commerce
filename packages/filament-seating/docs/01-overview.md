---
title: Overview
---

# Filament Seating

Filament admin UI for managing venue seat layouts: seat maps, sections, occupancy visualization, and layout management.

## What it owns

- Filament resource for `SeatMap` CRUD
- Seat map overview dashboard widget

> **Planned:** Visual seat map editor, occupancy viewer, and admin-only workflow actions for seat layout configuration are stubbed but not yet implemented.

## What it does NOT own

- Seat layout rendering, allocation logic, hold management (see `seating`)
- Ticketing, passes, or event wiring (see `ticketing`, `filament-ticketing`)

## Requirements

- PHP 8.4+
- Laravel 13+
- Filament v5
- `aiarmada/seating`
