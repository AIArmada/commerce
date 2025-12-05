# Implementation Roadmap

> **Document:** 09 of 10  
> **Package:** `aiarmada/docs` + `aiarmada/filament-docs`  
> **Status:** Vision

---

## Overview

A **12-week phased implementation** transforming the docs package into a comprehensive document management platform with sequential numbering, extended document types, e-invoicing, and workflow automation.

---

## Phase Summary

| Phase | Focus | Duration | Deliverables |
|-------|-------|----------|--------------|
| 1 | Sequential Numbering | 2 weeks | DocSequence, SequenceManager, gap-free sequences |
| 2 | Document Types | 2 weeks | 6 document types, status workflow, conversion |
| 3 | Email Integration | 2 weeks | Templates, tracking, automated reminders |
| 4 | Workflow & Versioning | 2 weeks | Approval chains, version control, audit logs |
| 5 | E-Invoice Integration | 2 weeks | MyInvois API, UBL format, digital signing |
| 6 | Filament & Polish | 2 weeks | Dashboard, resources, analytics, testing |

---

## Phase 1: Sequential Numbering (Weeks 1-2)

### Week 1: Core Infrastructure

**Day 1-2: Database & Models**
```
Tasks:
├── Create doc_sequences migration
├── Create doc_sequence_numbers migration
├── Create DocSequence model
├── Create SequenceNumber model
└── Add HasUuids, getTable() patterns
```

**Day 3-4: Sequence Manager**
```
Tasks:
├── Build SequenceManager service
├── Implement format token parsing
├── Add configurable reset frequencies
├── Build gap-free reservation system
└── Add atomic number generation
```

**Day 5: Testing**
```
Tasks:
├── Unit tests for format tokens
├── Concurrent generation tests
├── Reset frequency tests
└── Gap detection tests
```

### Week 2: Integration & Filament

**Day 1-2: Package Integration**
```
Tasks:
├── Integrate with Document creation
├── Add sequence assignment events
├── Build rollback on creation failure
└── Add number preview helper
```

**Day 3-4: Filament Resource**
```
Tasks:
├── Create SequenceResource
├── Add format preview component
├── Build number history view
└── Add bulk sequence operations
```

**Day 5: Documentation**
```
Tasks:
├── API documentation
├── Configuration guide
├── Migration guide for existing documents
└── Format token reference
```

**Phase 1 Deliverables:**
- [ ] DocSequence model with format tokens
- [ ] SequenceManager with gap-free generation
- [ ] Filament SequenceResource
- [ ] 85%+ test coverage

---

## Phase 2: Document Types (Weeks 3-4)

### Week 3: Type System

**Day 1-2: Enums & Status**
```
Tasks:
├── Create DocumentType enum (6 types)
├── Create DocumentStatus enum
├── Add type-specific behaviors
├── Build status transitions
└── Add validation per type
```

**Day 3-4: Type Services**
```
Tasks:
├── Build DocumentFactory
├── Create type-specific services
│   ├── InvoiceService
│   ├── QuotationService
│   ├── CreditNoteService
│   └── DeliveryNoteService
├── Implement conversion logic
└── Add line item calculations
```

**Day 5: Payment Tracking**
```
Tasks:
├── Create doc_payments migration
├── Build PaymentService
├── Add partial payment support
└── Implement payment allocation
```

### Week 4: Integration

**Day 1-2: Filament Updates**
```
Tasks:
├── Update DocumentResource for types
├── Add type-specific form sections
├── Build conversion actions
└── Add payment recording modal
```

**Day 3-4: Conversion Features**
```
Tasks:
├── Quotation → Invoice conversion
├── Invoice → Credit Note conversion
├── Add linked document references
└── Build conversion history
```

**Day 5: Testing**
```
Tasks:
├── Type behavior tests
├── Conversion flow tests
├── Payment calculation tests
└── Status transition tests
```

**Phase 2 Deliverables:**
- [ ] 6 document types operational
- [ ] Type conversion functionality
- [ ] Payment tracking system
- [ ] Updated Filament resources

---

## Phase 3: Email Integration (Weeks 5-6)

### Week 5: Email System

**Day 1-2: Templates**
```
Tasks:
├── Create doc_email_templates migration
├── Build EmailTemplate model
├── Implement template rendering
├── Add Blade template support
└── Create default templates per type
```

**Day 3-4: Sending Service**
```
Tasks:
├── Build DocumentEmailService
├── Integrate with Laravel Mail
├── Add PDF attachment generation
├── Implement batch sending
└── Add queue support
```

**Day 5: Tracking**
```
Tasks:
├── Create doc_emails migration
├── Build open/click tracking
├── Add tracking pixel
├── Build delivery webhooks
└── Store email events
```

### Week 6: Automation & Filament

**Day 1-2: Automated Reminders**
```
Tasks:
├── Build overdue detection scheduler
├── Create reminder queue jobs
├── Implement escalation levels
├── Add reminder suppression
└── Build unsubscribe handling
```

**Day 3-4: Filament Components**
```
Tasks:
├── Create EmailTemplateResource
├── Build email log viewer
├── Add send email action
├── Create EmailLogRelationManager
└── Build email preview modal
```

**Day 5: Testing**
```
Tasks:
├── Template rendering tests
├── Email sending tests
├── Tracking accuracy tests
└── Reminder scheduling tests
```

**Phase 3 Deliverables:**
- [ ] Email template system
- [ ] Tracked email sending
- [ ] Automated reminders
- [ ] Email management in Filament

---

## Phase 4: Workflow & Versioning (Weeks 7-8)

### Week 7: Approval Workflows

**Day 1-2: Workflow Configuration**
```
Tasks:
├── Create doc_workflow_configs migration
├── Build WorkflowConfig model
├── Implement approval level system
├── Add threshold-based routing
└── Build workflow builder UI
```

**Day 3-4: Approval Engine**
```
Tasks:
├── Create doc_approvals migration
├── Build ApprovalService
├── Implement multi-level approval
├── Add parallel approval support
├── Build approval notifications
└── Add delegation support
```

**Day 5: Status Integration**
```
Tasks:
├── Integrate with DocumentStatus
├── Add pending approval state
├── Implement approval → finalize flow
└── Build rejection handling
```

### Week 8: Version Control

**Day 1-2: Versioning System**
```
Tasks:
├── Create doc_versions migration
├── Build VersioningService
├── Implement diff calculation
├── Add change tracking
└── Build version restore
```

**Day 3-4: Audit Trail**
```
Tasks:
├── Create doc_audit_logs migration
├── Build comprehensive logging
├── Add user action tracking
├── Implement retention policies
└── Build audit report
```

**Day 5: Filament Integration**
```
Tasks:
├── Create PendingApprovalsPage
├── Build VersionsRelationManager
├── Add approval actions
├── Create AuditLogResource
└── Build approval dashboard widget
```

**Phase 4 Deliverables:**
- [ ] Multi-level approval workflows
- [ ] Document versioning with restore
- [ ] Complete audit trail
- [ ] Approval management in Filament

---

## Phase 5: E-Invoice Integration (Weeks 9-10)

### Week 9: MyInvois Core

**Day 1-2: API Client**
```
Tasks:
├── Build MyInvoisClient
├── Implement OAuth2 authentication
├── Add API error handling
├── Build response DTOs
└── Add sandbox/production modes
```

**Day 3-4: UBL Generation**
```
Tasks:
├── Build UblFormatter service
├── Implement UBL 2.1 structure
├── Add Malaysian extensions
├── Build line item mapping
└── Add tax calculation formatting
```

**Day 5: Submission**
```
Tasks:
├── Create doc_einvoice_submissions migration
├── Build submission service
├── Implement status polling
├── Add submission retry logic
└── Build validation pre-check
```

### Week 10: E-Invoice Features

**Day 1-2: Digital Signing**
```
Tasks:
├── Integrate P12 certificate handling
├── Build XML signing service
├── Add signature validation
└── Implement certificate management
```

**Day 3-4: QR & Verification**
```
Tasks:
├── Build QR code generator
├── Create verification URL handler
├── Add LHDN verification check
├── Build verification page
└── Store QR codes
```

**Day 5: Filament & Reporting**
```
Tasks:
├── Add e-invoice submission actions
├── Build submission status display
├── Create E-Invoice Report page
├── Add compliance monitoring
└── Build error resolution UI
```

**Phase 5 Deliverables:**
- [ ] MyInvois API integration
- [ ] UBL 2.1 format generation
- [ ] Digital signing
- [ ] QR code verification
- [ ] Filament e-invoice management

---

## Phase 6: Filament & Polish (Weeks 11-12)

### Week 11: Dashboard & Analytics

**Day 1-2: Dashboard Widgets**
```
Tasks:
├── Create DocumentStatsWidget
├── Build QuickActionsWidget
├── Add RecentDocumentsWidget
├── Create StatusBreakdownChart
└── Build RevenueChartWidget
```

**Day 3-4: Reporting**
```
Tasks:
├── Create AgingReportPage
├── Build revenue analytics
├── Add document metrics
├── Create export functionality
└── Build scheduled reports
```

**Day 5: Advanced Features**
```
Tasks:
├── Implement bulk operations
├── Add keyboard shortcuts
├── Build advanced filters
├── Add saved filter presets
└── Create custom views
```

### Week 12: Testing & Documentation

**Day 1-2: Comprehensive Testing**
```
Tasks:
├── Complete unit test coverage
├── Add integration tests
├── Build Filament page tests
├── Add E2E scenarios
└── Performance benchmarks
```

**Day 3-4: Documentation**
```
Tasks:
├── Complete API documentation
├── Write user guides
├── Create admin documentation
├── Build configuration reference
└── Add troubleshooting guides
```

**Day 5: Final Polish**
```
Tasks:
├── Performance optimization
├── Code cleanup
├── PHPStan level 6 compliance
├── Final review
└── Release preparation
```

**Phase 6 Deliverables:**
- [ ] Complete dashboard
- [ ] Analytics & reporting
- [ ] 85%+ test coverage
- [ ] Comprehensive documentation

---

## Risk Management

| Risk | Mitigation |
|------|------------|
| MyInvois API changes | Abstraction layer, version detection |
| Complex workflow requirements | Modular workflow engine, config-driven |
| Migration data loss | Backup strategy, rollback procedures |
| Performance at scale | Indexing, query optimization, caching |
| Email deliverability | Provider integration, reputation monitoring |

---

## Success Metrics

### Technical Metrics
- 85%+ test coverage
- PHPStan level 6 compliant
- <100ms document generation
- <500ms e-invoice submission
- Zero sequence number duplicates

### Business Metrics
- Support 6 document types
- Handle 10,000+ documents/month
- E-invoice compliance 100%
- Email delivery rate 98%+
- Approval workflow SLA <24h

---

## Dependencies

```
External:
├── MyInvois API credentials (sandbox & production)
├── P12 digital certificate
├── Email service provider
└── PDF generation service

Package:
├── aiarmada/commerce-support (money formatting)
├── Laravel Queue (for async)
├── Laravel Mail
└── DOMPDF/Snappy for PDFs
```

---

## Migration Strategy

### Existing Documents
1. Assign document types based on current data
2. Generate sequence numbers for existing
3. Migrate to new status workflow
4. Preserve all original timestamps

### Configuration
1. Deploy with feature flags
2. Gradual rollout per document type
3. E-invoice opt-in initially
4. Full activation after validation

---

## Navigation

**Previous:** [08-filament-enhancements.md](08-filament-enhancements.md)  
**Next:** [PROGRESS.md](PROGRESS.md)
