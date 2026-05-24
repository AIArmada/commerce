# Affiliate Programs & Campaigns

> **Document:** 03 of 11  
> **Package:** `aiarmada/affiliates`  
> **Status:** 🔴 0% Implemented  
> **Last Updated:** December 5, 2025

---

## Overview

Introduce **Affiliate Programs** as a first-class entity, enabling businesses to run multiple campaigns with distinct commission structures, creative assets, landing pages, and eligibility requirements.

---

## Current State ⚠️

### Not Yet Implemented

This feature set is **entirely planned** — no code exists for affiliate programs or campaigns.

**Current Reality:**
- All affiliates share one global commission structure
- No program/campaign segmentation
- No creative asset management
- No program-specific tracking URLs
- No eligibility rules or approval workflows

**Workarounds in Use:**
- Manual commission rate adjustments per affiliate
- Metadata field for informal program categorization
- External asset sharing via docs/drives

---

## Vision Architecture

### Program Hierarchy

```
┌──────────────────────────────────────────────────────────────┐
│                    PROGRAM STRUCTURE                          │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  Business                                                     │
│  ├── Program: "Standard Affiliate Program"                   │
│  │   ├── Tier: Basic (5% commission)                         │
│  │   ├── Tier: Pro (8% commission)                           │
│  │   └── Tier: Elite (12% commission)                        │
│  │                                                            │
│  ├── Program: "Holiday Campaign 2025"                        │
│  │   ├── Duration: Dec 1 - Dec 31                            │
│  │   ├── Bonus: +3% all conversions                          │
│  │   └── Creative: Holiday banners, emails                   │
│  │                                                            │
│  └── Program: "Influencer Program"                           │
│      ├── Invite-only                                          │
│      ├── Custom landing pages                                 │
│      └── 15% base + bonuses                                   │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## Proposed Models

### AffiliateProgram

```php
/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $description
 * @property AffiliateStatus $status (draft, active, paused, archived)
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $requires_approval
 * @property bool $is_public (visible in marketplace)
 * @property int $default_commission_rate_basis_points
 * @property CommissionType $commission_type
 * @property int $cookie_lifetime_days
 * @property string $terms_url
 * @property array $eligibility_rules
 * @property array $metadata
 */
class AffiliateProgram extends Model
{
    use HasUuids, HasSlug;
    
    public function tiers(): HasMany;
    public function affiliates(): BelongsToMany;
    public function creatives(): HasMany;
    public function conversions(): HasManyThrough;
    
    public function isActive(): bool;
    public function isOpen(): bool;
    public function canJoin(Affiliate $affiliate): bool;
}
```

### AffiliateProgramTier

```php
/**
 * @property string $id
 * @property string $program_id
 * @property string $name (Basic, Pro, Elite)
 * @property int $level (sort order)
 * @property int $commission_rate_basis_points
 * @property int $min_conversions (auto-upgrade threshold)
 * @property int $min_revenue (auto-upgrade threshold)
 * @property array $benefits
 */
class AffiliateProgramTier extends Model
{
    use HasUuids;
    
    public function program(): BelongsTo;
    public function affiliates(): HasMany;
    
    public function meetsUpgradeRequirements(Affiliate $affiliate): bool;
}
```

### AffiliateProgramMembership (Pivot)

```php
/**
 * @property string $affiliate_id
 * @property string $program_id
 * @property string $tier_id
 * @property string $status (pending, approved, rejected, suspended)
 * @property Carbon $applied_at
 * @property Carbon $approved_at
 * @property Carbon $expires_at
 * @property string $approved_by (user_id)
 * @property array $custom_terms
 */
class AffiliateProgramMembership extends Pivot
{
    use HasUuids;
    
    public function affiliate(): BelongsTo;
    public function program(): BelongsTo;
    public function tier(): BelongsTo;
}
```

### AffiliateProgramCreative

```php
/**
 * @property string $id
 * @property string $program_id
 * @property string $type (banner, text_link, email, video, landing_page)
 * @property string $name
 * @property string $description
 * @property int $width (for banners)
 * @property int $height (for banners)
 * @property string $asset_url
 * @property string $destination_url
 * @property string $tracking_code
 * @property array $metadata
 */
class AffiliateProgramCreative extends Model
{
    use HasUuids;
    
    public function program(): BelongsTo;
    
    public function getTrackingUrl(Affiliate $affiliate): string;
}
```

---

## Configuration

```php
// config/affiliates.php
'programs' => [
    'enabled' => true,
    'allow_multiple_memberships' => true, // Affiliate can join multiple programs
    'require_approval_by_default' => false,
    'auto_tier_upgrade' => true, // Auto-promote based on performance
    
    'default_program' => [
        'name' => 'Standard Affiliate Program',
        'commission_rate' => 1000, // 10% in basis points
        'cookie_lifetime' => 30,
    ],
    
    'creatives' => [
        'allowed_types' => ['banner', 'text_link', 'email', 'video'],
        'max_file_size_kb' => 2048,
        'banner_sizes' => [
            ['width' => 728, 'height' => 90],  // Leaderboard
            ['width' => 300, 'height' => 250], // Medium Rectangle
            ['width' => 160, 'height' => 600], // Wide Skyscraper
            ['width' => 320, 'height' => 50],  // Mobile Banner
        ],
    ],
],
```

---

## Program Membership Workflow

### Application Flow

```
┌─────────────────────────────────────────────────────────────┐
│                 MEMBERSHIP WORKFLOW                          │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  1. DISCOVERY                                                │
│     Affiliate browses program marketplace                    │
│     Views program details, terms, commission structure       │
│                                                              │
│  2. APPLICATION                                              │
│     Affiliate applies to join program                        │
│     Provides required information (website, audience, etc.)  │
│                                                              │
│  3. REVIEW (if requires_approval)                            │
│     Admin reviews application                                │
│     Approves / Rejects with reason                           │
│                                                              │
│  4. ONBOARDING                                               │
│     Affiliate receives welcome email                         │
│     Access to creatives and tracking links                   │
│     Program-specific dashboard                               │
│                                                              │
│  5. TIER PROGRESSION                                         │
│     Performance tracking                                     │
│     Auto-upgrade when thresholds met                         │
│     Tier benefits unlocked                                   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Services

### AffiliateProgramService

```php
class AffiliateProgramService
{
    public function create(array $data): AffiliateProgram;
    public function update(AffiliateProgram $program, array $data): AffiliateProgram;
    
    public function apply(Affiliate $affiliate, AffiliateProgram $program): AffiliateProgramMembership;
    public function approve(AffiliateProgramMembership $membership, ?string $approvedBy = null): void;
    public function reject(AffiliateProgramMembership $membership, string $reason): void;
    public function suspend(AffiliateProgramMembership $membership, string $reason): void;
    
    public function evaluateTierUpgrade(Affiliate $affiliate, AffiliateProgram $program): ?AffiliateProgramTier;
    public function upgradeTier(AffiliateProgramMembership $membership, AffiliateProgramTier $tier): void;
    
    public function getAvailablePrograms(Affiliate $affiliate): Collection;
    public function getCreatives(AffiliateProgram $program, ?string $type = null): Collection;
}
```

### CreativeTrackingService

```php
class CreativeTrackingService
{
    public function generateTrackingUrl(
        AffiliateProgramCreative $creative,
        Affiliate $affiliate
    ): string;
    
    public function recordImpression(
        AffiliateProgramCreative $creative,
        Affiliate $affiliate,
        Request $request
    ): void;
    
    public function recordClick(
        AffiliateProgramCreative $creative,
        Affiliate $affiliate,
        Request $request
    ): void;
    
    public function getCreativeStats(
        AffiliateProgramCreative $creative,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): array;
}
```

---

## Database Schema

```php
// affiliate_programs table
Schema::create('affiliate_programs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->text('description')->nullable();
    $table->string('status')->default('draft');
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->boolean('requires_approval')->default(false);
    $table->boolean('is_public')->default(true);
    $table->integer('default_commission_rate_basis_points');
    $table->string('commission_type')->default('percentage');
    $table->integer('cookie_lifetime_days')->default(30);
    $table->string('terms_url')->nullable();
    $table->json('eligibility_rules')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->softDeletes();
});

// affiliate_program_tiers table
Schema::create('affiliate_program_tiers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('program_id');
    $table->string('name');
    $table->integer('level')->default(0);
    $table->integer('commission_rate_basis_points');
    $table->integer('min_conversions')->default(0);
    $table->integer('min_revenue')->default(0);
    $table->json('benefits')->nullable();
    $table->timestamps();
    $table->index(['program_id', 'level']);
});

// affiliate_program_memberships table (pivot)
Schema::create('affiliate_program_memberships', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->foreignUuid('program_id');
    $table->foreignUuid('tier_id')->nullable();
    $table->string('status')->default('pending');
    $table->timestamp('applied_at');
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('expires_at')->nullable();
    $table->foreignUuid('approved_by')->nullable();
    $table->json('custom_terms')->nullable();
    $table->timestamps();
    $table->unique(['affiliate_id', 'program_id']);
    $table->index(['program_id', 'status']);
});

// affiliate_program_creatives table
Schema::create('affiliate_program_creatives', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('program_id');
    $table->string('type');
    $table->string('name');
    $table->text('description')->nullable();
    $table->integer('width')->nullable();
    $table->integer('height')->nullable();
    $table->string('asset_url');
    $table->string('destination_url');
    $table->string('tracking_code')->unique();
    $table->json('metadata')->nullable();
    $table->timestamps();
    $table->index(['program_id', 'type']);
});

// creative_impressions table (optional, high volume)
Schema::create('affiliate_creative_impressions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('creative_id');
    $table->foreignUuid('affiliate_id');
    $table->string('ip_address', 45);
    $table->string('user_agent')->nullable();
    $table->timestamp('viewed_at');
    $table->index(['creative_id', 'viewed_at']);
});
```

---

## Events

| Event | Trigger | Payload |
|-------|---------|---------|
| `AffiliateProgramCreated` | New program created | program |
| `AffiliateProgramStatusChanged` | Program activated/paused/archived | program, oldStatus, newStatus |
| `AffiliateAppliedToProgram` | Affiliate applies | affiliate, program, membership |
| `AffiliateProgramMembershipApproved` | Application approved | membership, approvedBy |
| `AffiliateProgramMembershipRejected` | Application rejected | membership, reason |
| `AffiliateTierUpgraded` | Tier promotion | affiliate, program, oldTier, newTier |
| `CreativeClicked` | Tracking link clicked | creative, affiliate, request |

---

## Filament Integration

### AffiliateResource Enhancement

```php
// Add programs relation manager
AffiliateResource::getRelations()
    ->add(ProgramMembershipsRelationManager::class);
    
// Add programs tab to affiliate view
AffiliateResource::infolist()
    ->schema([
        Tabs::make()
            ->tabs([
                Tab::make('Programs')
                    ->schema([
                        RepeatableEntry::make('programMemberships')
                            ->schema([
                                TextEntry::make('program.name'),
                                TextEntry::make('tier.name'),
                                BadgeEntry::make('status'),
                                TextEntry::make('approved_at')->dateTime(),
                            ]),
                    ]),
            ]),
    ]);
```

### New Resources

- `AffiliateProgramResource` - Manage programs
- `AffiliateProgramCreativeResource` - Manage creatives per program

---

## Implementation Priority

| Phase | Deliverable | Effort | Status |
|-------|-------------|--------|--------|
| 1 | AffiliateProgram model & migration | 2 days | ⬜ Todo |
| 2 | Tier system | 1 day | ⬜ Todo |
| 3 | Membership pivot & workflow | 2 days | ⬜ Todo |
| 4 | Creative asset management | 2 days | ⬜ Todo |
| 5 | Program-scoped tracking URLs | 1 day | ⬜ Todo |
| 6 | AffiliateProgramService | 2 days | ⬜ Todo |
| 7 | Filament resources | 3 days | ⬜ Todo |
| 8 | Program marketplace (portal) | 3 days | ⬜ Todo |

**Total Effort:** ~2.5 weeks

---

## Navigation

**Previous:** [02-multi-tier-network.md](02-multi-tier-network.md)  
**Next:** [04-fraud-detection.md](04-fraud-detection.md)
