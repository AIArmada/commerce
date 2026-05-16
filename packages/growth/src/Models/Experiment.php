<?php

declare(strict_types=1);

namespace AIArmada\Growth\Models;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeKey;
use AIArmada\Growth\Enums\ExperimentStatus;
use AIArmada\Signals\Models\TrackedProperty;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @property string $id
 * @property string $tracked_property_id
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property string $owner_scope
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string $module_type
 * @property ExperimentStatus $status
 * @property string $goal_event_name
 * @property string $goal_event_category
 * @property string $winner_metric
 * @property array<string, mixed>|null $audience
 * @property array<string, mixed>|null $settings
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $ended_at
 * @property-read TrackedProperty $trackedProperty
 * @property-read Collection<int, Variant> $variants
 * @property-read Collection<int, Assignment> $assignments
 */
final class Experiment extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasOwnerScopeKey;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'growth.features.owner';

    /** @var list<string> */
    protected $hidden = [
        'owner_scope',
    ];

    /** @var list<string> */
    protected $fillable = [
        'tracked_property_id',
        'name',
        'slug',
        'description',
        'module_type',
        'status',
        'goal_event_name',
        'goal_event_category',
        'winner_metric',
        'audience',
        'settings',
        'started_at',
        'ended_at',
        'owner_type',
        'owner_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'status' => ExperimentStatus::class,
        'audience' => 'array',
        'settings' => 'array',
        'started_at' => 'immutable_datetime',
        'ended_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        $tables = config('growth.database.tables', []);
        $prefix = config('growth.database.table_prefix', 'growth_');

        return $tables['experiments'] ?? $prefix . 'experiments';
    }

    /**
     * @return BelongsTo<TrackedProperty, $this>
     */
    public function trackedProperty(): BelongsTo
    {
        return $this->belongsTo(TrackedProperty::class, 'tracked_property_id');
    }

    /**
     * @return HasMany<Variant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class, 'experiment_id');
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'experiment_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ExperimentStatus::Active->value);
    }

    protected static function booted(): void
    {
        static::creating(function (Experiment $experiment): void {
            if (! is_string($experiment->slug) || $experiment->slug === '') {
                $experiment->slug = Str::slug($experiment->name);
            }

            if (! is_string($experiment->module_type) || $experiment->module_type === '') {
                $experiment->module_type = (string) config('growth.defaults.module_type', 'ab_test');
            }

            if (! is_string($experiment->goal_event_name) || $experiment->goal_event_name === '') {
                $experiment->goal_event_name = (string) config('growth.integrations.signals.purchase_event_name', 'order.paid');
            }

            if (! is_string($experiment->goal_event_category) || $experiment->goal_event_category === '') {
                $experiment->goal_event_category = 'conversion';
            }

            if (! is_string($experiment->winner_metric) || $experiment->winner_metric === '') {
                $experiment->winner_metric = (string) config('growth.defaults.winner_metric', 'revenue_per_visitor');
            }
        });

        static::saving(function (Experiment $experiment): void {
            if (! static::resolveOwnerScopeConfig()->enabled) {
                return;
            }

            $owner = OwnerContext::resolve();

            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                'Owner scoping is enabled but no owner was resolved while saving a growth experiment.',
            );

            if ($experiment->tracked_property_id === '' || $experiment->tracked_property_id === null) {
                throw new RuntimeException('tracked_property_id is required for a growth experiment.');
            }

            $exists = TrackedProperty::query()
                ->forOwner($owner, includeGlobal: false)
                ->whereKey($experiment->tracked_property_id)
                ->exists();

            if (! $exists) {
                throw new RuntimeException('Invalid tracked_property_id: does not belong to the current owner scope.');
            }
        });

        static::deleting(function (Experiment $experiment): void {
            $experiment->assignments()->delete();
            $experiment->variants()->delete();
        });
    }
}
