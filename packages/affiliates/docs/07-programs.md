---
title: Programs & Tiers
---

# Affiliate Programs & Tiers

Programs allow you to create structured affiliate offerings with different commission rates, eligibility rules, and promotional materials.

## Creating Programs

```php
use AIArmada\Affiliates\Models\AffiliateProgram;
use AIArmada\Affiliates\Enums\ProgramStatus;

$program = AffiliateProgram::create([
    'name' => 'Premium Partners',
    'slug' => 'premium-partners',
    'description' => 'Our exclusive partner program with higher commissions',
    'status' => ProgramStatus::Active,
    'requires_approval' => true,
    'is_public' => true,
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

ProgramStatus::Draft;   // Not yet published
ProgramStatus::Active;  // Accepting enrollments
ProgramStatus::Paused;  // Temporarily closed
ProgramStatus::Ended;   // Permanently closed
```

## Creating Program Tiers

Tiers allow progressive commission rates based on performance:

```php
use AIArmada\Affiliates\Models\AffiliateProgramTier;

// Bronze tier (default)
AffiliateProgramTier::create([
    'affiliate_program_id' => $program->id,
    'name' => 'Bronze',
    'slug' => 'bronze',
    'commission_rate_basis_points' => 1000, // 10%
    'min_revenue_minor' => 0,
    'min_conversions' => 0,
    'sort_order' => 1,
]);

// Silver tier
AffiliateProgramTier::create([
    'affiliate_program_id' => $program->id,
    'name' => 'Silver',
    'slug' => 'silver',
    'commission_rate_basis_points' => 1250, // 12.5%
    'min_revenue_minor' => 100000, // $1,000
    'min_conversions' => 10,
    'sort_order' => 2,
]);

// Gold tier
AffiliateProgramTier::create([
    'affiliate_program_id' => $program->id,
    'name' => 'Gold',
    'slug' => 'gold',
    'commission_rate_basis_points' => 1500, // 15%
    'min_revenue_minor' => 500000, // $5,000
    'min_conversions' => 50,
    'sort_order' => 3,
]);
```

## Enrolling Affiliates

```php
use AIArmada\Affiliates\Services\ProgramService;
use AIArmada\Affiliates\Enums\MembershipStatus;

$programService = app(ProgramService::class);

// Check eligibility first
if ($programService->checkEligibility($affiliate, $program)) {
    $membership = $programService->enroll($affiliate, $program);
}

// Or enroll directly (if approval required, status will be Pending)
$membership = $programService->enroll($affiliate, $program);
```

## Membership Statuses

```php
use AIArmada\Affiliates\Enums\MembershipStatus;

MembershipStatus::Pending;    // Awaiting approval
MembershipStatus::Active;     // Enrolled and earning
MembershipStatus::Suspended;  // Temporarily disabled
MembershipStatus::Terminated; // Removed from program
```

## Program Creatives

Provide affiliates with promotional materials:

```php
use AIArmada\Affiliates\Models\AffiliateProgramCreative;

AffiliateProgramCreative::create([
    'affiliate_program_id' => $program->id,
    'name' => 'Summer Sale Banner',
    'type' => 'banner',
    'url' => 'https://cdn.example.com/banners/summer-sale.jpg',
    'dimensions' => '728x90',
    'is_active' => true,
    'metadata' => [
        'alt_text' => 'Summer Sale - 20% Off',
        'click_url' => 'https://example.com/summer-sale',
    ],
]);
```

## Using the ProgramService

```php
use AIArmada\Affiliates\Services\ProgramService;

$service = app(ProgramService::class);

// Get available programs for affiliate
$programs = $service->getAvailablePrograms($affiliate);

// Check eligibility
$eligible = $service->checkEligibility($affiliate, $program);

// Enroll affiliate
$membership = $service->enroll($affiliate, $program);

// Upgrade tier
$service->upgradeTier($membership, $goldTier);

// Get affiliate's programs
$memberships = $affiliate->programMemberships()
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

Independent of programs, affiliates can have volume-based commission bonuses:

```php
use AIArmada\Affiliates\Models\AffiliateVolumeTier;

AffiliateVolumeTier::create([
    'affiliate_id' => $affiliate->id,
    'min_volume_minor' => 0,
    'max_volume_minor' => 100000,
    'bonus_rate_basis_points' => 0, // No bonus
    'is_active' => true,
]);

AffiliateVolumeTier::create([
    'affiliate_id' => $affiliate->id,
    'min_volume_minor' => 100001,
    'max_volume_minor' => null, // Unlimited
    'bonus_rate_basis_points' => 100, // +1% bonus
    'is_active' => true,
]);
```

## Commission Rules

Custom rules for specific conditions:

```php
use AIArmada\Affiliates\Models\AffiliateCommissionRule;
use AIArmada\Affiliates\Enums\CommissionRuleType;

AffiliateCommissionRule::create([
    'affiliate_id' => $affiliate->id,
    'rule_type' => CommissionRuleType::Product,
    'commission_type' => 'percentage',
    'rate_basis_points' => 2000, // 20% for specific products
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
    'affiliate_id' => $affiliate->id,
    'name' => 'Holiday Bonus',
    'bonus_rate_basis_points' => 500, // +5% bonus
    'starts_at' => now(),
    'ends_at' => now()->addMonth(),
    'is_active' => true,
]);
```
