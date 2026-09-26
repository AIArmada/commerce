---
title: Actions Reference
---

# Actions Reference

Complete reference for all table and form actions provided by the plugin.

## AffiliateSiteResource Actions

### Table Actions

| Action | Icon | Color | Visibility | Description |
|--------|------|-------|------------|-------------|
| Edit | - | - | Always | Open edit form |
| Sync catalog | `heroicon-o-arrow-path` | Info | Always | Pull the merchant catalog now (requires confirmation) |
| Verify | `heroicon-o-check-badge` | Success | When `isPending()` | Manually verify site |
| Reject | `heroicon-o-x-mark` | Danger | When `isPending()` | Reject site |
| Suspend | `heroicon-o-pause` | Warning | When `isVerified()` | Suspend site |
| Reinstate | `heroicon-o-play` | Success | When `isSuspended()` | Restore to verified |

### Verify Action Implementation

```php
Tables\Actions\Action::make('verify')
    ->icon('heroicon-o-check-badge')
    ->color('success')
    ->requiresConfirmation()
    ->visible(fn (AffiliateSite $record): bool => $record->isPending())
    ->action(function (AffiliateSite $record): void {
        self::transitionSite($record, [
            'status' => AffiliateSite::STATUS_VERIFIED,
            'verified_at' => CarbonImmutable::now(),
        ]);
    });
```

Every status transition goes through the table's private
`transitionSite()` helper, which re-reads the row under a lock and re-checks
site verification before writing.

### Bulk Actions

| Action | Description |
|--------|-------------|
| Delete | Delete selected sites (in a `BulkActionGroup`) |

---

## AffiliateOfferResource Actions

### Table Actions

| Action | Icon | Color | Visibility | Description |
|--------|------|-------|------------|-------------|
| Edit | - | - | Always | Open edit form |
| Activate | `heroicon-o-play` | Success | When not published | Publish via `UpdateOffer` (stamps `published_at`) |
| Pause | `heroicon-o-pause` | Warning | When published | Archive via `UpdateOffer` (stamps `archived_at`) |

Activate is disabled until the owning site is verified. Both transitions
run inside the site's owner context and fire the domain `OfferUpdated`
event.

### Bulk Actions

| Action | Description |
|--------|-------------|
| Delete | Delete selected offers |

### Relation Managers

| Manager | Tab | Actions |
|---------|-----|---------|
| `LinksRelationManager` | Links | Reconcile offer, Reconcile (prove legs posted to the merchant ledger exactly once) |
| `LegsRelationManager` | Legs | Reverse (posted legs only; records a reason and posts the negated companion leg) |

```php
// Legs reverse action
Action::make('reverse')
    ->label('Reverse')
    ->color('danger')
    ->visible(fn (NetworkConversionLeg $record): bool => $record->status === LegStatus::Posted)
    ->form([
        TextInput::make('reason')->required()->maxLength(120),
    ])
    ->action(fn (NetworkConversionLeg $record, array $data) =>
        app(NetworkBooks::class)->reverse($record, $data['reason']));
```

---

## AffiliateSiteResource Header Actions

The site edit page carries two header actions besides save/delete:

| Action | Icon | Color | Description |
|--------|------|-------|-------------|
| Sync catalog | `heroicon-o-arrow-path` | Info | Pull the merchant catalog now (requires confirmation) |
| Rotate catalog token | `heroicon-o-key` | Warning | Issue a fresh postback token (requires confirmation) |
| Delete | - | Danger | Delete the site |

```php
Actions\Action::make('rotate_catalog_token')
    ->label('Rotate catalog token')
    ->requiresConfirmation()
    ->modalDescription('The previous token stops working immediately. Copy the new token now — it is shown once.')
    ->action(function (AffiliateSite $record): void {
        $token = $record->rotateCatalogToken();

        Notification::make()
            ->title('New catalog token')
            ->body($token)
            ->persistent()
            ->send();
    });
```

---

## AffiliateOfferCategoryResource Actions

### Table Actions

| Action | Description |
|--------|-------------|
| Edit | Open edit form |

### Bulk Actions

| Action | Description |
|--------|-------------|
| Delete | Delete selected categories |

### Special Features

- **Reorderable**: Categories support drag-and-drop reordering via `sort_order` column
- **Default Sort**: Sorted by `sort_order` ascending

---

## AffiliateOfferApplicationResource Actions

### Table Actions

| Action | Icon | Color | Visibility | Description |
|--------|------|-------|------------|-------------|
| Approve | `heroicon-o-check` | Success | When `isPending()` | Approve application |
| Reject | `heroicon-o-x-mark` | Danger | When `isPending()` | Reject with reason |
| Revoke | `heroicon-o-no-symbol` | Danger | When `isApproved()` | Revoke approval |
| View | - | - | Always | View application details |

### Approve Action Implementation

```php
Tables\Actions\Action::make('approve')
    ->icon('heroicon-o-check')
    ->color('success')
    ->requiresConfirmation()
    ->visible(fn (AffiliateOfferApplication $record): bool => $record->isPending())
    ->action(function (AffiliateOfferApplication $record): void {
        app(OfferManagementService::class)->approveApplication(
            $record,
            self::getReviewerName()
        );

        Notification::make()
            ->title('Application approved')
            ->success()
            ->send();
    });
```

### Reject Action Implementation

```php
Tables\Actions\Action::make('reject')
    ->icon('heroicon-o-x-mark')
    ->color('danger')
    ->form([
        Textarea::make('reason')
            ->label('Rejection Reason')
            ->required(),
    ])
    ->visible(fn (AffiliateOfferApplication $record): bool => $record->isPending())
    ->action(function (AffiliateOfferApplication $record, array $data): void {
        app(OfferManagementService::class)->rejectApplication(
            $record,
            $data['reason'],
            self::getReviewerName()
        );

        Notification::make()
            ->title('Application rejected')
            ->warning()
            ->send();
    });
```

### Revoke Action Implementation

```php
Tables\Actions\Action::make('revoke')
    ->icon('heroicon-o-no-symbol')
    ->color('danger')
    ->form([
        Textarea::make('reason')
            ->label('Revocation Reason')
            ->required(),
    ])
    ->visible(fn (AffiliateOfferApplication $record): bool => $record->isApproved())
    ->action(function (AffiliateOfferApplication $record, array $data): void {
        app(OfferManagementService::class)->revokeApplication(
            $record,
            $data['reason'],
            self::getReviewerName()
        );

        Notification::make()
            ->title('Application revoked')
            ->warning()
            ->send();
    });
```

### Bulk Actions

| Action | Icon | Color | Description |
|--------|------|-------|-------------|
| Approve Selected | `heroicon-o-check` | Success | Bulk approve pending applications (in a `BulkActionGroup`) |

### Bulk Approve Implementation

```php
Tables\Actions\BulkAction::make('approve_selected')
    ->label('Approve Selected')
    ->icon('heroicon-o-check')
    ->color('success')
    ->requiresConfirmation()
    ->action(function ($records): void {
        $service = app(OfferManagementService::class);
        $reviewer = self::getReviewerName();

        foreach ($records as $record) {
            if ($record->isPending()) {
                $service->approveApplication($record, $reviewer);
            }
        }

        Notification::make()
            ->title('Applications approved')
            ->success()
            ->send();
    });
```

---

## Adding Custom Actions

> **warning:**
> `AffiliateSiteResource`, `AffiliateOfferResource`,
> `AffiliateOfferCategoryResource`, and `AffiliateOfferApplicationResource` are
> all `final`. You cannot bolt a `suspend` action onto the shipped site table by
> subclassing it. In Filament v5, `table()` is an **instance** method and
> columns/actions live in a `Tables\XTable` class — override the table class on
> your own resource instead.

### Add Action to a Site Table

```php
<?php

namespace App\Filament\Resources\AffiliateSiteResource\Tables;

use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource;
use Filament\Tables;
use Filament\Tables\Table;

class AffiliateSitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->actions([
                Tables\Actions\EditAction::make(),

                // Add suspend action
                Tables\Actions\Action::make('suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isVerified())
                    ->action(fn (AffiliateSite $record) => $record->update([
                        'status' => AffiliateSite::STATUS_SUSPENDED,
                    ])),

                // Add reinstate action
                Tables\Actions\Action::make('reinstate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateSite $record): bool => $record->isSuspended())
                    ->action(fn (AffiliateSite $record) => $record->update([
                        'status' => AffiliateSite::STATUS_VERIFIED,
                    ])),
            ]);
    }
}
```

Note: the shipped table already provides `suspend` and `reinstate`. This
example is only meaningful for a resource you own.

### Add Action to an Offer Table

```php
<?php

namespace App\Filament\Resources\AffiliateOfferResource\Tables;

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\CommerceSupport\Support\MoneyFormatter;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;

class AffiliateOffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->actions([
                Tables\Actions\EditAction::make(),

                // Add duplicate action
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (AffiliateOffer $record) {
                        $newOffer = $record->replicate();
                        $newOffer->name = $record->name . ' (Copy)';
                        $newOffer->slug = $record->slug . '-copy-' . time();
                        $newOffer->status = OfferStatus::Draft;
                        $newOffer->save();

                        Notification::make()
                            ->title('Offer duplicated')
                            ->success()
                            ->send();
                    }),

                // Add export stats action
                Tables\Actions\Action::make('export_stats')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (AffiliateOffer $record) => response()->streamDownload(
                        function () use ($record) {
                            echo "Link Slug,Clicks,Conversions,Revenue\n";
                            foreach ($record->links as $link) {
                                echo $link->link?->slug
                                    . ',' . $link->clicks
                                    . ',' . $link->conversions
                                    . ',' . $link->revenue . "\n";
                            }
                        },
                        "{$record->slug}-stats.csv"
                    )),
            ]);
    }
}
```

> **warning:**
> Do not spread money in a string concat or divide by 100. Use
> `MoneyFormatter::formatMinor($link->revenue, $link->currency)` — the
> minor-unit scale is currency-dependent.

---

## Action Notifications

All actions use Filament's notification system:

```php
use Filament\Notifications\Notification;

// Success notification
Notification::make()
    ->title('Operation successful')
    ->success()
    ->send();

// Warning notification
Notification::make()
    ->title('Warning message')
    ->warning()
    ->send();

// Persistent notification (doesn't auto-dismiss)
Notification::make()
    ->title('Link Generated')
    ->body($trackingUrl)
    ->success()
    ->persistent()
    ->send();
```

---

## Action Authorization

Actions respect Filament's authorization:

```php
Tables\Actions\Action::make('approve')
    ->authorize('approve')  // Checks policy method
    ->action(function ($record) {
        // Only executed if authorized
    });
```

Or with custom logic:

```php
Tables\Actions\Action::make('approve')
    ->visible(fn () => auth()->user()->can('approve', AffiliateOfferApplication::class))
    ->action(function ($record) {
        // ...
    });
```
