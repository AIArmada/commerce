# Filament Vouchers — Lifecycle Field Audit

## 1. Executive Summary

`filament-vouchers` has no database tables. All lifecycle columns live in the `vouchers` domain package. The Filament layer has **stale references to deprecated `is_claimed`/`is_redeemed` booleans** across 4 files, and **status-transition actions that don't set timestamps**. This audit covers only Filament surfaces.

---

## 2. Full Inventory by Filament Surface

### 2.1 VoucherForm (`VoucherResource/Schemas/VoucherForm.php`)

| Field | Type | Problem |
|---|---|---|
| `starts_at` | DateTimePicker | OK — display-only |
| `expires_at` | DateTimePicker | OK — display-only |
| `status` | Select (VoucherStatus::options()) | Defaults to `Active::class` FQCN. Form allows editing to any state — no UI-level guard for valid transitions |
| `allows_manual_redemption` | Toggle | Boolean with no timestamp audit trail |

### 2.2 VouchersTable (`VoucherResource/Tables/VouchersTable.php`)

| Component | Field | Problem |
|---|---|---|
| `status` | TextColumn badge (color-coded via instanceof) | OK — uses state system |
| `starts_at` / `expires_at` | TextColumn dateTime, toggleable | OK |
| `allows_manual_redemption` | IconColumn boolean + `manual_only` filter | Boolean display with no timestamp |
| `active_now` filter | Runtime check: status + starts_at + expires_at | OK — logic correct |

### 2.3 WalletEntriesTable (`VoucherResource/Tables/WalletEntriesTable.php`)

**Stale boolean references (P2):**

| Component | Current | Should Be |
|---|---|---|
| `status` column | Derives from `$record->is_redeemed` / `$record->is_claimed` | Derive from `$record->redeemed_at !== null` / `$record->claimed_at !== null` |
| `is_claimed` TernaryFilter | `where('is_claimed', true/false)` | `whereNotNull('claimed_at')` / `whereNull('claimed_at')` |
| `is_redeemed` TernaryFilter | `where('is_redeemed', true/false)` | `whereNotNull('redeemed_at')` / `whereNull('redeemed_at')` |
| Sort by status | `orderBy('is_redeemed')->orderBy('is_claimed')` | `orderBy('redeemed_at')->orderBy('claimed_at')` |
| `markAsRedeemed` visibility | `! $record->is_redeemed` | `$record->redeemed_at === null` |

### 2.4 VoucherWalletsTable (`VoucherWalletResource/Tables/VoucherWalletsTable.php`)

**Stale boolean references (P2):**

| Component | Current | Should Be |
|---|---|---|
| `is_claimed` IconColumn | `boolean()` | Check `claimed_at IS NOT NULL` |
| `is_redeemed` IconColumn | `boolean()` | Check `redeemed_at IS NOT NULL` |
| `claimed` filter | `where('is_claimed', true)` | `whereNotNull('claimed_at')` |
| `not_redeemed` filter | `where('is_redeemed', false)` | `whereNull('redeemed_at')` |
| `redeemed` filter | `where('is_redeemed', true)` | `whereNotNull('redeemed_at')` |

### 2.5 VoucherWalletResource.php

**Stale boolean references (P2):**

| Method | Current | Should Be |
|---|---|---|
| `getNavigationBadge()` | `where('is_claimed', true)->where('is_redeemed', false)` | `whereNotNull('claimed_at')->whereNull('redeemed_at')` |

### 2.6 VoucherWalletStatsWidget (`Widgets/VoucherWalletStatsWidget.php`)

**Stale boolean references (P2):**

| Query | Current | Should Be |
|---|---|---|
| Claimed count | `where('is_claimed', true)->count()` | `whereNotNull('claimed_at')->count()` |
| Redeemed count | `where('is_redeemed', true)->count()` | `whereNotNull('redeemed_at')->count()` |
| Available count | `where('is_redeemed', false)->count()` | `whereNull('redeemed_at')->count()` |

### 2.7 Actions

#### ActivateVoucherAction (`Actions/ActivateVoucherAction.php`)

| Issue | Details |
|---|---|
| visibility | `! ($record->status instanceof Active)` — OK |
| action | `$record->update(['status' => Active::class])` — **does not set `last_activated_at`** |

#### PauseVoucherAction (`Actions/PauseVoucherAction.php`)

| Issue | Details |
|---|---|
| visibility | `$record->status instanceof Active` — OK |
| action | `$record->update(['status' => Paused::class])` — **does not set `paused_at`** |

#### BulkGenerateVouchersAction (`Actions/BulkGenerateVouchersAction.php`)

| Issue | Details |
|---|---|
| Status assignment | Hardcodes `status => Active::class` inline instead of delegating to a service method |

---

## 3. Problems Summary

### 3.1 Status-transition actions missing timestamps

`ActivateVoucherAction` and `PauseVoucherAction` call `$record->update(['status' => ...])` without setting transition timestamps. When domain adds `paused_at` and `last_activated_at`:

- `ActivateVoucherAction`: must set `last_activated_at = now()` and `paused_at = null`
- `PauseVoucherAction`: must set `paused_at = now()`

### 3.2 21 references to deprecated `is_claimed`/`is_redeemed` booleans

| File | Count | Types |
|---|---|---|
| `WalletEntriesTable.php` | 11 | status display, ternary filters, sort orderBy, action visibility |
| `VoucherWalletsTable.php` | 6 | IconColumn boolean, filters (claimed/redeemed/not_redeemed) |
| `VoucherWalletResource.php` | 2 | navigation badge count |
| `VoucherWalletStatsWidget.php` | 3 | aggregate counts (claimed/redeemed/available) |

All must migrate from boolean reads to timestamp reads.

### 3.3 Missing transition timestamp display

`VoucherInfolist` and `VouchersTable` don't display `paused_at`, `depleted_at`, or `last_activated_at`. When domain adds these columns:

- `VoucherInfolist`: add TextEntry for each in Voucher Overview section
- `VouchersTable`: add toggleable TextColumn for each, hidden by default

### 3.4 Status Select allows invalid transitions

`VoucherForm` status Select displays all `VoucherStatus::options()` without constraining to valid transitions from current state. The server-side state machine blocks invalid transitions, but UI should ideally only offer valid options.

### 3.5 `allows_manual_redemption` boolean with no timestamp display

Toggle in form + IconColumn in table expose a boolean with no `manual_redemption_enabled_at` display. When domain adds the timestamp, add it to infolist and table.

---

## 4. Recommended Filament Changes

### 4.1 WalletEntriesTable — status column

```php
TextColumn::make('claimed_at')
    ->label('Status')
    ->state(function (VoucherWallet $record): string {
        if ($record->redeemed_at !== null) { return 'Redeemed'; }
        if ($record->claimed_at !== null) { return 'Claimed'; }
        return 'Available';
    })
    ->badge()
    ->color(fn (string $state): string => match ($state) {
        'Redeemed' => 'danger', 'Claimed' => 'warning', 'Available' => 'success',
        default => 'gray',
    })
    ->sortable(query: function ($query, string $direction): void {
        $query->orderBy('redeemed_at', $direction)->orderBy('claimed_at', $direction);
    })
```

### 4.2 ActivateVoucherAction

```php
$record->update([
    'status' => Active::class,
    'last_activated_at' => now(),
    'paused_at' => null,
]);
```

### 4.3 PauseVoucherAction

```php
$record->update([
    'status' => Paused::class,
    'paused_at' => now(),
]);
```

### 4.4 VoucherInfolist additions

```php
TextEntry::make('last_activated_at')->label('Last Activated')->dateTime()->placeholder('—')
TextEntry::make('paused_at')->label('Paused At')->dateTime()->placeholder('—')->visible(fn ($r) => $r->paused_at !== null)
TextEntry::make('depleted_at')->label('Depleted At')->dateTime()->placeholder('—')->visible(fn ($r) => $r->depleted_at !== null)
```

### 4.5 VouchersTable additions

```php
TextColumn::make('last_activated_at')->label('Activated')->dateTime()->toggleable(isToggledHiddenByDefault: true)
TextColumn::make('paused_at')->label('Paused')->dateTime()->toggleable(isToggledHiddenByDefault: true)
TextColumn::make('depleted_at')->label('Depleted')->dateTime()->toggleable(isToggledHiddenByDefault: true)
```

---

## 5. Verification Commands

```bash
# 1. Count stale is_claimed/is_redeemed references (should be 0 after fix)
grep -rn "is_claimed\|is_redeemed" packages/filament-vouchers/src --include="*.php"

# 2. Verify wallet queries use timestamp patterns
grep -rn "claimed_at\|redeemed_at" packages/filament-vouchers/src --include="*.php"

# 3. Verify ActivateVoucherAction sets last_activated_at
grep -A5 "update.*Active::class" packages/filament-vouchers/src/Actions/ActivateVoucherAction.php | grep last_activated_at

# 4. Verify PauseVoucherAction sets paused_at
grep -A5 "update.*Paused::class" packages/filament-vouchers/src/Actions/PauseVoucherAction.php | grep paused_at

# 5. PHPStan on filament-vouchers
./vendor/bin/phpstan analyse packages/filament-vouchers/src --level=6

# 6. Run filament-vouchers tests
./vendor/bin/pest --parallel packages/filament-vouchers/tests/

# 7. Pint formatting
./vendor/bin/pint packages/filament-vouchers/src --test

# 8. Check owner scoping in queries
grep -rn "getEloquentQuery\|::query()" packages/filament-vouchers/src --include="*.php"
```
