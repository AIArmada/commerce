# Database Evolution

> **Document:** 07 of 10  
> **Package:** `aiarmada/docs`  
> **Status:** Vision

---

## Overview

Evolve the Docs database schema to support **sequential numbering, extended document types, e-invoicing, email tracking, workflows, and versioning**.

---

## Current Schema Analysis

### Existing Tables

| Table | Purpose | Status |
|-------|---------|--------|
| `doc_documents` | Core documents | ⚠️ Extend |
| `doc_items` | Document line items | ✅ Stable |

### Current doc_documents Structure

```php
// Existing columns (estimated)
$table->uuid('id')->primary();
$table->string('number');
$table->string('type');
$table->string('status');
$table->string('customer_name');
$table->string('customer_email')->nullable();
$table->date('issue_date');
$table->date('due_date')->nullable();
$table->bigInteger('total_minor');
$table->string('currency', 3);
$table->text('notes')->nullable();
$table->json('metadata')->nullable();
$table->timestamps();
```

---

## Schema Evolution Plan

### Phase 1: Sequential Numbering

#### New Table: doc_sequences

```php
Schema::create('doc_sequences', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('code')->unique();
    $table->string('document_type');
    $table->string('prefix')->nullable();
    $table->string('suffix')->nullable();
    $table->unsignedInteger('padding')->default(5);
    $table->string('reset_frequency')->default('yearly');
    $table->unsignedInteger('current_number')->default(0);
    $table->timestamp('last_reset_at')->nullable();
    $table->json('format_tokens')->nullable();
    $table->string('scope_type')->nullable();
    $table->string('scope_id')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index(['document_type', 'is_default']);
    $table->index(['scope_type', 'scope_id']);
});
```

#### New Table: doc_sequence_numbers

```php
Schema::create('doc_sequence_numbers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('sequence_id');
    $table->unsignedInteger('number');
    $table->string('formatted_number')->unique();
    $table->foreignUuid('document_id')->nullable();
    $table->string('status')->default('reserved');
    $table->timestamp('used_at')->nullable();
    $table->timestamp('voided_at')->nullable();
    $table->string('void_reason')->nullable();
    $table->timestamps();
    
    $table->index('sequence_id');
    $table->index('document_id');
    $table->index('status');
});
```

---

### Phase 2: Extended Documents

#### Modify doc_documents

```php
Schema::table('doc_documents', function (Blueprint $table) {
    // Relationships
    $table->foreignUuid('sequence_id')->nullable()->after('id');
    $table->foreignUuid('parent_id')->nullable()->after('number');
    $table->foreignUuid('converted_from_id')->nullable()->after('parent_id');
    
    // Customer details
    $table->json('customer_address')->nullable()->after('customer_email');
    
    // Extended dates
    $table->date('valid_until')->nullable()->after('due_date');
    
    // Financial tracking
    $table->bigInteger('subtotal_minor')->default(0)->after('total_minor');
    $table->bigInteger('discount_minor')->default(0)->after('subtotal_minor');
    $table->bigInteger('tax_minor')->default(0)->after('discount_minor');
    $table->bigInteger('paid_minor')->default(0)->after('tax_minor');
    
    // Terms
    $table->text('terms')->nullable()->after('notes');
    
    // Versioning
    $table->unsignedInteger('version')->default(1)->after('metadata');
    
    // E-invoicing
    $table->boolean('is_e_invoiced')->default(false)->after('version');
    $table->string('e_invoice_id')->nullable()->after('is_e_invoiced');
    
    // Indexes
    $table->index('sequence_id');
    $table->index('parent_id');
    $table->index('converted_from_id');
    $table->index(['type', 'status']);
    $table->index('is_e_invoiced');
});
```

#### New Table: doc_payments

```php
Schema::create('doc_payments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id');
    $table->bigInteger('amount_minor');
    $table->string('payment_method')->nullable();
    $table->string('reference')->nullable();
    $table->text('notes')->nullable();
    $table->timestamp('paid_at');
    $table->timestamps();
    
    $table->index('document_id');
});
```

---

### Phase 3: E-Invoicing

#### New Table: doc_einvoice_submissions

```php
Schema::create('doc_einvoice_submissions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id');
    $table->string('submission_uid');
    $table->string('long_id')->nullable();
    $table->string('internal_id')->nullable();
    $table->string('status');
    $table->string('uuid')->nullable()->unique();
    $table->string('qr_url')->nullable();
    $table->json('validation_results')->nullable();
    $table->text('rejection_reason')->nullable();
    $table->timestamp('submitted_at');
    $table->timestamp('validated_at')->nullable();
    $table->timestamp('cancelled_at')->nullable();
    $table->text('cancel_reason')->nullable();
    $table->timestamps();
    
    $table->index('document_id');
    $table->index('status');
    $table->index('submission_uid');
});
```

---

### Phase 4: Email Integration

#### New Table: doc_email_templates

```php
Schema::create('doc_email_templates', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('code')->unique();
    $table->string('document_type')->nullable();
    $table->string('subject_template');
    $table->text('body_template');
    $table->json('variables')->nullable();
    $table->boolean('is_default')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index(['document_type', 'is_default']);
});
```

#### New Table: doc_emails

```php
Schema::create('doc_emails', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id');
    $table->string('recipient');
    $table->string('subject');
    $table->string('type')->default('send');
    $table->string('status')->default('queued');
    $table->string('tracking_id')->unique();
    $table->unsignedInteger('open_count')->default(0);
    $table->timestamp('sent_at')->nullable();
    $table->timestamp('delivered_at')->nullable();
    $table->timestamp('first_opened_at')->nullable();
    $table->timestamp('last_opened_at')->nullable();
    $table->timestamp('bounced_at')->nullable();
    $table->text('bounce_reason')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->index('document_id');
    $table->index('status');
    $table->index('tracking_id');
});
```

---

### Phase 5: Workflow & Versioning

#### New Table: doc_workflow_configs

```php
Schema::create('doc_workflow_configs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('name');
    $table->string('document_type')->nullable();
    $table->bigInteger('threshold_minor')->default(0);
    $table->json('approval_levels');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index('document_type');
});
```

#### New Table: doc_approvals

```php
Schema::create('doc_approvals', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id');
    $table->foreignUuid('workflow_config_id');
    $table->unsignedInteger('level');
    $table->string('status')->default('pending');
    $table->foreignUuid('approver_id')->nullable();
    $table->text('comment')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('rejected_at')->nullable();
    $table->timestamps();
    
    $table->index('document_id');
    $table->index(['document_id', 'level', 'status']);
});
```

#### New Table: doc_versions

```php
Schema::create('doc_versions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id');
    $table->unsignedInteger('version');
    $table->json('snapshot');
    $table->json('changes');
    $table->foreignUuid('changed_by')->nullable();
    $table->string('change_reason')->nullable();
    $table->timestamps();
    
    $table->index('document_id');
    $table->unique(['document_id', 'version']);
});
```

#### New Table: doc_audit_logs

```php
Schema::create('doc_audit_logs', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id');
    $table->string('action');
    $table->json('old_values')->nullable();
    $table->json('new_values')->nullable();
    $table->foreignUuid('user_id')->nullable();
    $table->string('ip_address')->nullable();
    $table->string('user_agent')->nullable();
    $table->timestamps();
    
    $table->index('document_id');
    $table->index('action');
    $table->index('created_at');
});
```

---

## Indexing Strategy

### Performance Indexes

```php
// Document queries
$table->index(['type', 'status', 'issue_date']);
$table->index(['customer_email', 'status']);
$table->index('due_date');

// Sequence queries
$table->index(['document_type', 'is_active', 'is_default']);

// E-invoice queries
$table->index(['status', 'submitted_at']);

// Email queries
$table->index(['document_id', 'type']);
$table->index(['status', 'sent_at']);
```

---

## Migration Strategy

### Rollout Order

```
Phase 1: Sequences (Week 1)
├── doc_sequences
└── doc_sequence_numbers

Phase 2: Document Extensions (Week 2)
├── Alter doc_documents
└── doc_payments

Phase 3: E-Invoicing (Week 3)
└── doc_einvoice_submissions

Phase 4: Email (Week 4)
├── doc_email_templates
└── doc_emails

Phase 5: Workflow (Week 5)
├── doc_workflow_configs
├── doc_approvals
├── doc_versions
└── doc_audit_logs
```

---

## Schema Summary

### New Tables (9)

| Table | Columns | Indexes | Purpose |
|-------|---------|---------|---------|
| `doc_sequences` | 14 | 3 | Numbering definitions |
| `doc_sequence_numbers` | 9 | 3 | Number tracking |
| `doc_payments` | 8 | 1 | Payment records |
| `doc_einvoice_submissions` | 14 | 3 | E-invoice tracking |
| `doc_email_templates` | 9 | 1 | Email templates |
| `doc_emails` | 15 | 3 | Email tracking |
| `doc_workflow_configs` | 6 | 1 | Workflow definitions |
| `doc_approvals` | 10 | 2 | Approval records |
| `doc_versions` | 7 | 2 | Version snapshots |
| `doc_audit_logs` | 9 | 3 | Audit trail |

### Modified Tables (1)

| Table | New Columns | New Indexes |
|-------|-------------|-------------|
| `doc_documents` | 14 | 5 |

---

## Data Integrity

### Application-Level Cascades

```php
// Document model
protected static function booted(): void
{
    static::deleting(function (Document $document): void {
        $document->items()->delete();
        $document->payments()->delete();
        $document->versions()->delete();
        $document->auditLogs()->delete();
        $document->approvals()->delete();
        $document->emails()->delete();
        $document->eInvoiceSubmissions()->delete();
    });
}

// DocSequence model
protected static function booted(): void
{
    static::deleting(function (DocSequence $sequence): void {
        $sequence->numbers()->delete();
    });
}
```

---

## Navigation

**Previous:** [06-workflow-versioning.md](06-workflow-versioning.md)  
**Next:** [08-filament-enhancements.md](08-filament-enhancements.md)
