---
title: Actions Reference
---

# Actions Reference

Every action delegates to an `aiarmada/affiliate-network` feature action and runs
inside the correct owner context; this package never writes domain rows directly.

## AffiliateSiteResource

### Table actions

| Action | Icon | Visibility | Effect |
|---|---|---|---|
| Edit | — | always | Edit form |
| Sync catalog | `heroicon-o-arrow-path` | always | `Actions\SyncSiteCatalog` (confirmation) |
| Verify | `heroicon-o-check-badge` | `isPending()` | Verify inside the site owner context |
| Reject | `heroicon-o-x-mark` | `isPending()` | Reject |
| Suspend | `heroicon-o-pause` | `isVerified()` | Suspend (keeps `verified_at`) |
| Reinstate | `heroicon-o-play` | `isSuspended()` | Restore to verified |

```php
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySite;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

Action::make('verify')
    ->icon('heroicon-o-check-badge')
    ->color('success')
    ->requiresConfirmation()
    ->visible(fn ($record): bool => $record->isPending())
    ->action(function ($record): void {
        app(VerifySite::class)->execute($record);

        Notification::make()->title('Site verified')->success()->send();
    });
```

### Edit-page header actions

| Action | Icon | Effect |
|---|---|---|
| Sync catalog | `heroicon-o-arrow-path` | Pull the merchant catalog now (confirmation) |
| Rotate catalog token | `heroicon-o-key` | Issue a fresh token; previous dies immediately, shown once |
| Delete | — | Delete site + dependents |

```php
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RotateCatalogToken;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

Action::make('rotate_catalog_token')
    ->label('Rotate catalog token')
    ->icon('heroicon-o-key')
    ->color('warning')
    ->requiresConfirmation()
    ->modalDescription('The previous token stops working immediately. Copy the new token now — it is shown once.')
    ->action(function ($record): void {
        $token = app(RotateCatalogToken::class)->execute($record);

        Notification::make()->title('New catalog token')->body($token)->persistent()->send();
    });
```

## AffiliateOfferResource

### Table actions

| Action | Icon | Visibility | Effect |
|---|---|---|---|
| Edit | — | always | Edit form |
| Activate | `heroicon-o-play` | not published | Publish (site must be verified; `published_at` stamped) |
| Pause | `heroicon-o-pause` | published | Archive (`archived_at` stamped) |

Both transitions run inside the owning site's owner context and fire the domain
`OfferUpdated` event.

### Relation managers (edit page)

#### InvitationsRelationManager

Header **Invite affiliate** (`InviteAffiliateToOffer`); row **Revoke**
(`RevokeOfferInvitation`). Columns: affiliate ID, token (copyable), auto
approve, expiry, accepted, revoked.

#### DealsRelationManager

Header **Upsert deal** (`UpsertOfferDeal`); row **Revoke** (`RevokeOfferDeal`).
Columns: affiliate ID, base bp, fixed minor, fee bp, currency, active.

#### CouponsRelationManager

Header **Assign coupon** (`AssignOfferCoupon`); row **Revoke**
(`RevokeOfferCoupon`). Columns: code (copyable), affiliate ID, site, window,
active.

#### CreativesRelationManager

Manual rows support upload/update/delete; imported rows accept only
`is_active`/`sort_order`. Re-sync reconciles imported deletions.

#### LinksRelationManager

Columns: affiliate, backing slug, clicks, conversions, revenue. **Reconcile**
proves every counted conversion posted exactly once.

#### LegsRelationManager

Columns: affiliate, reference, event, coupon, sub id, revenue, commission, fee,
payout, status, locks at, occurred.

| Action | Visibility | Effect |
|---|---|---|
| **Lock matured** (header) | always | `LockMaturedLegs::execute()` |
| **Adjust** | `pending`/`posted` and no parent leg | `AdjustConversionLeg` (reference, delta, reason) |
| **Reverse** | `posted` | `ReverseConversionLeg` (reason) |

```php
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\AdjustConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\LockMaturedLegs;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReverseConversionLeg;
use Filament\Actions\Action;

Action::make('lock_matured')
    ->label('Lock matured')
    ->icon('heroicon-o-lock-closed')
    ->color('info')
    ->requiresConfirmation()
    ->action(fn () => app(LockMaturedLegs::class)->execute());

Action::make('adjust')
    ->label('Adjust')
    ->color('warning')
    ->visible(fn ($record): bool => in_array($record->status, [LegStatus::Pending, LegStatus::Posted], true)
        && $record->parent_leg_id === null)
    ->action(fn ($record, array $data) => app(AdjustConversionLeg::class)->execute(
        (string) $record->getKey(),
        (string) $data['adjustment_reference'],
        (int) $data['delta_revenue_minor'],
        (string) $data['reason'],
    ));

Action::make('reverse')
    ->label('Reverse')
    ->color('danger')
    ->visible(fn ($record): bool => $record->status === LegStatus::Posted)
    ->action(fn ($record, array $data) => app(ReverseConversionLeg::class)->execute(
        (string) $record->getKey(),
        (string) $data['reason'],
    ));
```

## AffiliateOfferCategoryResource

Table action **Edit**; bulk **Delete**. Sortable by `sort_order`; cycle-safe
re-parenting; deleting re-parents children.

## AffiliateOfferApplicationResource

| Action | Icon | Visibility | Effect |
|---|---|---|---|
| Approve | `heroicon-o-check` | `isPending()` | `ApproveApplication` |
| Reject | `heroicon-o-x-mark` | `isPending()` | `RejectApplication` with reason |
| Revoke | `heroicon-o-no-symbol` | `isApproved()` | `RevokeApplication` with reason |
| View | — | always | Infolist |
| Approve selected (bulk) | `heroicon-o-check` | pending rows | `ApproveApplication` per row |

Each mutation runs inside the owning affiliate's owner context, so cross-tenant
review works with owner scoping enabled.

```php
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RejectApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\RevokeApplication;
use Filament\Actions\Action;

Action::make('approve')
    ->visible(fn ($record): bool => $record->isPending())
    ->action(fn ($record) => app(ApproveApplication::class)->execute($record, auth()->user()?->name));

Action::make('reject')
    ->visible(fn ($record): bool => $record->isPending())
    ->action(fn ($record, array $data) => app(RejectApplication::class)->execute($record, (string) $data['reason'], auth()->user()?->name));

Action::make('revoke')
    ->visible(fn ($record): bool => $record->isApproved())
    ->action(fn ($record, array $data) => app(RevokeApplication::class)->execute($record, (string) $data['reason'], auth()->user()?->name));
```

## MerchantStatementResource (admin-only)

| Action | Visibility | Effect |
|---|---|---|
| **Generate** (list header) | always | `GenerateMerchantStatement` for a chosen site/currency/period, inside the site owner context |
| View | always | Infolist |
| **Issue** | `draft` | `IssueMerchantStatement` (`issued_at`/`due_at`) |
| **Mark Paid** | `draft`/`issued` | `MarkMerchantStatementPaid` with optional reference |
| **Void** | `draft`/`issued` | `VoidMerchantStatement` (detaches legs) |

```php
use AIArmada\AffiliateNetwork\Data\SettlementStatus;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\IssueMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\MarkMerchantStatementPaid;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\VoidMerchantStatement;
use Filament\Actions\Action;

Action::make('issue')
    ->visible(fn ($record): bool => $record->status === SettlementStatus::Draft)
    ->action(fn ($record) => app(IssueMerchantStatement::class)->execute((string) $record->getKey()));

Action::make('mark_paid')
    ->visible(fn ($record): bool => in_array($record->status, [SettlementStatus::Draft, SettlementStatus::Issued], true))
    ->action(fn ($record, array $data) => app(MarkMerchantStatementPaid::class)->execute(
        (string) $record->getKey(),
        $data['payment_reference'] ?? null,
    ));

Action::make('void')
    ->visible(fn ($record): bool => in_array($record->status, [SettlementStatus::Draft, SettlementStatus::Issued], true))
    ->action(fn ($record) => app(VoidMerchantStatement::class)->execute((string) $record->getKey()));
```

## Adding custom actions

Shipped resources are `final`; add actions on your own resource/table class
(instance `table(Table $table)` on a relation manager, static `table()` on a
resource).

```php
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Data\OfferStatus;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

public static function table(Table $table): Table
{
    return $table->actions([
        Action::make('duplicate')
            ->icon('heroicon-o-document-duplicate')
            ->action(function (AffiliateOffer $record): void {
                $copy = $record->replicate();
                $copy->name = $record->name . ' (Copy)';
                $copy->slug = $record->slug . '-copy-' . now()->timestamp;
                $copy->status = OfferStatus::Draft;
                $copy->save();

                Notification::make()->title('Offer duplicated')->success()->send();
            }),
    ]);
}
```

## Notifications

```php
use Filament\Notifications\Notification;

Notification::make()->title('Saved')->success()->send();
Notification::make()->title('Warning')->warning()->send();
Notification::make()->title('New token')->body($token)->persistent()->send();
```

## Authorization

Actions respect Filament policy checks:

```php
Action::make('approve')
    ->authorize('approve')
    ->action(fn ($record) => /* … */);
```

The network-wide resources additionally gate on `NetworkAdminAccess::allows()`.
