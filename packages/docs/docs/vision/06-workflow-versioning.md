---
title: Workflow & Versioning
---

# Workflow & Versioning

> **Document:** 06 of 10  
> **Package:** `aiarmada/docs`  
> **Status:** Vision

---

## Overview

Implement **document workflows** for approval processes and **version control** for complete audit trails and change tracking.

---

## Workflow Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                    DOCUMENT WORKFLOW                          │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌─────────────┐     ┌─────────────┐     ┌─────────────┐    │
│  │    Draft    │────▶│   Pending   │────▶│  Approved   │    │
│  │             │     │   Approval  │     │             │    │
│  └─────────────┘     └─────────────┘     └─────────────┘    │
│         │                   │                   │            │
│         │                   ▼                   ▼            │
│         │            ┌─────────────┐     ┌─────────────┐    │
│         │            │  Rejected   │     │    Sent     │    │
│         │            │             │     │             │    │
│         │            └─────────────┘     └─────────────┘    │
│         │                                       │            │
│         └───────────────────────────────────────┘            │
│                        │                                     │
│                        ▼                                     │
│                 ┌─────────────┐                              │
│                 │  Audit Log  │                              │
│                 │  (Actions)  │                              │
│                 └─────────────┘                              │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```

---

## Approval Workflow

### WorkflowConfig

```php
/**
 * @property string $id
 * @property string $name
 * @property DocumentType|null $document_type
 * @property int $threshold_minor
 * @property array $approval_levels
 * @property bool $is_active
 */
class WorkflowConfig extends Model
{
    use HasUuids;
    
    protected $casts = [
        'document_type' => DocumentType::class,
        'approval_levels' => 'array',
        'is_active' => 'boolean',
    ];
    
    public function getTable(): string
    {
        return config('docs.database.tables.workflow_configs')
            ?? config('docs.database.table_prefix', 'doc_') . 'workflow_configs';
    }
    
    /**
     * Check if document requires approval
     */
    public function requiresApproval(Document $document): bool
    {
        if (! $this->is_active) {
            return false;
        }
        
        if ($this->document_type && $document->type !== $this->document_type) {
            return false;
        }
        
        return $document->total_minor >= $this->threshold_minor;
    }
    
    /**
     * Get approvers for a level
     */
    public function getApprovers(int $level): Collection
    {
        $config = $this->approval_levels[$level] ?? null;
        
        if (! $config) {
            return collect();
        }
        
        return match ($config['type']) {
            'users' => User::whereIn('id', $config['user_ids'])->get(),
            'role' => User::role($config['role'])->get(),
            'permission' => User::permission($config['permission'])->get(),
            default => collect(),
        };
    }
}
```

### DocumentApproval Model

```php
/**
 * @property string $id
 * @property string $document_id
 * @property string $workflow_config_id
 * @property int $level
 * @property string $status
 * @property string|null $approver_id
 * @property string|null $comment
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 */
class DocumentApproval extends Model
{
    use HasUuids;
    
    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];
    
    public function getTable(): string
    {
        return config('docs.database.tables.approvals')
            ?? config('docs.database.table_prefix', 'doc_') . 'approvals';
    }
    
    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
    
    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
    
    /**
     * @return BelongsTo<WorkflowConfig, $this>
     */
    public function workflowConfig(): BelongsTo
    {
        return $this->belongsTo(WorkflowConfig::class, 'workflow_config_id');
    }
}
```

### ApprovalService

```php
class ApprovalService
{
    public function __construct(
        private NotificationService $notifications,
    ) {}
    
    /**
     * Submit document for approval
     */
    public function submit(Document $document): void
    {
        $workflow = $this->getApplicableWorkflow($document);
        
        if (! $workflow) {
            throw new WorkflowException('No applicable workflow found');
        }
        
        // Create approval records for first level
        $this->createApprovalForLevel($document, $workflow, 0);
        
        // Update document status
        $document->update(['status' => DocumentStatus::Draft]); // PendingApproval
        
        // Store workflow reference
        $document->update([
            'metadata' => array_merge($document->metadata ?? [], [
                'workflow_id' => $workflow->id,
                'current_approval_level' => 0,
            ]),
        ]);
    }
    
    /**
     * Approve document at current level
     */
    public function approve(Document $document, User $approver, string $comment = null): void
    {
        $approval = $this->getPendingApproval($document, $approver);
        
        if (! $approval) {
            throw new WorkflowException('No pending approval for this user');
        }
        
        $approval->update([
            'status' => 'approved',
            'approver_id' => $approver->id,
            'comment' => $comment,
            'approved_at' => now(),
        ]);
        
        // Check if all approvals at this level are complete
        $this->processLevelCompletion($document, $approval->level);
    }
    
    /**
     * Reject document
     */
    public function reject(Document $document, User $approver, string $reason): void
    {
        $approval = $this->getPendingApproval($document, $approver);
        
        if (! $approval) {
            throw new WorkflowException('No pending approval for this user');
        }
        
        $approval->update([
            'status' => 'rejected',
            'approver_id' => $approver->id,
            'comment' => $reason,
            'rejected_at' => now(),
        ]);
        
        // Update document status
        $document->update(['status' => DocumentStatus::Draft]); // Rejected
        
        // Notify document owner
        $this->notifications->notifyRejected($document, $approver, $reason);
    }
    
    private function processLevelCompletion(Document $document, int $level): void
    {
        $pendingCount = $document->approvals()
            ->where('level', $level)
            ->where('status', 'pending')
            ->count();
        
        if ($pendingCount > 0) {
            return; // Still waiting for approvals
        }
        
        $workflow = WorkflowConfig::find($document->metadata['workflow_id']);
        $nextLevel = $level + 1;
        
        if ($nextLevel < count($workflow->approval_levels)) {
            // Create approvals for next level
            $this->createApprovalForLevel($document, $workflow, $nextLevel);
            
            $document->update([
                'metadata' => array_merge($document->metadata, [
                    'current_approval_level' => $nextLevel,
                ]),
            ]);
        } else {
            // All levels approved
            $document->update(['status' => DocumentStatus::Sent]); // Approved
            
            // Notify document owner
            $this->notifications->notifyApproved($document);
        }
    }
}
```

---

## Version Control

### DocumentVersion Model

```php
/**
 * @property string $id
 * @property string $document_id
 * @property int $version
 * @property array $snapshot
 * @property array $changes
 * @property string|null $changed_by
 * @property string|null $change_reason
 */
class DocumentVersion extends Model
{
    use HasUuids;
    
    protected $casts = [
        'snapshot' => 'array',
        'changes' => 'array',
    ];
    
    public function getTable(): string
    {
        return config('docs.database.tables.versions')
            ?? config('docs.database.table_prefix', 'doc_') . 'versions';
    }
    
    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
    
    /**
     * @return BelongsTo<User, $this>
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
```

### VersioningService

```php
class VersioningService
{
    /**
     * Create a new version before changes
     */
    public function createVersion(Document $document, User $user = null, string $reason = null): DocumentVersion
    {
        $currentVersion = $document->version;
        
        // Create snapshot
        $snapshot = $this->createSnapshot($document);
        
        // Get changes from previous version
        $previousVersion = $document->versions()->latest()->first();
        $changes = $previousVersion 
            ? $this->calculateChanges($previousVersion->snapshot, $snapshot)
            : ['initial' => true];
        
        // Create version record
        $version = DocumentVersion::create([
            'document_id' => $document->id,
            'version' => $currentVersion,
            'snapshot' => $snapshot,
            'changes' => $changes,
            'changed_by' => $user?->id,
            'change_reason' => $reason,
        ]);
        
        // Increment document version
        $document->increment('version');
        
        return $version;
    }
    
    /**
     * Create snapshot of document state
     */
    private function createSnapshot(Document $document): array
    {
        return [
            'customer_name' => $document->customer_name,
            'customer_email' => $document->customer_email,
            'customer_address' => $document->customer_address,
            'issue_date' => $document->issue_date->toIso8601String(),
            'due_date' => $document->due_date?->toIso8601String(),
            'valid_until' => $document->valid_until?->toIso8601String(),
            'subtotal_minor' => $document->subtotal_minor,
            'discount_minor' => $document->discount_minor,
            'tax_minor' => $document->tax_minor,
            'total_minor' => $document->total_minor,
            'notes' => $document->notes,
            'terms' => $document->terms,
            'items' => $document->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price_minor' => $item->unit_price_minor,
                'tax_rate' => $item->tax_rate,
                'subtotal_minor' => $item->subtotal_minor,
            ])->toArray(),
        ];
    }
    
    /**
     * Calculate changes between versions
     */
    private function calculateChanges(array $previous, array $current): array
    {
        $changes = [];
        
        foreach ($current as $key => $value) {
            if ($key === 'items') {
                $itemChanges = $this->calculateItemChanges(
                    $previous['items'] ?? [],
                    $current['items']
                );
                if (! empty($itemChanges)) {
                    $changes['items'] = $itemChanges;
                }
                continue;
            }
            
            if (! isset($previous[$key]) || $previous[$key] !== $value) {
                $changes[$key] = [
                    'old' => $previous[$key] ?? null,
                    'new' => $value,
                ];
            }
        }
        
        return $changes;
    }
    
    /**
     * Compare document versions
     */
    public function compare(DocumentVersion $versionA, DocumentVersion $versionB): array
    {
        return $this->calculateChanges($versionA->snapshot, $versionB->snapshot);
    }
    
    /**
     * Restore document to a previous version
     */
    public function restore(Document $document, DocumentVersion $version, User $user): void
    {
        // Create version before restore
        $this->createVersion($document, $user, "Restored to version {$version->version}");
        
        $snapshot = $version->snapshot;
        
        // Restore document fields
        $document->update([
            'customer_name' => $snapshot['customer_name'],
            'customer_email' => $snapshot['customer_email'],
            'customer_address' => $snapshot['customer_address'],
            'issue_date' => Carbon::parse($snapshot['issue_date']),
            'due_date' => $snapshot['due_date'] ? Carbon::parse($snapshot['due_date']) : null,
            'subtotal_minor' => $snapshot['subtotal_minor'],
            'discount_minor' => $snapshot['discount_minor'],
            'tax_minor' => $snapshot['tax_minor'],
            'total_minor' => $snapshot['total_minor'],
            'notes' => $snapshot['notes'],
            'terms' => $snapshot['terms'],
        ]);
        
        // Restore items
        $document->items()->delete();
        
        foreach ($snapshot['items'] as $itemData) {
            $document->items()->create([
                'description' => $itemData['description'],
                'quantity' => $itemData['quantity'],
                'unit_price_minor' => $itemData['unit_price_minor'],
                'tax_rate' => $itemData['tax_rate'],
                'subtotal_minor' => $itemData['subtotal_minor'],
            ]);
        }
    }
}
```

---

## Audit Trail

### DocumentAuditLog Model

```php
/**
 * @property string $id
 * @property string $document_id
 * @property string $action
 * @property array|null $old_values
 * @property array|null $new_values
 * @property string|null $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 */
class DocumentAuditLog extends Model
{
    use HasUuids;
    
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];
    
    public function getTable(): string
    {
        return config('docs.database.tables.audit_logs')
            ?? config('docs.database.table_prefix', 'doc_') . 'audit_logs';
    }
    
    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }
    
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

### AuditService

```php
class AuditService
{
    /**
     * Log document action
     */
    public function log(
        Document $document,
        string $action,
        array $oldValues = null,
        array $newValues = null
    ): DocumentAuditLog {
        return DocumentAuditLog::create([
            'document_id' => $document->id,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
    
    /**
     * Get audit trail for document
     */
    public function getTrail(Document $document): Collection
    {
        return $document->auditLogs()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}

// Document model observer
class DocumentObserver
{
    public function __construct(private AuditService $audit) {}
    
    public function created(Document $document): void
    {
        $this->audit->log($document, 'created', null, $document->toArray());
    }
    
    public function updated(Document $document): void
    {
        $this->audit->log(
            $document,
            'updated',
            $document->getOriginal(),
            $document->getChanges()
        );
    }
    
    public function deleted(Document $document): void
    {
        $this->audit->log($document, 'deleted', $document->toArray(), null);
    }
}
```

---

## Database Schema

```php
// doc_workflow_configs table
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

// doc_approvals table
Schema::create('doc_approvals', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->foreignUuid('document_id');
    $table->foreignUuid('workflow_config_id');
    $table->unsignedInteger('level');
    $table->string('status')->default('pending'); // pending, approved, rejected
    $table->foreignUuid('approver_id')->nullable();
    $table->text('comment')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('rejected_at')->nullable();
    $table->timestamps();
    
    $table->index('document_id');
    $table->index(['document_id', 'level', 'status']);
});

// doc_versions table
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

// doc_audit_logs table
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

## Navigation

**Previous:** [05-email-integration.md](05-email-integration.md)  
**Next:** [07-database-evolution.md](07-database-evolution.md)
