# Implementation Roadmap

> **Document:** 10-implementation-roadmap.md  
> **Status:** Planning  
> **Last Updated:** December 3, 2025

---

## Timeline Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                    IMPLEMENTATION TIMELINE                           │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  2025 Q4 (Dec)                                                       │
│  └── Phase 0: Foundation & Cart Dependency ✅                       │
│                                                                      │
│  2026 Q1 (Jan-Mar)                                                   │
│  ├── Phase 1: Stacking Policies                                     │
│  └── Phase 2: Enhanced Targeting                                    │
│                                                                      │
│  2026 Q2 (Apr-Jun)                                                   │
│  ├── Phase 3: Campaign Management                                   │
│  └── Phase 4: A/B Testing                                           │
│                                                                      │
│  2026 Q3 (Jul-Sep)                                                   │
│  ├── Phase 5: Gift Card System                                      │
│  └── Phase 6: Advanced Voucher Types                                │
│                                                                      │
│  2026 Q4 (Oct-Dec)                                                   │
│  ├── Phase 7: Fraud Detection                                       │
│  └── Phase 8: AI Optimization (Start)                               │
│                                                                      │
│  2027 Q1+                                                            │
│  └── Phase 9: AI Optimization (Complete)                            │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Phase 0: Foundation (Complete ✅)

**Duration:** 1 day  
**Status:** ✅ Complete  
**Date:** December 3, 2025

### Completed Tasks

| Task | Status | Files |
|------|--------|-------|
| Make cart a required dependency | ✅ | `composer.json` |
| Update package description | ✅ | `composer.json` |
| Update README documentation | ✅ | `README.md` |
| Create vision documentation | ✅ | `docs/vision/*` |

### Outcome

The vouchers package is now correctly declared as a first-party extension of the cart package, with proper architectural documentation.

---

## Phase 1: Stacking Policies

**Duration:** 2 weeks  
**Priority:** P0 (Critical)  
**Dependencies:** None

### Tasks

| Task | Effort | Status |
|------|--------|--------|
| Create `StackingPolicyInterface` | 2h | ⏳ |
| Create `StackingDecision` value object | 1h | ⏳ |
| Create `StackingEngine` orchestrator | 4h | ⏳ |
| Implement `MaxVouchersRule` | 2h | ⏳ |
| Implement `MaxDiscountRule` | 2h | ⏳ |
| Implement `MutualExclusionRule` | 3h | ⏳ |
| Implement `TypeRestrictionRule` | 2h | ⏳ |
| Add stacking columns migration | 1h | ⏳ |
| Update `InteractsWithVouchers` trait | 4h | ⏳ |
| Add stacking configuration | 1h | ⏳ |
| Write tests | 8h | ⏳ |
| Update documentation | 2h | ⏳ |

### Files to Create/Modify

```
src/
├── Stacking/
│   ├── Contracts/
│   │   ├── StackingPolicyInterface.php
│   │   └── StackingRuleInterface.php
│   ├── StackingDecision.php
│   ├── StackingEngine.php
│   ├── StackingPolicy.php
│   └── Rules/
│       ├── MaxVouchersRule.php
│       ├── MaxDiscountRule.php
│       ├── MaxDiscountPercentageRule.php
│       ├── MutualExclusionRule.php
│       └── TypeRestrictionRule.php
├── Support/
│   └── InteractsWithVouchers.php (modify)
config/
└── vouchers.php (modify - add stacking section)
database/migrations/
└── 2026_01_XX_add_stacking_columns_to_vouchers.php
```

---

## Phase 2: Enhanced Targeting

**Duration:** 2 weeks  
**Priority:** P1  
**Dependencies:** None

### Tasks

| Task | Effort | Status |
|------|--------|--------|
| Create `TargetingContext` | 2h | ⏳ |
| Create `TargetingRuleEvaluator` interface | 1h | ⏳ |
| Create `TargetingEngine` | 4h | ⏳ |
| Implement `UserSegmentEvaluator` | 2h | ⏳ |
| Implement `CartValueEvaluator` | 2h | ⏳ |
| Implement `ProductCategoryEvaluator` | 3h | ⏳ |
| Implement `TimeWindowEvaluator` | 2h | ⏳ |
| Implement `GeographicEvaluator` | 2h | ⏳ |
| Add boolean expression parser | 4h | ⏳ |
| Update `VoucherCondition.validateVoucher()` | 2h | ⏳ |
| Add targeting column migration | 1h | ⏳ |
| Write tests | 8h | ⏳ |

### Files to Create/Modify

```
src/
├── Targeting/
│   ├── Contracts/
│   │   └── TargetingRuleEvaluator.php
│   ├── TargetingContext.php
│   ├── TargetingEngine.php
│   ├── ExpressionParser.php
│   └── Evaluators/
│       ├── UserSegmentEvaluator.php
│       ├── CartValueEvaluator.php
│       ├── ProductCategoryEvaluator.php
│       ├── TimeWindowEvaluator.php
│       ├── GeographicEvaluator.php
│       └── ChannelEvaluator.php
├── Conditions/
│   └── VoucherCondition.php (modify)
```

---

## Phase 3: Campaign Management

**Duration:** 3 weeks  
**Priority:** P2  
**Dependencies:** Phase 1, Phase 2

### Tasks

| Task | Effort | Status |
|------|--------|--------|
| Create `Campaign` model | 2h | ⏳ |
| Create `CampaignVariant` model | 2h | ⏳ |
| Create `CampaignEvent` model | 2h | ⏳ |
| Create campaign migrations | 2h | ⏳ |
| Create `CampaignService` | 4h | ⏳ |
| Create `CampaignAnalytics` | 6h | ⏳ |
| Link vouchers to campaigns | 2h | ⏳ |
| Event tracking integration | 4h | ⏳ |
| Filament `CampaignResource` | 8h | ⏳ |
| Filament dashboard widgets | 6h | ⏳ |
| Write tests | 8h | ⏳ |

---

## Phase 4: A/B Testing

**Duration:** 2 weeks  
**Priority:** P2  
**Dependencies:** Phase 3

### Tasks

| Task | Effort | Status |
|------|--------|--------|
| Create `CampaignVariantAssigner` | 4h | ⏳ |
| Implement sticky sessions | 3h | ⏳ |
| Create `ABTestAnalyzer` | 6h | ⏳ |
| Implement significance calculation | 4h | ⏳ |
| Filament A/B test dashboard | 8h | ⏳ |
| Variant comparison UI | 4h | ⏳ |
| Auto-winner declaration | 3h | ⏳ |
| Write tests | 6h | ⏳ |

---

## Phase 5: Gift Card System

**Duration:** 4 weeks  
**Priority:** P1  
**Dependencies:** Cart balance operator (coordination needed)

### Tasks

| Task | Effort | Status |
|------|--------|--------|
| Create `GiftCard` model | 2h | ⏳ |
| Create `GiftCardTransaction` model | 2h | ⏳ |
| Create gift card migrations | 2h | ⏳ |
| Create `GiftCardService` | 6h | ⏳ |
| Create `GiftCardCondition` | 4h | ⏳ |
| **Cart: Balance deduction operator** | 8h | ⏳ |
| Create `InteractsWithGiftCards` trait | 4h | ⏳ |
| Multi-card checkout support | 6h | ⏳ |
| Refund handling | 4h | ⏳ |
| Filament `GiftCardResource` | 6h | ⏳ |
| Bulk issuance tool | 4h | ⏳ |
| Write tests | 10h | ⏳ |

### Cart Package Coordination

The gift card system requires a new operator in the cart package:

```php
// New balance deduction operator '~'
case '~' => $this->applyBalanceDeduction($value),
```

This needs to be implemented in `CartCondition` before gift cards can fully function.

---

## Phase 6: Advanced Voucher Types

**Duration:** 4 weeks  
**Priority:** P1  
**Dependencies:** Phase 5 (for compound conditions)

### Tasks

| Task | Effort | Status |
|------|--------|--------|
| Add `value_config` column | 1h | ⏳ |
| Create `CompoundVoucherCondition` base | 4h | ⏳ |
| Implement `BOGOVoucherCondition` | 8h | ⏳ |
| Implement `TieredVoucherCondition` | 6h | ⏳ |
| Implement `BundleVoucherCondition` | 6h | ⏳ |
| Implement `CashbackVoucherCondition` | 6h | ⏳ |
| Product matcher interface | 4h | ⏳ |
| Post-checkout cashback job | 3h | ⏳ |
| Filament compound voucher forms | 8h | ⏳ |
| Write tests | 12h | ⏳ |

---

## Phase 7: Fraud Detection

**Duration:** 3 weeks  
**Priority:** P2  
**Dependencies:** Phase 3 (event tracking)

### Tasks

| Task | Effort | Status |
|------|--------|--------|
| Create `VoucherFraudSignal` model | 2h | ⏳ |
| Create fraud signal migration | 1h | ⏳ |
| Create `VoucherFraudDetector` interface | 2h | ⏳ |
| Implement `VelocityDetector` | 4h | ⏳ |
| Implement `PatternDetector` | 6h | ⏳ |
| Implement `GeoAnomalyDetector` | 4h | ⏳ |
| Create `FraudAnalysis` value object | 2h | ⏳ |
| Integrate into voucher application flow | 3h | ⏳ |
| Filament fraud monitoring widget | 4h | ⏳ |
| Alert notification system | 3h | ⏳ |
| Write tests | 6h | ⏳ |

---

## Phase 8-9: AI Optimization

**Duration:** 8+ weeks  
**Priority:** P3 (Long-term)  
**Dependencies:** Phase 7

### Phase 8: Foundation (4 weeks)

| Task | Effort | Status |
|------|--------|--------|
| Feature extraction pipeline | 12h | ⏳ |
| Training data collection | 8h | ⏳ |
| ML infrastructure setup | 16h | ⏳ |
| Abandonment prediction model | 16h | ⏳ |
| Integration hooks | 8h | ⏳ |

### Phase 9: Advanced (4+ weeks)

| Task | Effort | Status |
|------|--------|--------|
| Conversion prediction model | 16h | ⏳ |
| Discount optimization engine | 20h | ⏳ |
| Voucher matching algorithm | 12h | ⏳ |
| Production deployment | 16h | ⏳ |
| Monitoring & iteration | Ongoing | ⏳ |

---

## Risk Assessment

| Phase | Technical Risk | Business Impact | Mitigation |
|-------|---------------|-----------------|------------|
| Phase 0 | Low | Low | ✅ Complete |
| Phase 1 | Medium | High | Clear interfaces, extensive tests |
| Phase 2 | Low | Medium | Modular evaluators |
| Phase 3 | Medium | High | Incremental delivery |
| Phase 4 | Medium | High | Statistical validation |
| Phase 5 | High | Very High | Cart coordination essential |
| Phase 6 | High | High | Compound condition complexity |
| Phase 7 | Medium | Medium | False positive tuning |
| Phase 8-9 | Very High | Very High | Phased ML rollout |

---

## Success Metrics

| Phase | Metric | Target |
|-------|--------|--------|
| Phase 1 | Multi-voucher checkout support | 100% |
| Phase 2 | Targeting rule coverage | 90%+ use cases |
| Phase 3 | Campaign adoption | 50%+ vouchers linked |
| Phase 4 | A/B test completion rate | 80%+ reach significance |
| Phase 5 | Gift card redemption success | 99.9% |
| Phase 6 | Compound voucher usage | 20%+ of total |
| Phase 7 | Fraud detection rate | 95%+ true positive |
| Phase 8-9 | AI recommendation accuracy | 70%+ conversion lift |

---

## Resource Requirements

| Phase | Developer Days | Dependencies |
|-------|---------------|--------------|
| Phase 0 | 0.5 | None |
| Phase 1 | 10 | None |
| Phase 2 | 10 | None |
| Phase 3 | 15 | Phases 1, 2 |
| Phase 4 | 10 | Phase 3 |
| Phase 5 | 20 | Cart team |
| Phase 6 | 20 | Phase 5 |
| Phase 7 | 15 | Phase 3 |
| Phase 8-9 | 40+ | Phase 7, ML expertise |

**Total Estimated:** 140+ developer days (~7 months with 1 developer)

---

## Navigation

**Previous:** [09-filament-enhancements.md](09-filament-enhancements.md)  
**Back to:** [01-executive-summary.md](01-executive-summary.md)
