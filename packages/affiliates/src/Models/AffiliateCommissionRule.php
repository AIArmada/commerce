<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Models;

use AIArmada\Affiliates\Enums\CommissionRuleType;
use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Models\Concerns\ScopesByProgramOwner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $program_id
 * @property string $name
 * @property CommissionRuleType $rule_type
 * @property int $priority
 * @property array<string, mixed> $conditions
 * @property CommissionType $commission_type
 * @property int $commission_value
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $is_active
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AffiliateProgram|null $program
 */
class AffiliateCommissionRule extends Model
{
    use HasUuids;
    use ScopesByProgramOwner;

    protected $fillable = [
        'program_id',
        'name',
        'rule_type',
        'priority',
        'conditions',
        'commission_type',
        'commission_value',
        'starts_at',
        'ends_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'rule_type' => CommissionRuleType::class,
        'commission_type' => CommissionType::class,
        'priority' => 'integer',
        'commission_value' => 'integer',
        'conditions' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.commission_rules', 'affiliate_commission_rules');
    }

    /**
     * @return BelongsTo<AffiliateProgram, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(AffiliateProgram::class, 'program_id');
    }

    public function isActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority', 'desc');
    }

    /**
     * Check if the rule matches the given context.
     */
    public function matches(array $context): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        $conditions = $this->conditions ?? [];

        foreach ($conditions as $field => $requirement) {
            if (! $this->evaluateCondition($field, $requirement, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate commission for a given amount.
     */
    public function calculateCommission(int $amountMinor): int
    {
        $commissionType = $this->getAttribute('commission_type');

        $commissionTypeValue = $commissionType instanceof CommissionType
            ? $commissionType->value
            : (is_string($commissionType) ? $commissionType : (string) $this->getRawOriginal('commission_type'));

        return match ($commissionTypeValue) {
            CommissionType::Percentage->value => (int) round($amountMinor * $this->commission_value / 10000),
            CommissionType::Fixed->value => $this->commission_value,
            default => 0,
        };
    }

    private function evaluateCondition(string $field, mixed $requirement, array $context): bool
    {
        $value = $context[$field] ?? null;

        if (is_array($requirement)) {
            if (isset($requirement['in'])) {
                return in_array($value, $requirement['in'], true);
            }
            if (isset($requirement['not_in'])) {
                return ! in_array($value, $requirement['not_in'], true);
            }
            if (isset($requirement['min'])) {
                return $value >= $requirement['min'];
            }
            if (isset($requirement['max'])) {
                return $value <= $requirement['max'];
            }
            if (isset($requirement['equals'])) {
                return $value === $requirement['equals'];
            }
        }

        return $value === $requirement;
    }
}
