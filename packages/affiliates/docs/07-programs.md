---
title: Programs & Tiers
---

# Affiliate Programs & Tiers

Programs allow you to create structured affiliate offerings with different commission rates, eligibility rules, and promotional materials.

## Creating Programs

```php
use AIArmada\Affiliates\Features\Programs\Api\ProgramAdministration;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;

$programId = app(ProgramAdministration::class)->create([
    'name' => 'Premium Partners',
    'slug' => 'premium-partners',
    'description' => 'Our exclusive partner program with higher commissions',
    'status' => ProgramStatus::Active,
    'requires_approval' => true,
    'visibility' => ProgramVisibility::Public,
    'default_commission_rate_basis_points' => 1500, // 15%
    'commission_type' => CommissionType::Percentage,
    'cookie_lifetime_days' => 60,
    'starts_at' => now(),
    'ends_at' => now()->addYear(),
    'eligibility_rules' => [
        'min_monthly_traffic' => 10000,
        'allowed_countries' => ['US', 'CA', 'GB'],
        'required_social_followers' => 5000,
    ],
]);
```

> **warning:**
> There is no `is_public` column. Use
> `visibility` (`ProgramVisibility::Public` / `Private`).

## Program Statuses

```php
use AIArmada\Affiliates\Enums\ProgramStatus;

ProgramStatus::Draft;     // Not yet published
ProgramStatus::Active;    // Accepting enrollments
ProgramStatus::Paused;    // Temporarily closed
ProgramStatus::Archived;  // Permanently closed
```

## Creating Program Tiers

Tiers allow progressive commission rates based on performance:

```php
use AIArmada\Affiliates\Features\Programs\Api\ProgramTierAdministration;

// Bronze tier (default)
app(ProgramTierAdministration::class)->create($programId, [
    'name' => 'Bronze',
    'level' => 1,
    'commission_rate_basis_points' => 1000, // 10%
    'min_revenue' => 0,
    'min_conversions' => 0,
]);

// Silver tier
app(ProgramTierAdministration::class)->create($programId, [
    'name' => 'Silver',
    'level' => 2,
    'commission_rate_basis_points' => 1250, // 12.5%
    'min_revenue' => 100000, // $1,000
    'min_conversions' => 10,
]);

// Gold tier
$goldTierId = app(ProgramTierAdministration::class)->create($programId, [
    'name' => 'Gold',
    'level' => 3,
    'commission_rate_basis_points' => 1500, // 15%
    'min_revenue' => 500000, // $5,000
    'min_conversions' => 50,
]);
```

`min_revenue` compares against the affiliate's program revenue measured in the affiliate's currency: legs convert when exchange rates exist, otherwise only the affiliate-currency leg counts. The same rule applies to `eligibility_rules.min_revenue` on the program itself.

## Enrolling Affiliates

```php
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Features\Programs\Api\ProgramMembershipReader;
use AIArmada\Affiliates\Features\Programs\Api\ProgramTierManagement;
use AIArmada\Affiliates\Features\Programs\Api\MerchantCatalog;

// Join (enroll). Throws ProgramEnrollmentDenied when ineligible or closed.
$membership = app(ProgramEnrollment::class)->join($affiliate->id, $programId);

if ($membership !== null) {
    app(\AIArmada\Affiliates\Features\Programs\Api\MembershipReviewApi::class)->approve($membership->id, 'admin-1');
}

// Program listings
app(MerchantCatalog::class)->availablePrograms();              // list of program summaries
app(ProgramMembershipReader::class)->programIds($affiliate->id);    // list of program IDs
app(ProgramMembershipReader::class)->find($affiliate->id, $programId)?->active ?? false;      // bool
app(ProgramMembershipReader::class)->find($affiliate->id, $programId); // ?MembershipRecord
```

## Membership Statuses

```php
use AIArmada\Affiliates\Enums\MembershipStatus;

MembershipStatus::Pending;    // Awaiting approval
MembershipStatus::Approved;   // Enrolled and earning
MembershipStatus::Rejected;   // Rejected from the program
MembershipStatus::Suspended;  // Temporarily disabled
```

## Program Creatives

Provide affiliates with promotional materials:

```php
use AIArmada\Affiliates\Features\Programs\Api\CreativeAdministration;

$creatives = app(CreativeAdministration::class);
$creativeId = $creatives->create($programId, [
    'name' => 'Summer Sale Banner',
    'type' => 'banner',
    'tracking_code' => 'SUMMER-SALE',
    'width' => 728,
    'height' => 90,
    'destination_url' => 'https://example.com/summer-sale',
    'metadata' => [
        'alt_text' => 'Summer Sale - 20% Off',
    ],
]);
```

Creative files live exclusively in Spatie Media Library's single-file `creative_asset` collection on the `public` disk. Supply validated upload bytes to the owning API; never attach or remove media directly:

```php
$bytes = file_get_contents(storage_path('app/banners/summer-sale.jpg'));

if ($bytes === false) {
    throw new RuntimeException('Creative asset could not be read.');
}

$creatives->update($programId, $creativeId, [], assetBytes: $bytes);

// Remove only the creative asset:
$creatives->update($programId, $creativeId, [], removeAsset: true);
```

The API coordinates media changes with the database transaction, retains the previous asset on rollback, and retries detached-file cleanup after failure. Banner embeds require an attached file; text links can use only `destination_url`.

> **info:**
> On PostgreSQL, creative writes take a shared lock on the parent program. Enrollment and tracking-link writes can overlap with an in-flight creative upload, while program updates or deletions and same-creative replacements remain exclusive. SQLite still serializes these writes behind its database-wide writer lock.

## Using the Program feature APIs

```php
use AIArmada\Affiliates\Features\Programs\Api\ProgramEnrollment;
use AIArmada\Affiliates\Features\Programs\Api\ProgramMembershipReader;
use AIArmada\Affiliates\Features\Programs\Api\ProgramTierManagement;
use AIArmada\Affiliates\Features\Programs\Api\MerchantCatalog;

// Get available programs (no affiliate argument)
$programs = app(MerchantCatalog::class)->availablePrograms();

// Enroll affiliate
$membership = app(ProgramEnrollment::class)->join($affiliate->id, $programId);

// Suspend administratively
app(\AIArmada\Affiliates\Features\Programs\Api\MembershipAdministration::class)->suspend($membership->id);

// Leave
app(ProgramEnrollment::class)->leave($affiliate->id, $programId);

// Upgrade tier — takes affiliate + program + tier, not the membership
app(ProgramTierManagement::class)->upgrade($affiliate->id, $programId, $goldTierId);

// Get affiliate's memberships
$reader = app(ProgramMembershipReader::class);
$programIds = $reader->programIds($affiliate->id);
$membership = $reader->find($affiliate->id, $programId);
```

## Commission Templates

Pre-defined commission structures for quick affiliate setup:

```php
use AIArmada\Affiliates\Features\Commissions\Api\CommissionTemplateAdministration;

$templates = app(CommissionTemplateAdministration::class);

// Create standard percentage template
$templateId = $templates->createStandardPercentage(
    name: 'Standard 10%',
    rateBasisPoints: 1000,
    isDefault: true,
);

// Create tiered volume template
$templateId = $templates->createTieredVolume(
    name: 'Volume Tiers',
    baseRateBasisPoints: 500,
    volumeTiers: [
        ['min_volume' => 0, 'max_volume' => 100000, 'bonus_rate' => 0],
        ['min_volume' => 100001, 'max_volume' => 500000, 'bonus_rate' => 100],
        ['min_volume' => 500001, 'max_volume' => null, 'bonus_rate' => 200],
    ],
);

// Create MLM template
$templateId = $templates->createMlm(
    name: '3-Level MLM',
    baseRateBasisPoints: 1000,
    overridePercentages: [50, 25, 10], // 50%, 25%, 10% of commission to uplines
);

// Apply template to affiliate
$templates->applyToAffiliate($templateId, $affiliate->id);

// Apply template to program
$templates->applyToProgram($templateId, $programId);
```

## Volume Tiers

Independent of a specific offer, programs can have volume-based commission
bonuses. `AffiliateVolumeTier` is program-scoped (`program_id`), not
affiliate-scoped.

```php
use AIArmada\Affiliates\Features\Commissions\Api\VolumeTierAdministration;

$volumeTiers = app(VolumeTierAdministration::class);

$volumeTiers->create([
    'program_id' => $programId,
    'name' => 'Base',
    'period' => 'monthly',
    'currency' => 'MYR',
    'min_volume_minor' => 0,
    'max_volume_minor' => 100000,
    'commission_rate_basis_points' => 0, // No bonus
]);

$volumeTiers->create([
    'program_id' => $programId,
    'name' => 'Scale',
    'period' => 'monthly',
    'currency' => 'MYR',
    'min_volume_minor' => 100001,
    'max_volume_minor' => null, // Unlimited
    'commission_rate_basis_points' => 100, // +1% bonus
]);
```

## Commission Rules

Custom rules for specific conditions:

```php
use AIArmada\Affiliates\Features\Commissions\Api\ProgramCommissionAdministration;
use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;

app(ProgramCommissionAdministration::class)->createRule($programId, [
    'name' => 'Electronics boost',
    'rule_type' => CommissionRuleType::Category,
    'priority' => 10,
    'commission_type' => CommissionType::Percentage,
    'commission_value' => 2000, // 20% in basis points
    'conditions' => [
        'product_categories' => ['electronics', 'software'],
    ],
    'is_active' => true,
]);
```

> **warning:**
> `affiliate_id` and `rate_basis_points` are not fillable. Rules hang off
> `program_id` and the rate lives in `commission_value`.

## Commission Promotions

Time-limited commission boosts:

```php
use AIArmada\Affiliates\Features\Commissions\Api\ProgramCommissionAdministration;
use AIArmada\Affiliates\Enums\CommissionType;

app(ProgramCommissionAdministration::class)->createPromotion($programId, [
    'name' => 'Holiday Bonus',
    'bonus_type' => CommissionType::Percentage,
    'bonus_value' => 500, // +5% in basis points
    'starts_at' => now(),
    'ends_at' => now()->addMonth(),
    'max_uses' => 100,
    'affiliate_ids' => [], // Empty = applies to all program affiliates
]);
```

> **warning:**
> `affiliate_id`, `bonus_rate_basis_points`, and `is_active` are not fillable.
> Promotions are program-scoped; opt individual affiliates in through the
> `affiliate_ids` array, and there is no `is_active` column — window it with
> `starts_at` / `ends_at` and `max_uses` / `current_uses`.

### Network mirroring

Program creatives are included in the [version 2 catalog](14-catalog.md).
Their asset URLs are absolute so remote networks can display public files.
General creatives without a program are excluded. The network builds its own
tracking links; the merchant's personalized embed HTML is never transferred.
