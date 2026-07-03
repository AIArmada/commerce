---
title: Seating Overview
---

# Seating

The `aiarmada/seating` package provides venue seat layout modeling, seat hold and allocation management, and a Livewire seat-map rendering component. It is vendor-agnostic: any Eloquent model (event, venue, etc.) can own a seat map via a polymorphic `seatable` relationship.

## Features

- **Seat Maps** — polymorphic layouts with versioning
- **Sections** — logical grouping of seats (VIP, Standard, etc.)
- **Seats** — individual seats with row/column positioning, status, and category
- **Holds** — temporary time-bounded holds (cart reservations, checkout flow)
- **Allocations** — persistent seat-to-entity assignments (passes, registrations)
- **Livewire Component** — interactive seat picking with status visualization
- **Console Command** — `seating:release-expired-holds` to clean up stale holds
- **Seating Modes** — `none`, `general_admission`, `assigned`, `hybrid` via `SeatingMode` enum
