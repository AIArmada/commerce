---
title: Seating Troubleshooting
---

## Seats not appearing in the Livewire component

Ensure the seat map has at least one section with seats. The layout renderer only renders seats that belong to a section.

## Holds not expiring

Run `php artisan seating:release-expired-holds` manually, or schedule it:

```php
// routes/console.php
Schedule::command('seating:release-expired-holds')->everyMinute();
```

## InsufficientSeatsException

Thrown when `DefaultSeatAllocator::allocate()` cannot fulfill the requested quantity. Check:
- Enough seats are available (not held, not blocked, not sold)
- Category preferences are not too restrictive
- Seat map has sections with seats

## All seat allocator tests fail due to missing database tables

Make sure seating migrations are loaded. If using the project test suite, verify `TestCase::defineDatabaseMigrations()` loads seating migrations.

## Double allocation under concurrency

`ConvertHoldsToAllocationsAction` converts physical seat holds and counts each seat against its section capacity. It locks the seats, holds, and sections in a consistent order. Repeating the same allocation can skip holds already processed. A conflicting allocation aborts the batch. PostgreSQL and SQLite also enforce at most one active allocation per seat with a partial unique index.
