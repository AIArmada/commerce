---
title: Models Reference
---

# Models Reference

The affiliates package includes 30 Eloquent models. This reference covers the primary models and their relationships.

## Core Models

### Affiliate

The main affiliate/partner model.

```php
use AIArmada\Affiliates\Models\Affiliate;
```

**Key Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | uuid | Primary key |
| `code` | string | Unique affiliate code |
| `name` | string | Affiliate name |
| `status` | `AIArmada\Affiliates\States\AffiliateStatus` | Spatie model state: `draft`, `pending`, `active`, `paused`, `disabled` |
| `registration_approval_mode` | string | Snapshotted approval mode (auto/open/admin), immutable |
| `commission_type` | CommissionType | Percentage or fixed |
| `commission_rate` | int | Rate in basis points or minor units |
| `currency` | string | ISO currency code |
| `parent_affiliate_id` | uuid | Parent for MLM upline |
| `default_voucher_code` | string | Default voucher for this affiliate |
| `contact_email` | string | Contact email |
| `owner_type` | string | Polymorphic owner type |
| `owner_id` | uuid | Polymorphic owner ID |
| `activated_at` | timestamp | When affiliate was activated |

**Relationships:**

```php
$affiliate->conversions;      // HasMany<AffiliateConversion>
$affiliate->attributions;     // HasMany<AffiliateAttribution>
$affiliate->payouts;          // MorphMany<AffiliatePayout>
$affiliate->links;            // HasMany<AffiliateLink>
$affiliate->balances;         // HasMany<AffiliateBalance>, one per currency
$affiliate->parent;           // BelongsTo<Affiliate>
$affiliate->children;         // HasMany<Affiliate>
$affiliate->programs;         // BelongsToMany<AffiliateProgram>
$affiliate->fraudSignals;     // HasMany<AffiliateFraudSignal>
$affiliate->dailyStats;       // HasMany<AffiliateDailyStat>
$affiliate->rank;             // BelongsTo<AffiliateRank>
$affiliate->payoutMethods;    // HasMany<AffiliatePayoutMethod>
$affiliate->payoutHolds;      // HasMany<AffiliatePayoutHold>
$affiliate->vouchers;         // HasMany<Voucher>
$affiliate->balances;         // HasMany<AffiliateBalance>, one row per currency
```

`commissionRules` and `volumeTiers` are **not** relationships on `Affiliate` —
those rows are program-scoped and reachable via `$program->commissionRules()` and
`$program->volumeTiers()`.

**Key Methods:**

```php
$affiliate->isActive();                // Check if status is Active
$affiliate->canBeAttributed();         // Active, or pending under open registration
$affiliate->hasActivePayoutHold();     // Check for unreleased payout holds
$affiliate->canRequestPayout();        // True when any balance meets its minimum payout
$affiliate->canRequestPayout('USD');   // True when the USD balance meets its minimum
$affiliate->balanceFor('USD');         // The USD AffiliateBalance, or null
```

### AffiliateAttribution

Tracks when a visitor is attributed to an affiliate.

```php
use AIArmada\Affiliates\Models\AffiliateAttribution;
```

**Key Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `affiliate_id` | uuid | The credited affiliate |
| `affiliate_code` | string | Code used at time of attribution |
| `subject_type` | string | Neutral subject type (`product`, `order`, etc.) |
| `subject_key` | string | Canonical subject key |
| `subject_instance` | string | Neutral subject instance/context |
| `subject_title_snapshot` | string | Snapshot title for subject at attribution time |
| `cart_identifier` | string | Cart session identifier |
| `cart_instance` | string | Cart instance name |
| `cookie_value` | string | Tracking cookie value |
| `voucher_code` | string | Voucher code if used |
| `landing_url` | string | First page visited |
| `referrer_url` | string | Referring URL |
| `source` | string | UTM source |
| `medium` | string | UTM medium |
| `campaign` | string | UTM campaign |
| `user_agent` | string | Browser user agent |
| `ip_address` | string | Visitor IP |
| `expires_at` | timestamp | Attribution expiry |

**Relationships:**

```php
$attribution->affiliate;    // BelongsTo<Affiliate>
$attribution->conversions;  // HasMany<AffiliateConversion>
$attribution->touchpoints;  // HasMany<AffiliateTouchpoint>
```

Attribution identity fields describe the originating commerce subject:

- `subject_key` and `cart_identifier` are stored independently with explicit meaning.
- `subject_instance` <-> `cart_instance`

### AffiliateConversion

Records a successful conversion (sale, signup, etc.).

```php
use AIArmada\Affiliates\Models\AffiliateConversion;
```

**Key Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `affiliate_id` | uuid | The credited affiliate |
| `affiliate_attribution_id` | uuid | Source attribution |
| `affiliate_payout_id` | uuid | Payout batch (if paid) |
| `subject_type` | string | Neutral subject type |
| `subject_key` | string | Canonical subject key |
| `subject_instance` | string | Neutral subject instance/context |
| `subject_title_snapshot` | string | Snapshot title for subject |
| `external_reference` | string | Neutral external reference |
| `conversion_type` | string | Conversion category (`purchase`, etc.) |
| `subtotal_minor` | int | Order subtotal in minor units |
| `value_minor` | int | Neutral conversion value in minor units |
| `commission_minor` | int | Commission amount in minor units |
| `commission_currency` | string | Required. Denominates both `value_minor` and `commission_minor`; no database default — writers must set it explicitly |
| `held_minor` | int | Amount this conversion recorded in the balance holding pool (0 when off-record); approval releases from it and credits any uncovered remainder, then zeroes it |
| `status` | ConversionStatus | pending, qualified, approved, rejected, reversed, paid |
| `occurred_at` | timestamp | When conversion occurred |
| `approved_at` | timestamp | When approved or matured into the payout-eligible state |

**Relationships:**

```php
$conversion->affiliate;    // BelongsTo<Affiliate>
$conversion->attribution;  // BelongsTo<AffiliateAttribution>
$conversion->payout;       // BelongsTo<AffiliatePayout>
```

`external_reference`, `value_minor`, `subject_key`, `subject_instance`, and
`commission_currency` are the canonical conversion fields. Cart identity remains on the
attribution record and is not copied into conversion metadata.

Balance side effects are handled by the model hooks:

- pending or qualified conversions add commission to `holding_minor`
- approved conversions release commission into `available_minor`
- paid conversions deduct the commission from `available_minor`

A conversion linked to an open (non-terminal) payout is reserved:
`$conversion->assertNotReservedByOpenPayout()` throws until the payout
reaches a terminal state, so reverse/void/reject paths cannot move
money out from under a payout in flight. Cancel or fail the payout
first to release its conversions.

Pre-existing rows keep `held_minor = 0`: the migration adds the
column without a data update, and the approval arithmetic credits
the full commission from the applied mutator amounts instead.
Residuals derive from applied amounts, so every approval credits
exactly `commission_minor` to available regardless of pool state.
Only the display-only lifetime total can drift on divergent legacy
rows, and any pool shortfall is reported via
`HoldingShortfallDetected` (see [Events](12-events.md)).

### AffiliatePayout

Batch payout record for commissions.

```php
use AIArmada\Affiliates\Models\AffiliatePayout;
```

**Key Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `reference` | string | Unique payout reference |
| `status` | PayoutStatus | Pending, Processing, Completed, Failed, Cancelled |
| `total_minor` | int | Total payout amount |
| `currency` | string | Payout currency |
| `payee_type` | string | Polymorphic payee type |
| `payee_id` | uuid | Polymorphic payee ID |
| `scheduled_at` | timestamp | When payout is scheduled |
| `paid_at` | timestamp | When payment was made |
| `metadata` | array | Additional data (bank details, notes) |

**Relationships:**

```php
$payout->conversions;  // HasMany<AffiliateConversion>
$payout->events;       // HasMany<AffiliatePayoutEvent>
$payout->payee;        // MorphTo (Affiliate or custom)
```

## Program Models

### AffiliateProgram

Affiliate program definition with commission rules.

```php
use AIArmada\Affiliates\Models\AffiliateProgram;
```

**Key Attributes:**

| Attribute | Type | Description |
|-----------|------|-------------|
| `name` | string | Program name |
| `slug` | string | URL-friendly slug |
| `status` | ProgramStatus | draft, active, paused, archived |
| `requires_approval` | bool | Manual approval required |
| `visibility` | ProgramVisibility | public, private |
| `default_commission_rate_basis_points` | int | Default commission |
| `commission_type` | string | Percentage or fixed |
| `cookie_lifetime_days` | int | Attribution window |
| `eligibility_rules` | array | Program requirements |
| `starts_at` | timestamp | Program start date |
| `ends_at` | timestamp | Program end date |

**Relationships:**

```php
$program->tiers;        // HasMany<AffiliateProgramTier>
$program->memberships;  // HasMany<AffiliateProgramMembership>
$program->creatives;    // HasMany<AffiliateProgramCreative>
$program->affiliates;   // BelongsToMany<Affiliate>
```

### AffiliateProgramMembership

Affiliate enrollment in a program.

```php
$membership->affiliate;  // BelongsTo<Affiliate>
$membership->program;    // BelongsTo<AffiliateProgram>
$membership->tier;       // BelongsTo<AffiliateProgramTier>
```

## Balance & Financial Models

### AffiliateBalance

Real-time balance tracking.

```php
use AIArmada\Affiliates\Models\AffiliateBalance;
```

| Attribute | Type | Description |
|-----------|------|-------------|
| `affiliate_id` | uuid | The affiliate |
| `available_minor` | int | Available for withdrawal |
| `holding_minor` | int | On hold (maturity, fraud review) |
| `lifetime_earnings_minor` | int | Total earned all-time |
| `minimum_payout_minor` | int | Minimum available balance required before payout |
| `currency` | string | Balance currency |

**Key Methods:**

```php
$balance->getTotalBalanceMinor();   // holding + available
$balance->canRequestPayout();       // True when available >= minimum payout
$balance->formatHolding();          // Decimal string for display
$balance->formatAvailable();        // Decimal string for display
$balance->formatLifetimeEarnings(); // Decimal string for display
```

### AffiliatePayoutMethod

Stored payout methods.

```php
use AIArmada\Affiliates\Models\AffiliatePayoutMethod;
```

| Attribute | Type | Description |
|-----------|------|-------------|
| `type` | PayoutMethodType | bank_transfer, paypal, stripe_connect, wise, payoneer, check, wire, crypto |
| `is_default` | bool | Primary method |
| `verified_at` | timestamp | When the method was verified by system |
| `details` | array | Encrypted payout details |

### AffiliatePayoutHold

Temporary holds on payouts.

```php
use AIArmada\Affiliates\Models\AffiliatePayoutHold;
```

| Attribute | Type | Description |
|-----------|------|-------------|
| `reason` | string | Hold reason |
| `notes` | string | Operator notes |
| `expires_at` | timestamp | Hold expiry |
| `placed_by` | string | Who placed the hold |
| `released_at` | timestamp | When released |

## Fraud & Analytics Models

### AffiliateFraudSignal

Detected fraud signals.

```php
use AIArmada\Affiliates\Models\AffiliateFraudSignal;
```

| Attribute | Type | Description |
|-----------|------|-------------|
| `affiliate_id` | uuid | The affiliate |
| `conversion_id` | uuid | Related conversion |
| `touchpoint_id` | uuid | Related touchpoint |
| `rule_code` | string | Fraud rule that fired (`CLICK_VELOCITY`, `GEO_ANOMALY`, `FINGERPRINT_REPEAT`, `SELF_REFERRAL`, `CONVERSION_VELOCITY`, `FAST_CONVERSION`) |
| `description` | string | Human-readable signal description |
| `severity` | FraudSeverity | low, medium, high, critical |
| `status` | FraudSignalStatus | detected, reviewed, dismissed, confirmed |
| `risk_points` | int | Fraud risk points contributed (FraudSeverity::fromScore() bands at 50/80/100) |
| `evidence` | array | Signal evidence |

### AffiliateDailyStat

Aggregated daily statistics.

```php
use AIArmada\Affiliates\Models\AffiliateDailyStat;
```

| Attribute | Type | Description |
|-----------|------|-------------|
| `affiliate_id` | uuid | The affiliate |
| `date` | date | Statistics date |
| `currency` | string | Row currency; unique with affiliate + date |
| `clicks` | int | Click count (affiliate-currency row only) |
| `unique_clicks` | int | Unique clicks (affiliate-currency row only) |
| `attributions` | int | Attributions (affiliate-currency row only) |
| `conversions` | int | Conversion count in this currency |
| `revenue_cents` | int | Revenue in this currency, minor units |
| `commission_cents` | int | Commission in this currency, minor units |
| `conversion_rate` | float | Day conversion rate (affiliate-currency row only) |
| `epc_cents` | float | This currency's commission per click |

## Upline Models

### AffiliateUpline

Parent-child closure table for MLM structures.

### AffiliateRank

Rank definitions (Bronze, Silver, Gold, etc.).

### AffiliateRankHistory

Rank change history for affiliates.

## Support Models

### AffiliateSupportTicket / AffiliateSupportMessage

Support ticket system for affiliate queries.

### AffiliateTaxDocument

Tax document storage (W-9, 1099).
