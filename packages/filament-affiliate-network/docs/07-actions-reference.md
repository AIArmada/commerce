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
| Verify | `heroicon-o-check-badge` | Success | When `isPending()` | Manually verify site |

### Verify Action Implementation

```php
Tables\Actions\Action::make('verify')
    ->icon('heroicon-o-check-badge')
    ->color('success')
    ->requiresConfirmation()
    ->visible(fn (AffiliateSite $record): bool => $record->isPending())
    ->action(function (AffiliateSite $record): void {
        $record->update([
            'status' => AffiliateSite::STATUS_VERIFIED,
            'verified_at' => now(),
        ]);
    });
```

### Bulk Actions

| Action | Description |
|--------|-------------|
| Delete | Delete selected sites |

---

## AffiliateOfferResource Actions

### Table Actions

| Action | Icon | Color | Visibility | Description |
|--------|------|-------|------------|-------------|
| Edit | - | - | Always | Open edit form |
| Activate | `heroicon-o-play` | Success | When not active | Set status to active |
| Pause | `heroicon-o-pause` | Warning | When active | Set status to paused |

### Activate Action Implementation

```php
Tables\Actions\Action::make('activate')
    ->icon('heroicon-o-play')
    ->color('success')
    ->requiresConfirmation()
    ->visible(fn (AffiliateOffer $record): bool => $record->status !== AffiliateOffer::STATUS_ACTIVE)
    ->action(fn (AffiliateOffer $record) => $record->update(['status' => AffiliateOffer::STATUS_ACTIVE]));
```

### Pause Action Implementation

```php
Tables\Actions\Action::make('pause')
    ->icon('heroicon-o-pause')
    ->color('warning')
    ->requiresConfirmation()
    ->visible(fn (AffiliateOffer $record): bool => $record->status === AffiliateOffer::STATUS_ACTIVE)
    ->action(fn (AffiliateOffer $record) => $record->update(['status' => AffiliateOffer::STATUS_PAUSED]));
```

### Bulk Actions

| Action | Description |
|--------|-------------|
| Delete | Delete selected offers |

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
            static::getReviewerName()
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
            static::getReviewerName()
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
            static::getReviewerName()
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
| Approve Selected | `heroicon-o-check` | Success | Bulk approve pending applications |

### Bulk Approve Implementation

```php
Tables\Actions\BulkAction::make('approve_selected')
    ->label('Approve Selected')
    ->icon('heroicon-o-check')
    ->color('success')
    ->requiresConfirmation()
    ->action(function ($records): void {
        $service = app(OfferManagementService::class);
        $reviewer = static::getReviewerName();

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

## AffiliateMarketplacePage Actions

### Page Actions

| Action | Method | Description |
|--------|--------|-------------|
| Apply for Offer | `applyForOffer($offerId, $reason)` | Submit application |
| Generate Link | `generateLink($offerId)` | Create tracking link |

### Apply Action Usage

```php
// In Blade view
<x-filament::button wire:click="applyForOffer('{{ $offer->id }}', 'I want to promote this')">
    Apply Now
</x-filament::button>
```

### Generate Link Action Usage

```php
// In Blade view (only for approved affiliates)
@if ($this->getApplicationStatus($offer) === 'approved')
    <x-filament::button wire:click="generateLink('{{ $offer->id }}')" color="success">
        Get Link
    </x-filament::button>
@endif
```

---

## Adding Custom Actions

### Add Action to Site Resource

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateSiteResource as BaseResource;
use Filament\Tables;
use Filament\Tables\Table;

class AffiliateSiteResource extends BaseResource
{
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->actions([
                Tables\Actions\EditAction::make(),
                
                // Add suspend action
                Tables\Actions\Action::make('suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->isVerified())
                    ->action(function ($record) {
                        $record->update(['status' => 'suspended']);
                    }),
                    
                // Add reinstate action
                Tables\Actions\Action::make('reinstate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'suspended')
                    ->action(function ($record) {
                        $record->update([
                            'status' => 'verified',
                            'verified_at' => now(),
                        ]);
                    }),
            ]);
    }
}
```

### Add Action to Offer Resource

```php
<?php

namespace App\Filament\Resources;

use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource as BaseResource;
use Filament\Tables;
use Filament\Tables\Table;

class AffiliateOfferResource extends BaseResource
{
    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->actions([
                Tables\Actions\EditAction::make(),
                
                // Add duplicate action
                Tables\Actions\Action::make('duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function ($record) {
                        $newOffer = $record->replicate();
                        $newOffer->name = $record->name . ' (Copy)';
                        $newOffer->slug = $record->slug . '-copy-' . time();
                        $newOffer->status = 'draft';
                        $newOffer->save();
                        
                        Notification::make()
                            ->title('Offer duplicated')
                            ->success()
                            ->send();
                    }),
                    
                // Add export stats action
                Tables\Actions\Action::make('export_stats')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($record) {
                        // Generate CSV export
                        return response()->streamDownload(function () use ($record) {
                            echo "Link Code,Clicks,Conversions,Revenue\n";
                            foreach ($record->links as $link) {
                                echo "{$link->code},{$link->clicks},{$link->conversions},{$link->revenue}\n";
                            }
                        }, "{$record->slug}-stats.csv");
                    }),
            ]);
    }
}
```

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
