---
title: Extended Document Types
---

# Extended Document Types

> **Document:** 03 of 10  
> **Package:** `aiarmada/docs`  
> **Status:** Vision

---

## Overview

Expand the document type system to support a **full range of business documents** including invoices, quotations, credit notes, delivery notes, pro-forma invoices, and receipts.

---

## Document Type Hierarchy

```
┌──────────────────────────────────────────────────────────────┐
│                    DOCUMENT TYPES                             │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────────────────────────────────────────────┐    │
│  │                    BASE DOCUMENT                     │    │
│  │  • Common fields (number, date, customer, items)    │    │
│  │  • Status workflow                                   │    │
│  │  • PDF generation                                    │    │
│  └─────────────────────────────────────────────────────┘    │
│                            │                                  │
│          ┌─────────────────┼─────────────────┐               │
│          │                 │                 │               │
│          ▼                 ▼                 ▼               │
│  ┌───────────────┐ ┌───────────────┐ ┌───────────────┐      │
│  │   INVOICE     │ │  QUOTATION    │ │ CREDIT NOTE   │      │
│  │               │ │               │ │               │      │
│  │ • Due date    │ │ • Valid until │ │ • Reference   │      │
│  │ • Payment     │ │ • Conversion  │ │ • Reason      │      │
│  │ • Tax calc    │ │ • Accept/Dec  │ │ • Partial     │      │
│  └───────────────┘ └───────────────┘ └───────────────┘      │
│                                                               │
│          ┌─────────────────┼─────────────────┐               │
│          │                 │                 │               │
│          ▼                 ▼                 ▼               │
│  ┌───────────────┐ ┌───────────────┐ ┌───────────────┐      │
│  │ DELIVERY NOTE │ │  PRO-FORMA    │ │   RECEIPT     │      │
│  │               │ │               │ │               │      │
│  │ • Shipping    │ │ • Prepayment  │ │ • Payment ref │      │
│  │ • Tracking    │ │ • Valid until │ │ • Method      │      │
│  │ • Receiver    │ │ • Conversion  │ │ • Amount      │      │
│  └───────────────┘ └───────────────┘ └───────────────┘      │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## DocumentType Enum

```php
enum DocumentType: string
{
    case Invoice = 'invoice';
    case Quotation = 'quotation';
    case CreditNote = 'credit_note';
    case DeliveryNote = 'delivery_note';
    case ProformaInvoice = 'proforma_invoice';
    case Receipt = 'receipt';
    
    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::Quotation => 'Quotation',
            self::CreditNote => 'Credit Note',
            self::DeliveryNote => 'Delivery Note',
            self::ProformaInvoice => 'Pro-forma Invoice',
            self::Receipt => 'Receipt',
        };
    }
    
    public function prefix(): string
    {
        return match ($this) {
            self::Invoice => 'INV',
            self::Quotation => 'QUO',
            self::CreditNote => 'CN',
            self::DeliveryNote => 'DN',
            self::ProformaInvoice => 'PI',
            self::Receipt => 'RCP',
        };
    }
    
    public function icon(): string
    {
        return match ($this) {
            self::Invoice => 'heroicon-o-document-text',
            self::Quotation => 'heroicon-o-document-duplicate',
            self::CreditNote => 'heroicon-o-arrow-uturn-left',
            self::DeliveryNote => 'heroicon-o-truck',
            self::ProformaInvoice => 'heroicon-o-document',
            self::Receipt => 'heroicon-o-receipt-percent',
        };
    }
    
    public function statuses(): array
    {
        return match ($this) {
            self::Invoice => [
                DocumentStatus::Draft,
                DocumentStatus::Sent,
                DocumentStatus::Viewed,
                DocumentStatus::Overdue,
                DocumentStatus::PartiallyPaid,
                DocumentStatus::Paid,
                DocumentStatus::Voided,
            ],
            self::Quotation => [
                DocumentStatus::Draft,
                DocumentStatus::Sent,
                DocumentStatus::Viewed,
                DocumentStatus::Expired,
                DocumentStatus::Accepted,
                DocumentStatus::Declined,
                DocumentStatus::Converted,
            ],
            self::CreditNote => [
                DocumentStatus::Draft,
                DocumentStatus::Issued,
                DocumentStatus::Applied,
                DocumentStatus::Voided,
            ],
            self::DeliveryNote => [
                DocumentStatus::Draft,
                DocumentStatus::Pending,
                DocumentStatus::Shipped,
                DocumentStatus::Delivered,
                DocumentStatus::Returned,
            ],
            self::ProformaInvoice => [
                DocumentStatus::Draft,
                DocumentStatus::Sent,
                DocumentStatus::Expired,
                DocumentStatus::Converted,
                DocumentStatus::Voided,
            ],
            self::Receipt => [
                DocumentStatus::Issued,
                DocumentStatus::Voided,
            ],
        };
    }
    
    public function canConvertTo(): array
    {
        return match ($this) {
            self::Quotation => [self::Invoice, self::ProformaInvoice],
            self::ProformaInvoice => [self::Invoice],
            self::Invoice => [self::CreditNote, self::Receipt, self::DeliveryNote],
            default => [],
        };
    }
    
    public function requiresPayment(): bool
    {
        return in_array($this, [
            self::Invoice,
            self::ProformaInvoice,
        ]);
    }
    
    public function hasDueDate(): bool
    {
        return in_array($this, [
            self::Invoice,
        ]);
    }
    
    public function hasValidityPeriod(): bool
    {
        return in_array($this, [
            self::Quotation,
            self::ProformaInvoice,
        ]);
    }
}
```

---

## DocumentStatus Enum

```php
enum DocumentStatus: string
{
    // Common statuses
    case Draft = 'draft';
    case Voided = 'voided';
    
    // Invoice statuses
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Overdue = 'overdue';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    
    // Quotation statuses
    case Expired = 'expired';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Converted = 'converted';
    
    // Credit Note statuses
    case Issued = 'issued';
    case Applied = 'applied';
    
    // Delivery Note statuses
    case Pending = 'pending';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Returned = 'returned';
    
    public function label(): string
    {
        return Str::headline($this->value);
    }
    
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent, self::Pending => 'info',
            self::Viewed => 'primary',
            self::Overdue, self::Expired => 'danger',
            self::PartiallyPaid => 'warning',
            self::Paid, self::Delivered, self::Applied => 'success',
            self::Accepted, self::Converted => 'success',
            self::Declined, self::Returned, self::Voided => 'danger',
            self::Shipped, self::Issued => 'info',
        };
    }
    
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }
    
    public function isFinal(): bool
    {
        return in_array($this, [
            self::Paid,
            self::Voided,
            self::Converted,
            self::Delivered,
            self::Applied,
            self::Declined,
        ]);
    }
}
```

---

## Enhanced Document Model

```php
/**
 * @property string $id
 * @property DocumentType $type
 * @property string $number
 * @property DocumentStatus $status
 * @property string|null $parent_id
 * @property string|null $converted_from_id
 * @property string $customer_name
 * @property string|null $customer_email
 * @property array|null $customer_address
 * @property Carbon $issue_date
 * @property Carbon|null $due_date
 * @property Carbon|null $valid_until
 * @property int $subtotal_minor
 * @property int $discount_minor
 * @property int $tax_minor
 * @property int $total_minor
 * @property string $currency
 * @property int $paid_minor
 * @property string|null $notes
 * @property string|null $terms
 * @property array|null $metadata
 * @property int $version
 * @property bool $is_e_invoiced
 * @property string|null $e_invoice_id
 */
class Document extends Model
{
    use HasUuids;
    
    protected $casts = [
        'type' => DocumentType::class,
        'status' => DocumentStatus::class,
        'customer_address' => 'array',
        'issue_date' => 'date',
        'due_date' => 'date',
        'valid_until' => 'date',
        'metadata' => 'array',
        'is_e_invoiced' => 'boolean',
    ];
    
    public function getTable(): string
    {
        return config('docs.database.tables.documents')
            ?? config('docs.database.table_prefix', 'doc_') . 'documents';
    }
    
    /**
     * @return HasMany<DocumentItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class, 'document_id');
    }
    
    /**
     * @return BelongsTo<Document, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'parent_id');
    }
    
    /**
     * @return HasMany<Document, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Document::class, 'parent_id');
    }
    
    /**
     * @return BelongsTo<Document, $this>
     */
    public function convertedFrom(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'converted_from_id');
    }
    
    /**
     * @return HasMany<DocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id');
    }
    
    public function getBalanceMinor(): int
    {
        return $this->total_minor - $this->paid_minor;
    }
    
    public function isPaid(): bool
    {
        return $this->paid_minor >= $this->total_minor;
    }
    
    public function isOverdue(): bool
    {
        return $this->due_date 
            && $this->due_date->isPast() 
            && ! $this->isPaid();
    }
    
    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }
    
    public function canConvertTo(DocumentType $type): bool
    {
        return in_array($type, $this->type->canConvertTo());
    }
    
    protected static function booted(): void
    {
        static::deleting(function (Document $document): void {
            $document->items()->delete();
            $document->versions()->delete();
        });
    }
}
```

---

## Document Factory

### DocumentFactory

```php
class DocumentFactory
{
    public function __construct(
        private SequenceManager $sequenceManager,
    ) {}
    
    /**
     * Create a new document
     */
    public function create(DocumentType $type, array $data): Document
    {
        // Get appropriate sequence
        $sequence = $this->getSequence($type, $data);
        
        // Generate number
        $number = $this->sequenceManager->getNext($sequence);
        
        // Create document
        $document = Document::create([
            'type' => $type,
            'number' => $number,
            'status' => DocumentStatus::Draft,
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'issue_date' => $data['issue_date'] ?? now(),
            'due_date' => $this->calculateDueDate($type, $data),
            'valid_until' => $this->calculateValidUntil($type, $data),
            'currency' => $data['currency'] ?? config('docs.defaults.currency', 'MYR'),
            'notes' => $data['notes'] ?? null,
            'terms' => $data['terms'] ?? $this->getDefaultTerms($type),
            'version' => 1,
        ]);
        
        // Add items
        if (! empty($data['items'])) {
            $this->addItems($document, $data['items']);
        }
        
        // Calculate totals
        $this->calculateTotals($document);
        
        return $document;
    }
    
    /**
     * Convert document to another type
     */
    public function convert(Document $source, DocumentType $targetType): Document
    {
        if (! $source->canConvertTo($targetType)) {
            throw new InvalidConversionException(
                "Cannot convert {$source->type->value} to {$targetType->value}"
            );
        }
        
        $target = $this->create($targetType, [
            'customer_name' => $source->customer_name,
            'customer_email' => $source->customer_email,
            'customer_address' => $source->customer_address,
            'currency' => $source->currency,
            'notes' => $source->notes,
            'items' => $source->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'tax_rate' => $item->tax_rate,
            ])->toArray(),
        ]);
        
        // Link documents
        $target->update(['converted_from_id' => $source->id]);
        $source->update(['status' => DocumentStatus::Converted]);
        
        return $target;
    }
    
    private function calculateDueDate(DocumentType $type, array $data): ?Carbon
    {
        if (! $type->hasDueDate()) {
            return null;
        }
        
        $days = $data['payment_terms'] ?? config('docs.defaults.payment_terms', 30);
        $issueDate = $data['issue_date'] ?? now();
        
        return Carbon::parse($issueDate)->addDays($days);
    }
    
    private function calculateValidUntil(DocumentType $type, array $data): ?Carbon
    {
        if (! $type->hasValidityPeriod()) {
            return null;
        }
        
        $days = $data['validity_days'] ?? config('docs.defaults.validity_days', 30);
        $issueDate = $data['issue_date'] ?? now();
        
        return Carbon::parse($issueDate)->addDays($days);
    }
}
```

---

## Type-Specific Services

### InvoiceService

```php
class InvoiceService
{
    public function recordPayment(Document $invoice, int $amountMinor, array $data = []): DocumentPayment
    {
        if ($invoice->type !== DocumentType::Invoice) {
            throw new InvalidDocumentTypeException('Document must be an invoice');
        }
        
        $payment = DocumentPayment::create([
            'document_id' => $invoice->id,
            'amount_minor' => $amountMinor,
            'payment_method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'] ?? now(),
        ]);
        
        $invoice->increment('paid_minor', $amountMinor);
        
        // Update status
        if ($invoice->isPaid()) {
            $invoice->update(['status' => DocumentStatus::Paid]);
        } elseif ($invoice->paid_minor > 0) {
            $invoice->update(['status' => DocumentStatus::PartiallyPaid]);
        }
        
        return $payment;
    }
    
    public function generateReceipt(Document $invoice): Document
    {
        if (! $invoice->isPaid()) {
            throw new DocumentException('Cannot generate receipt for unpaid invoice');
        }
        
        return app(DocumentFactory::class)->create(DocumentType::Receipt, [
            'customer_name' => $invoice->customer_name,
            'customer_email' => $invoice->customer_email,
            'parent_id' => $invoice->id,
            'items' => [[
                'description' => "Payment for {$invoice->number}",
                'quantity' => 1,
                'unit_price_minor' => $invoice->paid_minor,
            ]],
        ]);
    }
}
```

### CreditNoteService

```php
class CreditNoteService
{
    public function createFromInvoice(
        Document $invoice,
        int $amountMinor,
        string $reason,
        array $items = []
    ): Document {
        if ($invoice->type !== DocumentType::Invoice) {
            throw new InvalidDocumentTypeException('Source must be an invoice');
        }
        
        $creditNote = app(DocumentFactory::class)->create(DocumentType::CreditNote, [
            'customer_name' => $invoice->customer_name,
            'customer_email' => $invoice->customer_email,
            'parent_id' => $invoice->id,
            'notes' => $reason,
            'items' => $items ?: [[
                'description' => "Credit for {$invoice->number}",
                'quantity' => 1,
                'unit_price_minor' => $amountMinor,
            ]],
        ]);
        
        return $creditNote;
    }
    
    public function apply(Document $creditNote, Document $targetInvoice): void
    {
        if ($creditNote->type !== DocumentType::CreditNote) {
            throw new InvalidDocumentTypeException('Document must be a credit note');
        }
        
        if ($creditNote->status === DocumentStatus::Applied) {
            throw new DocumentException('Credit note already applied');
        }
        
        // Apply credit to invoice
        $targetInvoice->increment('paid_minor', $creditNote->total_minor);
        
        // Update credit note status
        $creditNote->update([
            'status' => DocumentStatus::Applied,
            'metadata' => array_merge($creditNote->metadata ?? [], [
                'applied_to' => $targetInvoice->id,
                'applied_at' => now()->toIso8601String(),
            ]),
        ]);
    }
}
```

### QuotationService

```php
class QuotationService
{
    public function accept(Document $quotation): Document
    {
        if ($quotation->type !== DocumentType::Quotation) {
            throw new InvalidDocumentTypeException('Document must be a quotation');
        }
        
        if ($quotation->isExpired()) {
            throw new DocumentException('Quotation has expired');
        }
        
        $quotation->update(['status' => DocumentStatus::Accepted]);
        
        return $quotation;
    }
    
    public function decline(Document $quotation, string $reason = null): void
    {
        $quotation->update([
            'status' => DocumentStatus::Declined,
            'metadata' => array_merge($quotation->metadata ?? [], [
                'decline_reason' => $reason,
                'declined_at' => now()->toIso8601String(),
            ]),
        ]);
    }
    
    public function convertToInvoice(Document $quotation): Document
    {
        if ($quotation->status !== DocumentStatus::Accepted) {
            throw new DocumentException('Only accepted quotations can be converted');
        }
        
        return app(DocumentFactory::class)->convert($quotation, DocumentType::Invoice);
    }
}
```

---

## Database Schema Updates

```php
// Enhanced doc_documents table
Schema::table('doc_documents', function (Blueprint $table) {
    // Type-specific fields
    $table->foreignUuid('parent_id')->nullable()->after('number');
    $table->foreignUuid('converted_from_id')->nullable()->after('parent_id');
    $table->date('valid_until')->nullable()->after('due_date');
    $table->integer('paid_minor')->default(0)->after('total_minor');
    
    // Versioning
    $table->unsignedInteger('version')->default(1)->after('metadata');
    
    // E-invoicing
    $table->boolean('is_e_invoiced')->default(false)->after('version');
    $table->string('e_invoice_id')->nullable()->after('is_e_invoiced');
    
    // Indexes
    $table->index('parent_id');
    $table->index('converted_from_id');
    $table->index(['type', 'status']);
});

// doc_payments table
Schema::create('doc_payments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id');
    $table->bigInteger('amount_minor');
    $table->string('payment_method')->nullable();
    $table->string('reference')->nullable();
    $table->timestamp('paid_at');
    $table->timestamps();
    
    $table->index('document_id');
});
```

---

## Navigation

**Previous:** [02-sequential-numbering.md](02-sequential-numbering.md)  
**Next:** [04-e-invoicing.md](04-e-invoicing.md)
