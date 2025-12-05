# Affiliates Vision Progress

> **Package:** `aiarmada/affiliates` + `aiarmada/filament-affiliates`  
> **Last Updated:** December 5, 2025

---

## Implementation Status

| Phase | Status | Progress |
|-------|--------|----------|
| Phase 1: Foundation & Core | 🟢 Completed | 100% |
| Phase 2: MLM Network & Programs | 🟡 Partial | 35% |
| Phase 3: Analytics & Reporting | 🟡 Partial | 40% |
| Phase 4: Fraud Detection | 🟡 Partial | 25% |
| Phase 5: Affiliate Portal | 🟡 Partial | 20% |
| Phase 6: Payout Automation | 🟡 Partial | 45% |
| Phase 7: Dynamic Commissions | 🟡 Partial | 30% |
| Phase 8: Filament Enhancements | 🟡 Partial | 50% |

---

## Phase 1: Foundation & Core Enhancements

### Tasks

- [x] Schema migrations for affiliates table expansion
  - `affiliates` table with UUID PK, status, commission_type, commission_rate, currency, parent_affiliate_id, owner scoping
  - `affiliate_attributions` table with UTM tracking, cookie tracking, user agent, IP, expiration
  - `affiliate_conversions` table with commission tracking, status workflow, payout linking
  - `affiliate_payouts` table with batch processing, status, scheduling
  - `affiliate_payout_events` table for audit trail
  - `affiliate_touchpoints` table for multi-touch attribution
- [x] Affiliate model expansion (relationships, scopes, casts)
  - `HasUuids` trait, `AffiliateStatus` and `CommissionType` enums
  - `parent()`, `children()`, `attributions()`, `conversions()`, `owner()` relationships
  - `forOwner()` scope, `isActive()` helper
  - Application-level cascade deletes in `booted()`
- [ ] AffiliateProgram model creation
- [ ] AffiliateTier model creation
- [ ] AffiliateBalance model implementation
- [x] Service refactoring for new models
  - `AffiliateService` with query scoping, attribution, conversion recording
  - `CommissionCalculator` with percentage/fixed calculation
  - `AffiliatePayoutService` with batch creation, status updates
  - `AffiliateReportService` with summary generation
  - `AttributionModel` with last-touch, first-touch, linear attribution
- [x] Configuration updates
  - Currency, table names, owner scoping, cart integration
  - Cookie tracking with consent gates, DNT respect
  - Voucher integration, commission settings
  - Payout configuration, multi-level settings
  - Tracking defaults, events, webhooks, links, API
- [x] Unit test coverage (24 test files covering all core functionality)

---

## Phase 2: MLM Network & Programs

### Tasks

- [ ] AffiliateNetwork closure table implementation
- [ ] AffiliateRank model (achievement levels)
- [ ] Network traversal service
- [x] Override commission service (basic multi-level implemented)
  - Configurable levels via `affiliates.payouts.multi_level.levels`
  - Parent traversal with weighted commission sharing
  - Upline conversion creation with metadata tracking
- [ ] Rank qualification engine
- [ ] Network visualization data provider
- [ ] Program management service
- [ ] Integration tests for MLM flows
- [x] Parent-child affiliate relationships (in Affiliate model)
- [x] Two-level depth support (configurable via config)

---

## Phase 3: Analytics & Reporting

### Tasks

- [ ] AffiliateDailyStat model
- [ ] Aggregation service (daily/hourly)
- [x] Dashboard data provider (`AffiliateStatsAggregator`)
  - Total/active/pending affiliates count
  - Pending/paid/total commission aggregation
  - Conversion rate calculation
  - Owner-scoped queries
- [x] Report generator (`AffiliateReportService`)
  - Affiliate summary with totals
  - Funnel metrics (attributions → conversions)
  - UTM aggregation (sources, campaigns)
- [ ] Export functionality (CSV, Excel, PDF)
  - [x] Basic CSV export via `ExportAffiliatePayoutCommand`
  - [ ] Excel export
  - [ ] PDF export
- [ ] Cohort analyzer
- [x] Attribution model comparison (last_touch, first_touch, linear)
- [ ] Scheduled aggregation commands

---

## Phase 4: Fraud Detection

### Tasks

- [ ] AffiliateFraudSignal model
- [x] VelocityDetector implementation (basic IP rate limiting)
  - Configurable max requests per IP
  - Cache-based counting with decay
- [ ] GeoAnomalyDetector implementation
- [x] PatternDetector implementation (basic fingerprint blocking)
  - SHA256 fingerprint from user agent + IP
  - Duplicate fingerprint detection per affiliate
- [ ] FraudScoreAggregator
- [x] Real-time protection middleware (`TrackAffiliateCookie`)
- [ ] Review workflow
- [x] Threshold configuration (IP rate limit, fingerprint settings)
- [ ] Fraud scenario tests
- [x] Self-referral blocking

---

## Phase 5: Affiliate Portal

### Tasks

- [ ] Portal authentication system
- [ ] Dashboard views
- [x] Link builder tool (`AffiliateLinkGenerator`)
  - Signed URLs with HMAC
  - Configurable TTL
  - Host allowlist validation
  - Signature verification
- [ ] AffiliateLink model
- [ ] Creative library (metadata support exists)
- [ ] Payout dashboard
- [ ] Profile management
- [ ] Network overview
- [ ] Support ticket system
- [ ] Training academy
- [x] API endpoints (summary, links, creatives) in `AffiliateApiController`

---

## Phase 6: Payout Automation

### Tasks

- [ ] PayoutBatch model (using `AffiliatePayout` currently)
- [ ] Payout processor factory
- [ ] Stripe Connect processor
- [ ] PayPal processor
- [ ] Bank transfer processor
- [ ] Commission maturity service
- [ ] Tax document service (1099)
- [ ] Reconciliation service
- [ ] Scheduled payout jobs
- [ ] Payout hold system
- [x] `AffiliatePayout` model with status workflow
- [x] `AffiliatePayoutEvent` model for audit trail
- [x] `AffiliatePayoutService` with batch creation, status updates
- [x] Webhook dispatch on payout status changes
- [x] `ExportAffiliatePayoutCommand` for CSV export

---

## Phase 7: Dynamic Commissions

### Tasks

- [ ] Commission rule engine
- [ ] ProductCommissionRule model
- [ ] VolumeTier model
- [ ] CommissionPromotion model
- [ ] Volume tier evaluator
- [ ] Time promotion evaluator
- [ ] Custom rule evaluator
- [ ] Commission templates
- [ ] Performance bonus service
- [x] `CommissionCalculator` with percentage/fixed types
- [x] Basis point scale configuration
- [x] Per-affiliate commission rates and currency

---

## Phase 8: Filament Enhancements

### Tasks

- [ ] PerformanceOverviewWidget
- [ ] RealTimeActivityWidget
- [ ] NetworkVisualizationWidget
- [ ] FraudAlertWidget
- [ ] PayoutQueueWidget
- [x] Enhanced AffiliateResource
  - Full CRUD with form/table/infolist
  - Status, commission type, rates, currency
  - Owner scoping, metadata
- [ ] AffiliateProgramResource
- [ ] BulkPayoutAction
- [ ] BulkFraudReviewAction
- [ ] FraudReviewPage
- [ ] PayoutBatchPage
- [ ] ReportsPage
- [ ] Network tree visualization page
- [x] Relation managers
  - `ConversionsRelationManager` on AffiliateResource
  - `ConversionsRelationManager` on AffiliatePayoutResource
- [x] `AffiliateStatsWidget` (5-stat overview)
- [x] `AffiliateConversionResource` (list, view)
- [x] `AffiliatePayoutResource` (list, view with events)
- [x] `CartBridge` integration (deep links to FilamentCart)
- [x] `VoucherBridge` integration (deep links to FilamentVouchers)
- [x] `PayoutExportService` for exports
- [x] `AffiliatePayoutPolicy` for authorization

---

## Legend

| Symbol | Meaning |
|--------|---------|
| 🔴 | Not Started |
| 🟡 | In Progress / Partial |
| 🟢 | Completed |
| ⏸️ | Paused |
| ❌ | Blocked |

---

## Current Architecture Summary

### Core Package (`aiarmada/affiliates`)

**Models:**
- `Affiliate` - Partner/program with status, commission, owner scoping
- `AffiliateAttribution` - Cart-level tracking with UTM, cookies, expiration
- `AffiliateConversion` - Monetized event with commission, status workflow
- `AffiliatePayout` - Batch payout with status, scheduling
- `AffiliatePayoutEvent` - Audit trail for payout status changes
- `AffiliateTouchpoint` - Multi-touch attribution tracking

**Enums:**
- `AffiliateStatus` (Draft, Pending, Active, Paused, Disabled)
- `CommissionType` (Percentage, Fixed)
- `ConversionStatus` (Pending, Qualified, Approved, Rejected, Paid)

**Services:**
- `AffiliateService` - Core operations, attribution, conversion recording
- `CommissionCalculator` - Commission calculation logic
- `AffiliatePayoutService` - Payout batch management
- `AffiliateReportService` - Summary/reporting
- `AttributionModel` - Multi-touch attribution models

**Events:**
- `AffiliateAttributed` - Fired on successful attribution
- `AffiliateConversionRecorded` - Fired on conversion creation

### Filament Package (`aiarmada/filament-affiliates`)

**Resources:**
- `AffiliateResource` - Full CRUD with conversions relation
- `AffiliateConversionResource` - List/view conversions
- `AffiliatePayoutResource` - List/view payouts with events

**Widgets:**
- `AffiliateStatsWidget` - Dashboard overview

**Services:**
- `AffiliateStatsAggregator` - Dashboard metrics
- `PayoutExportService` - Export functionality

**Integrations:**
- `CartBridge` - Deep links to FilamentCart
- `VoucherBridge` - Deep links to FilamentVouchers

---

## Notes

### December 5, 2025
- Initial progress assessment completed
- Phase 1 (Foundation) is fully implemented with comprehensive model/service layer
- Multi-touch attribution with 3 models (last-touch, first-touch, linear) working
- Basic MLM support (2-level) is functional via parent-child relationships
- Payout workflow with audit trail implemented
- Filament admin resources functional with stats widget
- Cart and Voucher bridge integrations active

### Next Priority Items
1. **AffiliateProgram model** - Enable tiered commission structures
2. **AffiliateTier model** - Progression system for affiliates
3. **Enhanced fraud detection** - GeoAnomalyDetector, FraudScoreAggregator
4. **Aggregation service** - Daily/hourly stat rollups
5. **Affiliate self-service portal** - Authentication and dashboard
