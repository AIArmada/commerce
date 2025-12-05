# Docs Package Vision - Executive Summary

> **Document:** 01 of 10  
> **Package:** `aiarmada/docs` + `aiarmada/filament-docs`  
> **Status:** Vision

---

## Package Overview

The **Docs** package provides document management capabilities for e-commerce operations including invoices, quotations, receipts, and other business documents.

### Current Capabilities

| Feature | Status | Description |
|---------|--------|-------------|
| Document Creation | ✅ Implemented | Create documents with items |
| Auto-Numbering | ⚠️ Basic | Simple incrementing numbers |
| PDF Generation | ✅ Implemented | Generate PDFs from templates |
| Template System | ✅ Implemented | Blade-based templates |
| Status Workflow | ✅ Implemented | Draft → Sent → Paid states |
| Email Delivery | ⚠️ Stub | Email methods exist but limited |

### Gap Analysis

| Gap | Impact | Priority |
|-----|--------|----------|
| No sequential numbering with prefixes | Compliance issues | High |
| Limited document types | Business constraints | High |
| No credit notes/refund docs | Accounting gaps | High |
| Basic email integration | Manual processes | Medium |
| No e-invoicing support | Future compliance | Medium |
| No document versioning | Audit trail gaps | Medium |

---

## Vision Pillars

### 1. Sequential Document Numbering
- **Prefix-based sequences** (INV-2024-00001)
- **Multi-series support** (per location, per year)
- **Gap-free sequences** for compliance
- **Reset rules** (yearly, monthly, never)

### 2. Extended Document Types
- **Invoices** with full accounting features
- **Quotations** with validity and conversion
- **Credit Notes** for refunds/adjustments
- **Delivery Notes** for shipments
- **Pro-forma Invoices** for pre-payment
- **Receipts** for payment confirmation

### 3. E-Invoicing & Compliance
- **MyInvois integration** (Malaysia LHDN)
- **Digital signatures** for authenticity
- **QR codes** for verification
- **Structured data** (UBL/PEPPOL format)

### 4. Email Integration
- **Automated sending** based on status
- **Template customization** per document type
- **Tracking** (opens, downloads)
- **Reminders** for overdue documents

### 5. Document Workflow
- **Approval workflows** for high-value docs
- **Version history** with diff tracking
- **Audit trail** for all changes
- **Archival policies** for retention

---

## Architecture Vision

```
┌──────────────────────────────────────────────────────────────┐
│                     DOCS ECOSYSTEM                            │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐    │
│  │  Sequence   │     │  Document   │     │  Template   │    │
│  │  Manager    │────▶│   Factory   │────▶│   Renderer  │    │
│  └─────────────┘     └─────────────┘     └─────────────┘    │
│         │                   │                   │            │
│         │                   ▼                   ▼            │
│         │            ┌─────────────┐     ┌─────────────┐    │
│         │            │  Workflow   │     │    PDF      │    │
│         │            │   Engine    │     │  Generator  │    │
│         │            └─────────────┘     └─────────────┘    │
│         │                   │                   │            │
│         ▼                   ▼                   ▼            │
│  ┌─────────────────────────────────────────────────────┐    │
│  │              DOCUMENT STORAGE                        │    │
│  │  ┌────────┐ ┌────────┐ ┌────────┐ ┌────────────┐   │    │
│  │  │Invoice │ │Quotation│ │Credit  │ │Delivery    │   │    │
│  │  │        │ │        │ │Note    │ │Note        │   │    │
│  │  └────────┘ └────────┘ └────────┘ └────────────┘   │    │
│  └─────────────────────────────────────────────────────┘    │
│                            │                                 │
│                            ▼                                 │
│               ┌───────────────────────┐                     │
│               │   E-Invoice Gateway   │                     │
│               │   (MyInvois/LHDN)     │                     │
│               └───────────────────────┘                     │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## Document Model Evolution

### From
```php
// Current simple structure
$document->number = 1001;
$document->type = 'invoice';
$document->status = 'draft';
```

### To
```php
// Enhanced document structure
$document->number = 'INV-2024-00001';
$document->sequence_id = $sequence->id;
$document->type = DocumentType::Invoice;
$document->status = DocumentStatus::Draft;
$document->parent_id = $creditNote->id; // For linked docs
$document->version = 1;
$document->is_e_invoiced = true;
$document->e_invoice_id = 'LHDN-XXXX';
```

---

## Integration Points

### Package Integrations

| Package | Integration |
|---------|-------------|
| `aiarmada/cart` | Generate invoice from cart |
| `aiarmada/chip` | Attach invoice to payment |
| `aiarmada/inventory` | Link delivery notes to stock |
| `aiarmada/jnt` | Attach shipping docs |
| `aiarmada/vouchers` | Show discounts on invoice |

### External Integrations

| System | Purpose |
|--------|---------|
| MyInvois (LHDN) | E-invoice submission |
| Email (SMTP) | Document delivery |
| Storage (S3) | PDF archival |
| Queue (Redis) | Async processing |

---

## Success Metrics

| Metric | Current | Target |
|--------|---------|--------|
| Document Types | 2 | 6 |
| Numbering Formats | 1 | 10+ |
| E-Invoice Support | No | Yes |
| Email Automation | Basic | Full |
| Version History | No | Yes |
| Audit Trail | Partial | Complete |

---

## Implementation Roadmap

| Phase | Focus | Duration |
|-------|-------|----------|
| 1 | Sequential Numbering | 1.5 weeks |
| 2 | Extended Document Types | 2 weeks |
| 3 | E-Invoicing | 2 weeks |
| 4 | Email Integration | 1 week |
| 5 | Workflow & Versioning | 1.5 weeks |
| 6 | Filament Enhancements | 2 weeks |
| 7 | Testing & Documentation | 2 weeks |

**Total Duration:** ~12 weeks

---

## Next Steps

1. Review [02-sequential-numbering.md](02-sequential-numbering.md) for numbering system
2. Explore [03-document-types.md](03-document-types.md) for type expansion
3. Understand [04-e-invoicing.md](04-e-invoicing.md) for compliance

---

## Navigation

**Next:** [02-sequential-numbering.md](02-sequential-numbering.md)
