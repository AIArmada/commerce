# Plan: Ticketing + Seating + Filament Packages

## Goal
Create `aiarmada/seating` and `aiarmada/filament-seating` as new peer packages; enhance `aiarmada/ticketing` with registration morph + scoping columns; move seat contracts from ticketing to seating; wire filament-ticketing to seating.

## Constraints
- Hard cut, no backward-compat aliases, no deprecated shims, no data migration.
- `aiarmada/ticketing` and `aiarmada/filament-ticketing` already exist and must not break.
- `packages/events` and `packages/filament-events` refactor is deferred to a separate execution.
- Livewire `<livewire:seating.seat-map>` component lives in the domain package (`seating`) so non-Filament storefronts can use it.
- Polymorphic `seatable` morph on seat maps (any Eloquent model can own a layout).
- Generic column names on `passes` (`registration_type/id`, `occurrence_id`, `session_id`) to keep ticketing vendor-agnostic.
- Seam-first design: columns ship now; their wiring is deferred.
- Filament v5, `getNavigationGroup()` reads from nested config, no static `$navigationGroup`.
- `SeatAllocatorInterface` moves from ticketing to seating; ticketing requires seating.
- Composer dependency direction: `seating` → `commerce-support`; `ticketing` → `commerce-support` + `seating`; `filament-*` mirrors domain.

## Phase 0: aiarmada/seating (NEW)

### Files created
| Path | Purpose |
|------|---------|
| `packages/seating/composer.json` | Requires `aiarmada/commerce-support`, `spatie/laravel-package-tools`, `livewire/livewire` |
| `packages/seating/CONTEXT.md` | Package context |
| `packages/seating/config/seating.php` | Config (tables, defaults, scheduling, json_column_type) |
| `packages/seating/src/SeatingServiceProvider.php` | Registers config, migrations, views, commands, allocator binding |
| `packages/seating/database/migrations/2000_01_01_000001_create_seat_maps_table.php` | `seat_maps` (uuid PK, nullableMorphs seatable+owner, name, slug, version, status, layout_metadata) |
| `packages/seating/database/migrations/2000_01_01_000002_create_seat_sections_table.php` | `seat_sections` (nullableMorphs owner, seat_map_id FK, name, code, sort_order, capacity) |
| `packages/seating/database/migrations/2000_01_01_000003_create_seats_table.php` | `seats` (nullableMorphs owner, seat_section_id FK, row_label, seat_label, row_number, column_number, category, price_modifier, status, metadata) |
| `packages/seating/database/migrations/2000_01_01_000004_create_seat_holds_table.php` | `seat_holds` (nullableMorphs held_by+owner, seat_id FK, reference, expires_at, metadata) |
| `packages/seating/database/migrations/2000_01_01_000005_create_seat_allocations_table.php` | `seat_allocations` (nullableMorphs owner+allocated_to, seat_id FK, reference, allocated_at, state, metadata) |
| `packages/seating/src/Models/SeatMap.php` | HasOwner, HasUuids, fillable, sections() hasMany, scopeActive, scopeForHost |
| `packages/seating/src/Models/SeatSection.php` | HasOwner, HasUuids, seats() hasMany, scopeFor map |
| `packages/seating/src/Models/Seat.php` | HasOwner, HasUuids, section() belongsTo, holds() hasMany, allocations() hasMany, scopes (available, blocked, byCategory) |
| `packages/seating/src/Models/SeatHold.php` | HasOwner, HasUuids, seat() belongsTo, scopeExpired |
| `packages/seating/src/Models/SeatAllocation.php` | HasOwner, HasUuids, seat() belongsTo |
| `packages/seating/database/factories/*.php` | 5 factories using only `numberBetween`, `randomDigit`, `randomNumber` (Faker 1.24 compatibility) |
| `packages/seating/src/Contracts/SeatAllocatorInterface.php` | Moved from ticketing (with NullSeatAllocator) |
| `packages/seating/src/Contracts/SeatLayoutInterface.php` | Contract for layout description + status check |
| `packages/seating/src/DTOs/AllocationResult.php` | Single seat allocation result |
| `packages/seating/src/DTOs/SeatMapLayout.php` | Layout data for API/view consumption |
| `packages/seating/src/Services/DefaultSeatAllocator.php` | Implements SeatAllocatorInterface |
| `packages/seating/src/Services/NullSeatAllocator.php` | Moved from ticketing |
| `packages/seating/src/Services/SeatLayoutRenderer.php` | Implements SeatLayoutInterface |
| `packages/seating/src/Exceptions/InsufficientSeatsException.php` | Thrown when allocation fails |
| `packages/seating/src/Console/Commands/ReleaseExpiredHoldsCommand.php` | `seating:release-expired-holds` |
| `packages/seating/src/Livewire/SeatMap.php` | Livewire component (aliases model import as `SeatMapModel`) |
| `packages/seating/resources/views/livewire/seat-map.blade.php` | Grid layout with legend, pick/deselect/clear |
| `packages/seating/docs/01-overview.md` through `99-troubleshooting.md` | 5 docs files |
| `tests/src/Seating/` | 7 test files (Architecture, Feature, Unit), 31 tests |

### Test fixes encountered
- `Pest.php` missing `'src/Seating'` → tests ran bare `PHPUnit\Framework\TestCase`
- All 5 migrations missing `nullableMorphs('owner')` — all models use `HasOwner`
- `SeatSection` factory missing `capacity` (not null in DB)
- Allocator test needed 15 seats (1 held/blocked leaves enough for `quantity: 10`)
- Faker 1.24: only `numberBetween`, `randomDigit`, `randomNumber`, `ean13`, `ean8`, `isbn10`, `isbn13`, `bloodType`, `bloodRh`, `bloodGroup`, `mimeType`, `fileExtension`, `filePath`, `semver` are built-in — no `words`, `sentence`, `company`, `uuid`, `slug`, `colorName`, `domainWord`, `randomElement`
- Livewire 4.3.1 + Laravel 13 incompatibility: `SupportValidation::render` calls `$component->getErrorBag()` which returns null → `ViewErrorBag::put('default', null)` throws TypeError. Workaround: test component methods directly instead of `Livewire::test()`

### Root composer.json changes
- Autoload: `AIArmada\Seating\` → `packages/seating/src`, `AIArmada\Seating\Database\Factories\` → `packages/seating/database/factories`
- Repositories: `packages/seating` added

### TestCase.php changes
- `SeatingServiceProvider::class` registered
- Seating migrations loaded alongside ticketing migrations

## Phase 0': aiarmada/filament-seating (NEW)

- `packages/filament-seating/composer.json` (requires `aiarmada/seating`, `filament/filament`)
- `packages/filament-seating/config/filament-seating.php` (nested `navigation.group`)
- `packages/filament-seating/src/FilamentSeatingServiceProvider.php`
- `packages/filament-seating/src/Resources/SeatMapResource.php` (getNavigationGroup from config)
- `packages/filament-seating/src/Pages/SeatMapEditor.php`
- `packages/filament-seating/src/Pages/SeatMapOccupancy.php`
- `packages/filament-seating/src/Widgets/SeatMapOverview.php`
- Root composer.json: autoload + repositories
- TestCase.php: register service provider, load config & migrations
- 5 docs files

## Phase 1: Ticketing schema additions

- Migration `000008_add_registration_and_scoping_to_passes`:
  - `$table->nullableMorphs('registration')`
  - `$table->nullableUuid('occurrence_id')->index()`
  - `$table->nullableUuid('session_id')->index()`
- `Pass` model: add relationships (`registration`, `occurrence`, `session`), scopes
- Update `passes` fillable and casts
- Tests

## Phase 2: Move SeatAllocatorInterface

- Delete `SeatAllocatorInterface` and `NullSeatAllocator` from `packages/ticketing`
- Add `"aiarmada/seating": "*"` to ticketing's composer.json `require`
- Update `IssuePassesAction` to inject `?SeatAllocatorInterface` and stamp seat assignment on passes
- Fix tests

## Phase 3: Filament-ticketing wiring

- `SeatAssignment` Filament page (choose map, assign seats to passes)
- `SeatMapPicker` Filament widget
- Update `packages/filament-ticketing/composer.json` with seating dependency
- Config updates
- Tests

## Phase 4: Docs & plumbing

- Changelogs per package
- ADR `0007-seating-package-extraction`
- Root docs cross-linking
- Update CONTEXT.md for ticketing and filament-ticketing

## Phase 5: Verification

- `./vendor/bin/pest --parallel` on all 4 packages
- `./vendor/bin/phpstan --level=6` on all 4 packages
- `./vendor/bin/pint` on changed packages only
- `composer validate`
- Architectural compliance audit

## Deferred
- Events refactor (separate execution)
- Data migration of legacy tables
