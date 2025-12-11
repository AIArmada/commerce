<?php

declare(strict_types=1);

namespace AIArmada\Tax\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Represents a tax exemption for a customer or entity.
 *
 * @property string $id
 * @property string|null $exemptable_id
 * @property string|null $exemptable_type
 * @property string $reason
 * @property string|null $certificate_number
 * @property string|null $document_path
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 */
class TaxExemption extends Model
{
    use HasUuids;
    use LogsActivity;

    protected $guarded = ['id'];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    public function getTable(): string
    {
        return config('tax.tables.tax_exemptions', 'tax_exemptions');
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * The exemptable entity (Customer, User, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function exemptable(): MorphTo
    {
        return $this->morphTo();
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('status', 'approved')
            ->where(function ($q): void {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            });
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isActive(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }

        if ($this->expires_at && $this->expires_at < now()) {
            return false;
        }

        return true;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at < now();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function approve(): self
    {
        $this->update([
            'status' => 'approved',
            'verified_at' => now(),
        ]);

        return $this;
    }

    public function reject(string $reason): self
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
        ]);

        return $this;
    }

    // =========================================================================
    // ACTIVITY LOG
    // =========================================================================

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reason', 'status', 'verified_at', 'expires_at'])
            ->logOnlyDirty()
            ->useLogName('tax');
    }
}
