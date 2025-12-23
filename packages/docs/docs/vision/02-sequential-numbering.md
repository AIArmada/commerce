---
title: Sequential Numbering System
---

# Sequential Numbering System

> **Document:** 02 of 10  
> **Package:** `aiarmada/docs`  
> **Status:** Vision

---

## Overview

Implement a **robust sequential numbering system** that supports multiple formats, prefixes, series, and compliance requirements for business documents.

---

## Numbering Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                  SEQUENCE MANAGEMENT                          │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────────┐     ┌─────────────────┐                 │
│  │  DocSequence    │────▶│ SequenceNumber  │                 │
│  │  (Definition)   │     │ (Allocation)    │                 │
│  └─────────────────┘     └─────────────────┘                 │
│         │                        │                            │
│         │                        ▼                            │
│         │                ┌─────────────────┐                 │
│         │                │  Number Pool    │                 │
│         │                │  (Lock Manager) │                 │
│         │                └─────────────────┘                 │
│         │                                                     │
│         ▼                                                     │
│  ┌─────────────────────────────────────────────────────┐    │
│  │                SEQUENCE EXAMPLES                     │    │
│  │                                                      │    │
│  │  INV-2024-00001    Invoice, yearly reset            │    │
│  │  QUO/KL/24/0001    Quotation, location + year       │    │
│  │  CN-00000001       Credit Note, never reset         │    │
│  │  RCP-2024-01-001   Receipt, monthly reset           │    │
│  └─────────────────────────────────────────────────────┘    │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## Sequence Model

### DocSequence

```php
/**
 * Defines a numbering sequence for documents
 * 
 * @property string $id
 * @property string $name
 * @property string $code
 * @property DocumentType $document_type
 * @property string $prefix
 * @property string $suffix
 * @property int $padding
 * @property ResetFrequency $reset_frequency
 * @property int $current_number
 * @property Carbon|null $last_reset_at
 * @property array|null $format_tokens
 * @property string|null $scope_type
 * @property string|null $scope_id
 * @property bool $is_default
 * @property bool $is_active
 */
class DocSequence extends Model
{
    use HasUuids;
    
    protected $casts = [
        'document_type' => DocumentType::class,
        'reset_frequency' => ResetFrequency::class,
        'format_tokens' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'last_reset_at' => 'datetime',
    ];
    
    public function getTable(): string
    {
        return config('docs.database.tables.sequences')
            ?? config('docs.database.table_prefix', 'doc_') . 'sequences';
    }
    
    /**
     * @return HasMany<DocSequenceNumber, $this>
     */
    public function numbers(): HasMany
    {
        return $this->hasMany(DocSequenceNumber::class, 'sequence_id');
    }
    
    public function getNextNumber(): string
    {
        return app(SequenceManager::class)->getNext($this);
    }
    
    protected static function booted(): void
    {
        static::deleting(function (DocSequence $sequence): void {
            $sequence->numbers()->delete();
        });
    }
}
```

---

## Enums

### ResetFrequency

```php
enum ResetFrequency: string
{
    case Never = 'never';
    case Daily = 'daily';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';
    
    public function shouldReset(Carbon $lastReset): bool
    {
        return match ($this) {
            self::Never => false,
            self::Daily => ! $lastReset->isToday(),
            self::Monthly => ! $lastReset->isSameMonth(now()),
            self::Quarterly => $lastReset->quarter !== now()->quarter || $lastReset->year !== now()->year,
            self::Yearly => $lastReset->year !== now()->year,
        };
    }
    
    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never reset',
            self::Daily => 'Reset daily',
            self::Monthly => 'Reset monthly',
            self::Quarterly => 'Reset quarterly',
            self::Yearly => 'Reset yearly',
        };
    }
}
```

### FormatToken

```php
enum FormatToken: string
{
    case Prefix = '{PREFIX}';
    case Suffix = '{SUFFIX}';
    case Number = '{NUMBER}';
    case Year = '{YYYY}';
    case YearShort = '{YY}';
    case Month = '{MM}';
    case Day = '{DD}';
    case Quarter = '{Q}';
    case Scope = '{SCOPE}';
    case Type = '{TYPE}';
    
    public function resolve(DocSequence $sequence, int $number): string
    {
        return match ($this) {
            self::Prefix => $sequence->prefix ?? '',
            self::Suffix => $sequence->suffix ?? '',
            self::Number => str_pad((string) $number, $sequence->padding, '0', STR_PAD_LEFT),
            self::Year => now()->format('Y'),
            self::YearShort => now()->format('y'),
            self::Month => now()->format('m'),
            self::Day => now()->format('d'),
            self::Quarter => (string) now()->quarter,
            self::Scope => $sequence->scope_id ?? '',
            self::Type => $sequence->document_type->value,
        };
    }
}
```

---

## Sequence Manager

### SequenceManager

```php
class SequenceManager
{
    public function __construct(
        private LockManager $lockManager,
    ) {}
    
    /**
     * Get the next number in the sequence (atomic operation)
     */
    public function getNext(DocSequence $sequence): string
    {
        return $this->lockManager->withLock(
            "sequence:{$sequence->id}",
            function () use ($sequence): string {
                // Check if reset needed
                if ($this->shouldReset($sequence)) {
                    $this->reset($sequence);
                }
                
                // Increment and get number
                $sequence->increment('current_number');
                $sequence->refresh();
                
                // Format the number
                return $this->format($sequence, $sequence->current_number);
            }
        );
    }
    
    /**
     * Reserve a range of numbers for batch operations
     */
    public function reserve(DocSequence $sequence, int $count): array
    {
        return $this->lockManager->withLock(
            "sequence:{$sequence->id}",
            function () use ($sequence, $count): array {
                if ($this->shouldReset($sequence)) {
                    $this->reset($sequence);
                }
                
                $start = $sequence->current_number + 1;
                $sequence->increment('current_number', $count);
                
                $numbers = [];
                for ($i = 0; $i < $count; $i++) {
                    $numbers[] = $this->format($sequence, $start + $i);
                }
                
                return $numbers;
            }
        );
    }
    
    /**
     * Format number according to sequence configuration
     */
    public function format(DocSequence $sequence, int $number): string
    {
        $tokens = $sequence->format_tokens ?? [
            FormatToken::Prefix->value,
            FormatToken::Year->value,
            '-',
            FormatToken::Number->value,
        ];
        
        $result = '';
        
        foreach ($tokens as $token) {
            $enumToken = FormatToken::tryFrom($token);
            if ($enumToken) {
                $result .= $enumToken->resolve($sequence, $number);
            } else {
                $result .= $token; // Literal character
            }
        }
        
        return $result;
    }
    
    /**
     * Check if sequence should reset
     */
    private function shouldReset(DocSequence $sequence): bool
    {
        if ($sequence->reset_frequency === ResetFrequency::Never) {
            return false;
        }
        
        if (! $sequence->last_reset_at) {
            return true;
        }
        
        return $sequence->reset_frequency->shouldReset($sequence->last_reset_at);
    }
    
    /**
     * Reset sequence counter
     */
    private function reset(DocSequence $sequence): void
    {
        $sequence->update([
            'current_number' => 0,
            'last_reset_at' => now(),
        ]);
    }
}
```

---

## Sequence Number Record

### DocSequenceNumber

```php
/**
 * Tracks allocated numbers for audit trail
 * 
 * @property string $id
 * @property string $sequence_id
 * @property int $number
 * @property string $formatted_number
 * @property string|null $document_id
 * @property string $status
 * @property Carbon|null $used_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 */
class DocSequenceNumber extends Model
{
    use HasUuids;
    
    protected $casts = [
        'used_at' => 'datetime',
        'voided_at' => 'datetime',
    ];
    
    public function getTable(): string
    {
        return config('docs.database.tables.sequence_numbers')
            ?? config('docs.database.table_prefix', 'doc_') . 'sequence_numbers';
    }
    
    /**
     * @return BelongsTo<DocSequence, $this>
     */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(DocSequence::class, 'sequence_id');
    }
    
    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
    
    public function void(string $reason): void
    {
        $this->update([
            'status' => 'voided',
            'voided_at' => now(),
            'void_reason' => $reason,
        ]);
    }
}
```

---

## Lock Manager

### LockManager

```php
class LockManager
{
    public function __construct(
        private Repository $cache,
    ) {}
    
    /**
     * Execute callback with exclusive lock
     */
    public function withLock(string $key, Closure $callback, int $timeout = 10): mixed
    {
        $lock = Cache::lock($key, $timeout);
        
        try {
            if ($lock->block($timeout)) {
                return $callback();
            }
            
            throw new SequenceLockException("Unable to acquire lock for: {$key}");
        } finally {
            $lock->release();
        }
    }
}
```

---

## Format Patterns

### Common Patterns

| Pattern | Example | Use Case |
|---------|---------|----------|
| `{PREFIX}{YYYY}-{NUMBER}` | INV2024-00001 | Standard invoice |
| `{PREFIX}/{SCOPE}/{YY}/{NUMBER}` | QUO/KL/24/0001 | Location-based |
| `{PREFIX}-{NUMBER}` | CN-00000001 | Simple sequential |
| `{PREFIX}{YYYY}{MM}-{NUMBER}` | RCP202401-001 | Monthly grouping |
| `{TYPE}-{YY}-{NUMBER}{SUFFIX}` | INV-24-00001-A | Typed with suffix |

### Creating Custom Formats

```php
// In sequence creation
DocSequence::create([
    'name' => 'Kuala Lumpur Invoices',
    'code' => 'inv-kl',
    'document_type' => DocumentType::Invoice,
    'prefix' => 'INV',
    'padding' => 5,
    'reset_frequency' => ResetFrequency::Yearly,
    'format_tokens' => [
        '{PREFIX}',
        '/',
        'KL',
        '/',
        '{YY}',
        '/',
        '{NUMBER}',
    ],
    'scope_type' => 'location',
    'scope_id' => 'kl',
    'is_default' => false,
]);

// Result: INV/KL/24/00001
```

---

## Gap-Free Sequences

### Ensuring No Gaps

```php
class GapFreeSequenceManager extends SequenceManager
{
    /**
     * Get next number with tracking
     */
    public function getNext(DocSequence $sequence): string
    {
        return $this->lockManager->withLock(
            "sequence:{$sequence->id}",
            function () use ($sequence): string {
                // Check for reset
                if ($this->shouldReset($sequence)) {
                    $this->reset($sequence);
                }
                
                // Get next number
                $nextNumber = $sequence->current_number + 1;
                
                // Record allocation BEFORE incrementing
                $record = DocSequenceNumber::create([
                    'sequence_id' => $sequence->id,
                    'number' => $nextNumber,
                    'formatted_number' => $this->format($sequence, $nextNumber),
                    'status' => 'reserved',
                ]);
                
                // Now increment
                $sequence->increment('current_number');
                
                return $record->formatted_number;
            }
        );
    }
    
    /**
     * Confirm number usage (link to document)
     */
    public function confirm(string $formattedNumber, string $documentId): void
    {
        DocSequenceNumber::where('formatted_number', $formattedNumber)
            ->update([
                'document_id' => $documentId,
                'status' => 'used',
                'used_at' => now(),
            ]);
    }
    
    /**
     * Void unused number (maintains audit trail)
     */
    public function void(string $formattedNumber, string $reason): void
    {
        $record = DocSequenceNumber::where('formatted_number', $formattedNumber)->first();
        
        if ($record && ! $record->document_id) {
            $record->void($reason);
        }
    }
}
```

---

## Database Schema

```php
// doc_sequences table
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

// doc_sequence_numbers table
Schema::create('doc_sequence_numbers', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('sequence_id');
    $table->unsignedInteger('number');
    $table->string('formatted_number')->unique();
    $table->foreignUuid('document_id')->nullable();
    $table->string('status')->default('reserved'); // reserved, used, voided
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

## Navigation

**Previous:** [01-executive-summary.md](01-executive-summary.md)  
**Next:** [03-document-types.md](03-document-types.md)
