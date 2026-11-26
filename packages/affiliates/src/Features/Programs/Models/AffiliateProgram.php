<?php

declare(strict_types=1);

namespace AIArmada\Affiliates\Features\Programs\Models;

use AIArmada\Affiliates\Enums\CommissionType;
use AIArmada\Affiliates\Enums\ProgramStatus;
use AIArmada\Affiliates\Enums\ProgramVisibility;
use AIArmada\CommerceSupport\Concerns\HasCommerceAudit;
use AIArmada\CommerceSupport\Concerns\LogsCommerceActivity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property ProgramStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $requires_approval
 * @property ProgramVisibility $visibility
 * @property int $default_commission_rate_basis_points
 * @property CarbonImmutable|null $paused_at
 * @property CarbonImmutable|null $archived_at
 * @property CommissionType $commission_type
 * @property int $cookie_lifetime_days
 * @property string|null $terms_url
 * @property array<string, mixed>|null $eligibility_rules
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property-read Model|null $owner
 */
class AffiliateProgram extends Model implements Auditable
{
    use HasCommerceAudit;
    use HasOwner {
        scopeForOwner as baseScopeForOwner;
    }
    use HasOwnerScopeConfig;
    use HasUuids;
    use LogsCommerceActivity;

    protected static string $ownerScopeConfigKey = 'affiliates.owner';

    protected $attributes = [
        'visibility' => ProgramVisibility::Private,
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'requires_approval',
        'visibility',
        'default_commission_rate_basis_points',
        'paused_at',
        'archived_at',
        'commission_type',
        'currency',
        'cookie_lifetime_days',
        'terms_url',
        'eligibility_rules',
        'metadata',
    ];

    protected $casts = [
        'status' => ProgramStatus::class,
        'commission_type' => CommissionType::class,
        'starts_at' => 'immutable_datetime',
        'ends_at' => 'immutable_datetime',
        'visibility' => ProgramVisibility::class,
        'requires_approval' => 'boolean',
        'default_commission_rate_basis_points' => 'integer',
        'paused_at' => 'immutable_datetime',
        'archived_at' => 'immutable_datetime',
        'cookie_lifetime_days' => 'integer',
        'eligibility_rules' => 'array',
        'metadata' => 'array',
    ];

    public function getTable(): string
    {
        return config('affiliates.database.tables.programs', 'affiliate_programs');
    }

    public function isActive(): bool
    {
        if ($this->status !== ProgramStatus::Active) {
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

    public function isOpen(): bool
    {
        return $this->isActive() && $this->visibility === ProgramVisibility::Public;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProgramStatus::Active)
            ->where(function ($q): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', CarbonImmutable::now());
            })
            ->where(function ($q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', CarbonImmutable::now());
            });
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('visibility', ProgramVisibility::Public);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeForOwner(Builder $query, Model | string | null $owner = OwnerContext::CURRENT, bool $includeGlobal = false): Builder
    {
        if (! config('affiliates.owner.enabled', false)) {
            return $query;
        }

        $includeGlobal = $includeGlobal && (bool) config('affiliates.owner.include_global', false);

        /** @var Builder<static> $scoped */
        $scoped = $this->baseScopeForOwner($query, $owner, $includeGlobal);

        return $scoped;
    }

    protected static function booted(): void
    {
        static::creating(function (self $program): void {
            if (empty($program->slug)) {
                $program->slug = Str::slug($program->name);
            }

            if (! config('affiliates.owner.enabled', false)) {
                return;
            }

            if ($program->owner_id !== null) {
                return;
            }

            if (! config('affiliates.owner.auto_assign_on_create', true)) {
                return;
            }

            $owner = OwnerContext::resolve();

            if ($owner) {
                $program->owner_type = $owner->getMorphClass();
                $program->owner_id = $owner->getKey();
            }
        });

    }

    protected function getActivityLogName(): string
    {
        return 'affiliates';
    }
}
