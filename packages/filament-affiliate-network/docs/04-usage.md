---
title: Usage
---

# Usage

This guide covers every shipped surface. All resources are owner-safe; the
network-wide admin surfaces are noted explicitly.

The plugin provides **five resources** (sites, offers, categories,
applications, merchant statements), **six offer relation managers**, a merchant
dashboard page, and two network reporting widgets. There is no marketplace page
in this package.

Every resource, page, table, and widget action gates on
`NetworkAdminAccess::allows()`, which reads
`filament-affiliate-network.authorization.admin_ability` (default
`affiliate-network.admin`).

Merchant dashboard counts and lists are owner-scoped. `MerchantStatementResource`
and the two widgets are deliberate network-wide admin reporting surfaces that
bypass owner scope.

The network owns discovery, enrollment, link metrics, and its own money legs:
every offer enrolls through network applications. `affiliates` owns merchant
local attribution, commissions, and payouts. Merchant-ledger postings arrive
through the fulfillment step, never inline or from this adapter.

---

## AffiliateSiteResource

Manage merchant sites, verification, catalog sync, tokens, and settlement.

### Table columns

Name, domain (copyable), status (badge), offers count, verified at, created at.

### Form sections

**Site Details** — name; domain; description. The domain field lowercases/trims
input and validates with `Platform\DomainHostValidator::isValidHostname()`: a
bare hostname, no scheme/path/whitespace, no IP or `localhost`/`.local`, max 253
chars. It also enforces uniqueness.

**Status** — status (`pending`, `verified`, `suspended`, `rejected`),
verification method (`dns`, `meta_tag`, `file`), read-only `verified_at`.
Suspending retains `verified_at`; changing a verified domain resets to pending.

**Catalog Sync** — catalog URL (empty for locally-managed offers); catalog API
token (stored encrypted; setting it stamps `catalog_token_issued_at`; leave
empty to keep).

**Settlement & Locking** — `default_locking_days`, `prepaid_balance_minor`,
`budget_currency`, `monthly_spend_cap_minor`,
`require_settled_for_payout` (pay-when-paid gate).

**Settings** — `settings`, `metadata` key/value.

### Actions

| Action | Visibility | Effect |
|---|---|---|
| Edit | always | Edit details |
| Sync catalog | always | `Actions\SyncSiteCatalog` pulls offers now (confirmation) |
| Verify | `isPending()` | Runs inside the site owner context |
| Reject | `isPending()` | Reject |
| Suspend | `isVerified()` | Suspend (keeps `verified_at`) |
| Reinstate | `isSuspended()` | Restore to verified |
| Delete | always | Delete site + dependents |

The edit page adds header actions **Sync catalog** and **Rotate catalog token**
(issues a fresh token; the previous dies immediately and the plaintext is shown
once in a persistent notification).

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

        Notification::make()
            ->title('New catalog token')
            ->body($token)
            ->persistent()
            ->send();
    });
```

### Status colors

`pending` warning · `verified` success · `suspended` danger · `rejected` danger.

---

## AffiliateOfferResource

Manage offers, pricing, caps, fallback, promotions, and the six relation tabs.

### Table columns

Name, site, category, status, commission (formatted), fee bp, source,
featured, visibility, applications count, created at.

### Form sections

**Offer Details** — site (verified sites only), category, name (auto-slug),
slug (unique per site), description, terms.

**Commission** — base rate bp, fixed amount minor, currency, cookie days,
source (`mirrored`/`manual`), network fee bp, volume tiers repeater.

**Caps & Locking** — `locking_days`, `daily_conversion_cap`,
`monthly_conversion_cap`, `total_conversion_cap`,
`per_affiliate_conversion_cap`, `monthly_payout_cap_minor`,
`total_payout_cap_minor`.

**Settings** — status (`draft`/`published`/`archived`), visibility
(`public`/`private`/`unlisted`), featured, requires approval, allow deep
linking, landing URL, **fallback URL**, **fallback offer ID**, start/end dates.

**Advanced** — `event_rates` (multi-event CPA), `active_promotions`,
`restrictions` (country/channel/device/order), `metadata` key/value
(collapsed).

### Actions

| Action | Visibility | Effect |
|---|---|---|
| Edit | always | Edit offer |
| Activate | not published | Publish via core action (`published_at` stamped, site must be verified) |
| Pause | published | Archive via core action (`archived_at` stamped) |
| Delete | always | Delete offer + dependents |

Activate/pause run inside the owning site's owner context and dispatch the
domain `OfferUpdated` event.

### Filters

Status, visibility, site, featured (ternary).

### Relation managers

**Creatives** — imported and manual assets. Imported rows are read-only except
`is_active`/`sort_order`; manual rows support the Spatie Media Library field,
destination URL, and HTML/template. Only manual rows can be deleted. Re-sync
reconciles imported deletions.

**Links** — issued tracking links with clicks/conversions/revenue, plus a
reconcile action that proves every counted conversion posted to the merchant
ledger exactly once.

**Legs** — posted money legs (reference, event, coupon, sub id, revenue,
commission, fee, payout, status, locks at, occurred). Header action **Lock
matured**; row actions **Adjust** (pending/posted originals) and **Reverse**
(posted only).

**Invitations** — token, affiliate, auto-approve, expiry/accepted/revoked.
Header action **Invite affiliate**; row action **Revoke**.

**Deals** — per-affiliate rate base/fixed/fee/currency/active. Header action
**Upsert deal**; row action **Revoke**.

**Coupons** — code, affiliate, site, window, active. Header action **Assign
coupon**; row action **Revoke**.

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\AdjustConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\LockMaturedLegs;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReverseConversionLeg;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use Filament\Actions\Action;

// Lock matured legs (header)
Action::make('lock_matured')
    ->label('Lock matured')
    ->action(fn () => app(LockMaturedLegs::class)->execute());

// Adjust a pending/posted original
Action::make('adjust')
    ->visible(fn ($record): bool => in_array($record->status, [LegStatus::Pending, LegStatus::Posted], true)
        && $record->parent_leg_id === null)
    ->action(fn ($record, array $data) => app(AdjustConversionLeg::class)->execute(
        (string) $record->getKey(),
        (string) $data['adjustment_reference'],
        (int) $data['delta_revenue_minor'],
        (string) $data['reason'],
    ));

// Reverse a posted leg
Action::make('reverse')
    ->visible(fn ($record): bool => $record->status === LegStatus::Posted)
    ->action(fn ($record, array $data) => app(ReverseConversionLeg::class)->execute(
        (string) $record->getKey(),
        (string) $data['reason'],
    ));
```

```php
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\InviteAffiliateToOffer;

app(InviteAffiliateToOffer::class)->execute(
    (string) $offerId,
    $affiliateId,          // null for a shareable token
    null,
    null,
    autoApprove: true,
);

use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\UpsertOfferDeal;

app(UpsertOfferDeal::class)->execute((string) $offerId, $affiliateId, [
    'rate_base_bp' => 2000,
    'network_fee_bp' => 500,
]);

use AIArmada\AffiliateNetwork\Features\Attribution\Actions\AssignOfferCoupon;

app(AssignOfferCoupon::class)->execute((string) $offerId, $affiliateId, 'SUMMER10');
```

> **warning**
> Editing any rate-block field on the offer form flips `source` to `manual`.
> Later catalog syncs then hold those rates back (`locked`) instead of
> reverting them.

---

## AffiliateOfferCategoryResource

Hierarchical category management.

- Columns: name, slug, parent, offers count, active, sort order, created at.
- Fields: parent (excludes self/descendants), name, slug, description, icon,
  sort order, active.
- Cycle protection prevents assigning a category to itself or a descendant.
- Sortable by `sort_order`; deleting re-parents children safely.

---

## AffiliateOfferApplicationResource

Review creator applications.

- Columns: affiliate ID, offer, status, reviewed by, reviewed at, submitted at.
- Infolist: offer/affiliate/status/reason and review data.
- Actions: View, Approve, Reject (reason), Revoke (reason).
- Bulk: Approve selected (pending only).
- Filters: status, offer.

Approve/reject/revoke/bulk run each mutation inside the owning affiliate's owner
context so cross-tenant review works with owner scoping enabled.

---

## MerchantStatementResource (admin-only)

Aggregate, issue, pay, and void merchant statements network-wide.

`canViewAny()` and `shouldRegisterNavigation()` require
`NetworkAdminAccess::allows()`. `getEloquentQuery()` calls
`withoutOwnerScope()` so operators see every merchant's statements.

### Table columns

Statement number (copyable), site, status, currency, legs, commission, fee,
payout, period start/end, issued/due/paid.

### Actions

| Action | Visibility | Effect |
|---|---|---|
| **Generate** (header) | always | `GenerateMerchantStatement` for a chosen site/currency/period, inside the site's owner context |
| View | always | Infolist |
| **Issue** | `draft` | `IssueMerchantStatement` (`issued_at`/`due_at`) |
| **Mark Paid** | `draft` or `issued` | `MarkMerchantStatementPaid` with optional payment reference (fulfils attached legs when the site requires settlement) |
| **Void** | `draft` or `issued` | `VoidMerchantStatement` (detaches legs) |

### Infolist

Sections: Statement (number, site, status, currency, period), Amounts (legs,
commission, fee, payout), Settlement (issued/due/paid timestamps, payment
reference).

```php
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\GenerateMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\IssueMerchantStatement;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\MarkMerchantStatementPaid;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\VoidMerchantStatement;
use AIArmada\CommerceSupport\Support\OwnerContext;

$statement = OwnerContext::withOwner($owner, fn () => app(GenerateMerchantStatement::class)->execute(
    $siteId,
    'USD',
    CarbonImmutable::parse($periodStart)->startOfDay(),
    CarbonImmutable::parse($periodEnd)->endOfDay(),
));

app(IssueMerchantStatement::class)->execute((string) $statement->getKey());
app(MarkMerchantStatementPaid::class)->execute((string) $statement->getKey(), 'BANK-REF');
app(VoidMerchantStatement::class)->execute((string) $statement->getKey());
```

---

## Extending resources

Every shipped resource, page, and widget is `final`. Reuse the schema/table
classes from your own resource:

```php
namespace App\Filament\Resources;

use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\CouponsRelationManager;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Schemas\AffiliateOfferForm;
use AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\Tables\AffiliateOffersTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AffiliateOfferResource extends Resource
{
    protected static ?string $model = AffiliateOffer::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return config('filament-affiliate-network.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('filament-affiliate-network.navigation.sort', 50) + 1;
    }

    public static function form(Schema $schema): Schema
    {
        return AffiliateOfferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AffiliateOffersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\CreativesRelationManager::class,
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\LinksRelationManager::class,
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\LegsRelationManager::class,
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\InvitationsRelationManager::class,
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\DealsRelationManager::class,
            \AIArmada\FilamentAffiliateNetwork\Resources\AffiliateOfferResource\RelationManagers\CouponsRelationManager::class,
        ];
    }
}
```

Register it on the panel (the shipped plugin registers a fixed set):

```php
$panel->resources([
    \App\Filament\Resources\AffiliateOfferResource::class,
]);
```

> **warning**
> Do not spread money in strings or divide by 100. Use
> `AIArmada\CommerceSupport\Support\MoneyFormatter::formatMinor($minor, $currency)`;
> the minor-unit scale is currency-dependent (JPY and KWD are not 2-decimal).
