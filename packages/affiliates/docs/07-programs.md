---
title: Programs & Tiers
---

# Affiliate Programs & Tiers

Programs allow you to create structured affiliate offerings with different commission rates, eligibility rules, and promotional materials.

## Creating Programs

```php
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;

$program = AffiliateProgram::create([
    'name' => 'Premium Partners',
    'slug' => 'premium-partners',
    'description' => 'Our exclusive partner program with higher commissions',
    'status' => ProgramStatus::Active,
    'requires_approval' => true,
    'visibility' => ProgramVisibility::Public,
    'default_commission_rate_basis_points' => 1500, // 15%
    'commission_type' => 'percentage',
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

## Program Statuses

```php
use AIArmada\Affiliates\Enums\ProgramStatus;

ProgramStatus::Draft;    // Not yet published
ProgramStatus::Active;   // Accepting enrollments
ProgramStatus::Paused;   // Temporarily closed
ProgramStatus::Archived; // Retired
```

## Creating Program Tiers

Tiers allow progressive commission rates based on performance:

```php
use AIArmada\Affiliates\Models\AffiliateProgramTier;

// Bronze tier (default)
AffiliateProgramTier::create([
    'program_id' => $program->id,
    'name' => 'Bronze',
    'level' => 1,
    'commission_rate_basis_points' => 1000, // 10%
    'min_revenue' => 0,
    'min_conversions' => 0,
]);

// Silver tier
AffiliateProgramTier::create([
    'program_id' => $program->id,
    'name' => 'Silver',
    'level' => 2,
    'commission_rate_basis_points' => 1250, // 12.5%
    'min_revenue' => 100000, // $1,000
    'min_conversions' => 10,
]);

// Gold tier
AffiliateProgramTier::create([
    'program_id' => $program->id,
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
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\Affiliates\Enums\MembershipStatus;

$programService = app(ProgramService::class);

// Check eligibility first
if ($program->canJoin($affiliate)) {
    $membership = $programService->joinProgram($affiliate, $program);
}

// Or join directly (if approval required, status will be Pending)
$membership = $programService->joinProgram($affiliate, $program);
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
use AIArmada\Affiliates\Models\AffiliateProgramCreative;

AffiliateProgramCreative::create([
    'program_id' => $program->id,
    'name' => 'Summer Sale Banner',
    'type' => 'banner',
    'asset_url' => 'https://cdn.example.com/banners/summer-sale.jpg',
    'width' => 728,
    'height' => 90,
    'metadata' => [
        'alt_text' => 'Summer Sale - 20% Off',
        'click_url' => 'https://example.com/summer-sale',
    ],
]);
```

## Using the ProgramService

```php
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Affiliates\Services\ProgramService;

$service = app(ProgramService::class);

// Get available programs
$programs = $service->getAvailablePrograms();

// Check eligibility
$eligible = $program->canJoin($affiliate);

// Join a program
$membership = $service->joinProgram($affiliate, $program);

// Upgrade tier
$service->upgradeTier($affiliate, $program, $goldTier);

// Get affiliate's programs
$memberships = AffiliateProgramMembership::where('affiliate_id', $affiliate->id)
    ->with('program', 'tier')
    ->get();
```

## Commission Templates

Pre-defined commission structures for quick affiliate setup:

```php
use AIArmada\Affiliates\Models\AffiliateCommissionTemplate;

// Create standard percentage template
$template = AffiliateCommissionTemplate::createStandardPercentage(
    name: 'Standard 10%',
    rateBasisPoints: 1000,
    isDefault: true,
);

// Create tiered volume template
$template = AffiliateCommissionTemplate::createTieredVolume(
    name: 'Volume Tiers',
    baseRateBasisPoints: 500,
    volumeTiers: [
        ['min_volume' => 0, 'max_volume' => 100000, 'bonus_rate' => 0],
        ['min_volume' => 100001, 'max_volume' => 500000, 'bonus_rate' => 100],
        ['min_volume' => 500001, 'max_volume' => null, 'bonus_rate' => 200],
    ],
);

// Create MLM template
$template = AffiliateCommissionTemplate::createMlm(
    name: '3-Level MLM',
    baseRateBasisPoints: 1000,
    overridePercentages: [50, 25, 10], // 50%, 25%, 10% of commission to uplines
);

// Apply template to affiliate
$template->applyToAffiliate($affiliate);

// Apply template to program
$template->applyToProgram($program);
```

## Volume Tiers

Programs can define volume-based commission tiers:

```php
use AIArmada\Affiliates\Models\AffiliateVolumeTier;

AffiliateVolumeTier::create([
    'program_id' => $program->id,
    'name' => 'Starter',
    'min_volume_minor' => 0,
    'max_volume_minor' => 100000,
    'commission_rate_basis_points' => 0, // No bonus
]);

AffiliateVolumeTier::create([
    'program_id' => $program->id,
    'name' => 'High Volume',
    'min_volume_minor' => 100001,
    'max_volume_minor' => null, // Unlimited
    'commission_rate_basis_points' => 100, // +1% bonus
]);
```

## Commission Rules

Custom rules for specific conditions:

```php
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Enums\CommissionRuleType;

AffiliateCommissionRule::create([
    'program_id' => $program->id,
    'name' => 'Electronics boost',
    'rule_type' => CommissionRuleType::Product,
    'commission_type' => 'percentage',
    'commission_value' => 2000, // 20% for specific products (basis points; minor units when fixed)
    'conditions' => [
        'product_categories' => ['electronics', 'software'],
    ],
    'is_active' => true,
    'priority' => 10,
]);
```

## Commission Promotions

Time-limited commission boosts:

```php
use AIArmada\Affiliates\Models\AffiliateCommissionPromotion;

AffiliateCommissionPromotion::create([
    'program_id' => $program->id,
    'name' => 'Holiday Bonus',
    'bonus_type' => 'percentage', // percentage, flat, or multiplier
    'bonus_value' => 500, // +5% bonus
    'starts_at' => now(),
    'ends_at' => now()->addMonth(),
]);
```
