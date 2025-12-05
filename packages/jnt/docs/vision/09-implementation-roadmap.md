# Implementation Roadmap

> **Document:** 9 of 9  
> **Package:** `aiarmada/shipping`  
> **Status:** Vision

---

## Overview

A phased approach to transform the JNT package into a **multi-carrier shipping platform** with rate shopping, intelligent routing, returns management, and comprehensive analytics.

---

## Timeline Summary

| Phase | Focus | Duration | Dependencies |
|-------|-------|----------|--------------|
| 1 | Multi-Carrier Abstraction | 3 weeks | None |
| 2 | Unified Data Model | 2 weeks | Phase 1 |
| 3 | Rate Shopping Engine | 3 weeks | Phase 2 |
| 4 | Carrier Selection Rules | 2 weeks | Phase 3 |
| 5 | Enhanced Tracking | 2 weeks | Phase 2 |
| 6 | Returns & RMA | 3 weeks | Phase 5 |
| 7 | Filament Dashboard | 2 weeks | All |
| 8 | Analytics & Reporting | 2 weeks | Phase 7 |

**Total Estimated Duration:** 19 weeks

---

## Phase 1: Multi-Carrier Abstraction (Weeks 1-3)

### Objectives
- Create carrier abstraction layer
- Extract J&T into adapter pattern
- Define unified DTOs and contracts

### Deliverables

**Contracts:**
- [ ] `CarrierContract` interface
- [ ] `CarrierCapabilities` value object
- [ ] `RateProviderContract` interface

**Core Services:**
- [ ] `ShippingManager` - carrier registry
- [ ] `CarrierFactory` - driver instantiation
- [ ] `CarrierResolver` - credential management

**Adapters:**
- [ ] `JntCarrier` adapter (refactor existing)
- [ ] `JntMapper` - DTO transformations

**DTOs:**
- [ ] `ShipmentData`, `AddressData`, `PackageData`
- [ ] `ShipmentResult`, `TrackingResult`
- [ ] `LabelResult`, `CancellationResult`

**Configuration:**
- [ ] Multi-carrier config structure
- [ ] Credential encryption

**Tests:**
- [ ] Carrier registration
- [ ] JNT adapter operations
- [ ] DTO transformations

### Acceptance Criteria
- JNT works through abstraction layer
- New carriers can be added via config
- Existing JNT functionality preserved

---

## Phase 2: Unified Data Model (Weeks 4-5)

### Objectives
- Create unified shipment tables
- Migrate JNT data
- Support multi-tenant credentials

### Deliverables

**Database:**
- [ ] `carriers` table with capabilities
- [ ] `carrier_credentials` table (encrypted)
- [ ] `shipments` unified table
- [ ] `shipment_packages` table
- [ ] `tracking_events` unified table

**Models:**
- [ ] `Carrier` model
- [ ] `CarrierCredential` model (encrypted)
- [ ] `Shipment` model
- [ ] `TrackingEvent` model

**Migrations:**
- [ ] Create new tables
- [ ] Data migration from JNT tables
- [ ] Seed carrier registry

**Tests:**
- [ ] Model relationships
- [ ] Credential encryption/decryption
- [ ] Data migration accuracy

### Acceptance Criteria
- All JNT data migrated
- Credentials encrypted at rest
- Models support multi-tenant

---

## Phase 3: Rate Shopping Engine (Weeks 6-8)

### Objectives
- Build rate calculation system
- Implement zone mapping
- Create rate comparison

### Deliverables

**Database:**
- [ ] `carrier_rate_cards` table
- [ ] `shipping_zones` table
- [ ] `zone_postcodes` table

**Services:**
- [ ] `RateCalculatorService`
- [ ] `ZoneMapper`
- [ ] `SurchargeCalculator`
- [ ] `RateCardService`

**DTOs:**
- [ ] `RateRequest`, `RateQuote`
- [ ] `RateCollection`, `RateComparisonResult`

**API:**
- [ ] Rate comparison endpoint
- [ ] Cache rate queries

**Tests:**
- [ ] Zone calculation
- [ ] Rate card parsing
- [ ] Surcharge application
- [ ] Multi-carrier comparison

### Acceptance Criteria
- Rates from multiple carriers returned
- Zone-based pricing works
- Surcharges calculated correctly

---

## Phase 4: Carrier Selection Rules (Weeks 9-10)

### Objectives
- Build rules engine
- Implement performance scoring
- Enable automatic routing

### Deliverables

**Database:**
- [ ] `shipping_rules` table

**Models:**
- [ ] `ShippingRule` model

**Enums:**
- [ ] `RuleType` enum
- [ ] `ConditionType` enum

**Services:**
- [ ] `CarrierSelectionEngine`
- [ ] `RuleEvaluator`
- [ ] `PerformanceScorer`

**API:**
- [ ] Auto-select carrier endpoint
- [ ] Selection reasoning

**Tests:**
- [ ] Rule evaluation
- [ ] Priority ordering
- [ ] Performance scoring
- [ ] Fallback selection

### Acceptance Criteria
- Rules correctly filter/prefer carriers
- Performance data influences selection
- Reasoning provided for selection

---

## Phase 5: Enhanced Tracking (Weeks 11-12)

### Objectives
- Normalize tracking statuses
- Build delivery estimation
- Implement notifications

### Deliverables

**Enums:**
- [ ] `TrackingStatus` unified enum
- [ ] `TrackingStage` enum
- [ ] `NotificationChannel` enum

**Services:**
- [ ] `TrackingNormalizer`
- [ ] `DeliveryEstimationService`
- [ ] `ShipmentNotificationService`
- [ ] `WebhookProcessingService`

**Models:**
- [ ] Update `TrackingEvent` with normalized status

**Notifications:**
- [ ] Email templates
- [ ] SMS templates
- [ ] Notification preferences

**Tests:**
- [ ] Status normalization per carrier
- [ ] ETA calculation
- [ ] Notification dispatch

### Acceptance Criteria
- All carrier statuses normalized
- ETAs calculated and updated
- Notifications sent on key events

---

## Phase 6: Returns & RMA (Weeks 13-15)

### Objectives
- Build RMA workflow
- Enable return label generation
- Create customer portal

### Deliverables

**Database:**
- [ ] `return_requests` table
- [ ] `return_request_items` table

**Models:**
- [ ] `ReturnRequest` model
- [ ] `ReturnRequestItem` model

**Enums:**
- [ ] `ReturnStatus` enum
- [ ] `ReturnReason` enum
- [ ] `ResolutionType` enum

**Services:**
- [ ] `ReturnService`
- [ ] `ReturnPolicyService`
- [ ] `CustomerReturnPortal`

**Events:**
- [ ] `ReturnRequested`, `ReturnApproved`
- [ ] `ReturnReceived`, `ReturnResolved`

**Tests:**
- [ ] Return eligibility
- [ ] Label generation
- [ ] Status transitions
- [ ] Refund calculation

### Acceptance Criteria
- Returns can be created and processed
- Return labels generated automatically
- Refund/exchange workflows work

---

## Phase 7: Filament Dashboard (Weeks 16-17)

### Objectives
- Build shipping dashboard
- Create management resources
- Add rate calculator UI

### Deliverables

**Widgets:**
- [ ] `ShippingOverviewWidget`
- [ ] `CarrierPerformanceWidget`
- [ ] `ExceptionAlertsWidget`
- [ ] `PendingReturnsWidget`

**Resources:**
- [ ] `ShipmentResource` with tracking
- [ ] `CarrierResource`
- [ ] `ReturnRequestResource`
- [ ] `ShippingRuleResource`

**Pages:**
- [ ] `RateComparisonPage`
- [ ] `ShipmentCreateWizard`

**Actions:**
- [ ] Bulk label printing
- [ ] Manual tracking update
- [ ] Return approval flow

**Tests:**
- [ ] Widget data accuracy
- [ ] Resource CRUD operations
- [ ] Action workflows

### Acceptance Criteria
- Dashboard provides operations overview
- All entities manageable in Filament
- Rate comparison works in UI

---

## Phase 8: Analytics & Reporting (Weeks 18-19)

### Objectives
- Build carrier metrics
- Create performance reports
- Enable cost analysis

### Deliverables

**Database:**
- [ ] `carrier_metrics` table
- [ ] `transit_history` table

**Services:**
- [ ] `CarrierMetricsService`
- [ ] `PerformanceReportService`
- [ ] `CostAnalysisService`

**Commands:**
- [ ] `shipping:calculate-metrics` - daily metrics
- [ ] `shipping:generate-report` - on-demand

**Reports:**
- [ ] Carrier Performance Report
- [ ] Delivery Time Analysis
- [ ] Cost per Shipment Report
- [ ] Exception Analysis

**Tests:**
- [ ] Metric calculation accuracy
- [ ] Report generation
- [ ] Historical comparisons

### Acceptance Criteria
- Carrier metrics tracked daily
- Reports exportable to Excel
- Trends visible over time

---

## Risk Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Carrier API changes | High | Abstract all carrier calls |
| Data migration loss | High | Run parallel before cutover |
| Rate calculation errors | Medium | Extensive testing, fallback to carrier |
| Webhook reliability | Medium | Queue processing, retry logic |

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Carrier integration time | < 2 days |
| Rate accuracy | 99% |
| Webhook processing success | 99.9% |
| Label generation time | < 3 seconds |
| Dashboard load time | < 2 seconds |

---

## Post-Launch Roadmap

### Additional Carriers
- Pos Laju (Week 20)
- DHL eCommerce (Week 21)
- GDex (Week 22)
- Ninja Van (Week 23)

### Advanced Features
- Address validation API (Week 24)
- Pickup scheduling (Week 25)
- Insurance claims workflow (Week 26)
- Multi-parcel shipments (Week 27)

---

## Navigation

**Previous:** [08-filament-enhancements.md](08-filament-enhancements.md)  
**Next:** [PROGRESS.md](PROGRESS.md)
