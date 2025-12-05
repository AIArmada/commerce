# Multi-Tier Network Marketing

> **Document:** 02 of 11  
> **Package:** `aiarmada/affiliates`  
> **Status:** 🟡 35% Implemented  
> **Last Updated:** December 5, 2025

---

## Overview

Transform the current two-level affiliate system into a comprehensive **Multi-Level Marketing (MLM)** infrastructure supporting unlimited depth, override commissions, network visualization, and rank-based qualification systems.

---

## Current State ✅

### Implemented Features

1. **Parent-Child Relationships**
   - `parent_affiliate_id` on Affiliate model
   - `parent()` and `children()` relationships
   - Application-level cascade on delete (nullifies children's parent)

2. **Basic Multi-Level Commissions**
   - Configurable via `affiliates.payouts.multi_level.enabled`
   - Level shares via `affiliates.payouts.multi_level.levels` array
   - `applyMultiLevelCommissions()` in AffiliateService
   - Upline traversal with weighted commission distribution
   - Metadata tracking (`upline_of`, `level`, `weight`, `base_conversion`)

3. **Configuration**
   ```php
   'payouts' => [
       'multi_level' => [
           'enabled' => env('AFFILIATES_MULTI_LEVEL_ENABLED', false),
           'levels' => [0.1, 0.05], // 10% to level 1, 5% to level 2
       ],
   ],
   ```

### Limitations (To Be Addressed)

- Fixed two-level depth (configurable but simple array)
- No closure table for efficient tree queries
- No rank/qualification system
- No network visualization
- No downline performance aggregation

---

## Vision Architecture

### Network Hierarchy Model

```
┌─────────────────────────────────────────────────────────────┐
│                    NETWORK STRUCTURE                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Diamond (Level 0)                                          │
│  └── Platinum (Level 1)                                     │
│      ├── Gold (Level 2)                                     │
│      │   ├── Silver (Level 3)                               │
│      │   │   └── Bronze (Level 4)                           │
│      │   │       └── Affiliate (Level 5)                    │
│      │   └── Silver (Level 3)                               │
│      └── Gold (Level 2)                                     │
│                                                              │
│  Override Commission Flow: ←←←←←←←←←←←←←←←←←←←←←←           │
│  Sale at Level 5 pays: L5 (10%) → L4 (2%) → L3 (1%) → ...  │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## Proposed Models

### AffiliateRank

```php
/**
 * @property string $id
 * @property string $name (Bronze, Silver, Gold, Platinum, Diamond)
 * @property string $slug
 * @property int $level (0-10, lower = higher rank)
 * @property int $min_personal_sales (qualification requirement)
 * @property int $min_team_sales (qualification requirement)
 * @property int $min_active_downlines (qualification requirement)
 * @property int $commission_rate_basis_points
 * @property array $override_rates (per level depth)
 * @property array $benefits (perks, bonuses)
 * @property array $metadata
 */
class AffiliateRank extends Model
{
    use HasUuids;
    
    public function isHigherThan(AffiliateRank $other): bool;
    public function meetsQualification(Affiliate $affiliate): bool;
}
```

### AffiliateNetwork (Closure Table)

```php
/**
 * Closure table for efficient ancestor/descendant queries
 * 
 * @property string $ancestor_id
 * @property string $descendant_id
 * @property int $depth
 */
class AffiliateNetwork extends Model
{
    public static function getAncestors(Affiliate $affiliate): Collection;
    public static function getDescendants(Affiliate $affiliate): Collection;
    public static function getAtDepth(Affiliate $affiliate, int $depth): Collection;
}
```

### AffiliateRankHistory

```php
/**
 * @property string $affiliate_id
 * @property string $from_rank_id
 * @property string $to_rank_id
 * @property string $reason (qualified, demoted, manual)
 * @property Carbon $qualified_at
 */
class AffiliateRankHistory extends Model
{
    // Audit trail for rank changes
}
```

---

## Override Commission System

### Enhanced Configuration

```php
// config/affiliates.php
'network' => [
    'enabled' => true,
    'max_depth' => 10, // 0 = unlimited
    'compression' => true, // Skip inactive levels
    
    'override_rates' => [
        // Depth => Override percentage (basis points)
        1 => 200,  // 2% to immediate upline
        2 => 100,  // 1% to level 2
        3 => 50,   // 0.5% to level 3
        4 => 25,   // 0.25% to level 4
        5 => 25,   // 0.25% to level 5
    ],
    
    'rank_overrides' => [
        // Higher ranks can earn more override
        'diamond' => [1 => 300, 2 => 200, 3 => 100],
        'platinum' => [1 => 250, 2 => 150, 3 => 75],
        'gold' => [1 => 200, 2 => 100],
    ],
],
```

### Commission Distribution Algorithm

```php
class NetworkCommissionCalculator
{
    public function distribute(
        AffiliateConversion $conversion,
        int $saleAmountCents
    ): Collection {
        $commissions = collect();
        $affiliate = $conversion->affiliate;
        
        // 1. Direct commission to converting affiliate
        $directRate = $affiliate->getEffectiveCommissionRate();
        $commissions->push(new CommissionEntry(
            affiliate: $affiliate,
            amount: $this->calculate($saleAmountCents, $directRate),
            type: 'direct'
        ));
        
        // 2. Override commissions to upline (using closure table)
        $ancestors = AffiliateNetwork::getAncestors($affiliate);
        
        foreach ($ancestors as $depth => $upline) {
            if ($depth > $this->maxDepth) break;
            if (!$upline->isActive()) continue; // Compression
            
            $overrideRate = $this->getOverrideRate($upline, $depth);
            if ($overrideRate <= 0) continue;
            
            $commissions->push(new CommissionEntry(
                affiliate: $upline,
                amount: $this->calculate($saleAmountCents, $overrideRate),
                type: 'override',
                depth: $depth
            ));
        }
        
        return $commissions;
    }
}
```

---

## Rank Qualification Engine

### Qualification Rules

```php
class RankQualificationService
{
    public function evaluate(Affiliate $affiliate): ?AffiliateRank
    {
        $metrics = $this->calculateMetrics($affiliate);
        
        // Find highest qualifying rank
        return AffiliateRank::query()
            ->orderBy('level', 'asc') // Diamond first
            ->get()
            ->first(fn ($rank) => $this->meetsRequirements($metrics, $rank));
    }
    
    private function calculateMetrics(Affiliate $affiliate): array
    {
        $period = now()->subDays(30); // Rolling 30-day
        
        return [
            'personal_sales' => $affiliate->conversions()
                ->where('occurred_at', '>=', $period)
                ->sum('total_minor'),
                
            'team_sales' => $this->getTeamSales($affiliate, $period),
            
            'active_downlines' => $this->countActiveDownlines($affiliate),
            
            'lifetime_value' => $affiliate->conversions()->sum('total_minor'),
        ];
    }
}
```

---

## Network Visualization

### Tree Data Structure

```php
class NetworkTreeBuilder
{
    public function build(Affiliate $root, int $maxDepth = 5): array
    {
        return [
            'id' => $root->id,
            'name' => $root->name,
            'code' => $root->code,
            'rank' => $root->rank?->name,
            'stats' => [
                'personal_sales' => $root->getPersonalSales(),
                'team_sales' => $root->getTeamSales(),
                'direct_recruits' => $root->children()->count(),
            ],
            'children' => $this->buildChildren($root, 1, $maxDepth),
        ];
    }
}
```

### Visualization Options

1. **Hierarchical Tree** - Traditional org chart view
2. **Radial Tree** - Circular layout for large networks
3. **Sunburst Chart** - Nested rings showing depth
4. **Force-Directed Graph** - Interactive network visualization
5. **Table View** - Flat list with indentation

---

## Database Schema

### New Tables

```php
// affiliates table additions
$table->foreignUuid('rank_id')->nullable();
$table->integer('network_depth')->default(0);
$table->integer('direct_downline_count')->default(0);
$table->integer('total_downline_count')->default(0);

// affiliate_ranks table
Schema::create('affiliate_ranks', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('slug')->unique();
    $table->integer('level')->unique();
    $table->integer('min_personal_sales')->default(0);
    $table->integer('min_team_sales')->default(0);
    $table->integer('min_active_downlines')->default(0);
    $table->integer('commission_rate_basis_points');
    $table->json('override_rates')->nullable();
    $table->json('benefits')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
});

// affiliate_network (closure table)
Schema::create('affiliate_network', function (Blueprint $table) {
    $table->foreignUuid('ancestor_id');
    $table->foreignUuid('descendant_id');
    $table->integer('depth');
    $table->primary(['ancestor_id', 'descendant_id']);
    $table->index(['descendant_id', 'depth']);
});

// affiliate_rank_histories table
Schema::create('affiliate_rank_histories', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('affiliate_id');
    $table->foreignUuid('from_rank_id')->nullable();
    $table->foreignUuid('to_rank_id')->nullable();
    $table->string('reason');
    $table->timestamp('qualified_at');
    $table->timestamps();
    $table->index(['affiliate_id', 'qualified_at']);
});
```

---

## Events

| Event | Trigger | Payload |
|-------|---------|---------|
| `AffiliateRankChanged` | Rank promotion/demotion | affiliate, fromRank, toRank, reason |
| `OverrideCommissionEarned` | Upline earns override | affiliate, conversion, amount, depth |
| `DownlineJoined` | New affiliate added to network | sponsor, newAffiliate, depth |
| `NetworkMilestoneReached` | Team size/sales milestone | affiliate, milestone, value |

---

## Implementation Priority

| Phase | Deliverable | Effort | Status |
|-------|-------------|--------|--------|
| 1 | Parent-child relationships | 1 day | ✅ Done |
| 2 | Basic multi-level config | 1 day | ✅ Done |
| 3 | Closure table migration | 2 days | ⬜ Todo |
| 4 | Rank model & qualification | 3 days | ⬜ Todo |
| 5 | Override commission calculator | 3 days | ⬜ Todo |
| 6 | Network tree builder | 2 days | ⬜ Todo |
| 7 | Filament visualization | 3 days | ⬜ Todo |
| 8 | Scheduled rank processing | 1 day | ⬜ Todo |

**Remaining Effort:** ~2 weeks

---

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-affiliate-programs.md](03-affiliate-programs.md)
