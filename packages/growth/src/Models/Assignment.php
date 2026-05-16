<?php

declare(strict_types=1);

namespace AIArmada\Growth\Models;

use AIArmada\CommerceSupport\Traits\HasOwner;
use AIArmada\CommerceSupport\Traits\HasOwnerScopeConfig;
use AIArmada\Signals\Models\SignalIdentity;
use AIArmada\Signals\Models\SignalSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * @property string $id
 * @property string $experiment_id
 * @property string $variant_id
 * @property string|null $signal_identity_id
 * @property string|null $signal_session_id
 * @property string $subject_key
 * @property int $bucket
 * @property array<string, mixed>|null $metadata
 * @property string|null $owner_type
 * @property string|null $owner_id
 * @property CarbonImmutable $assigned_at
 * @property CarbonImmutable|null $first_exposed_at
 * @property CarbonImmutable|null $last_seen_at
 * @property-read Experiment $experiment
 * @property-read Variant $variant
 * @property-read SignalIdentity|null $identity
 * @property-read SignalSession|null $session
 */
final class Assignment extends Model
{
    use HasFactory;
    use HasOwner;
    use HasOwnerScopeConfig;
    use HasUuids;

    protected static string $ownerScopeConfigKey = 'growth.features.owner';

    /** @var list<string> */
    protected $fillable = [
        'experiment_id',
        'variant_id',
        'signal_identity_id',
        'signal_session_id',
        'subject_key',
        'bucket',
        'metadata',
        'assigned_at',
        'first_exposed_at',
        'last_seen_at',
        'owner_type',
        'owner_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'bucket' => 'integer',
        'metadata' => 'array',
        'assigned_at' => 'immutable_datetime',
        'first_exposed_at' => 'immutable_datetime',
        'last_seen_at' => 'immutable_datetime',
    ];

    public function getTable(): string
    {
        $tables = config('growth.database.tables', []);
        $prefix = config('growth.database.table_prefix', 'growth_');

        return $tables['assignments'] ?? $prefix . 'assignments';
    }

    /**
     * @return BelongsTo<Experiment, $this>
     */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class, 'experiment_id');
    }

    /**
     * @return BelongsTo<Variant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class, 'variant_id');
    }

    /**
     * @return BelongsTo<SignalIdentity, $this>
     */
    public function identity(): BelongsTo
    {
        return $this->belongsTo(SignalIdentity::class, 'signal_identity_id');
    }

    /**
     * @return BelongsTo<SignalSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(SignalSession::class, 'signal_session_id');
    }

    protected static function booted(): void
    {
        static::creating(function (Assignment $assignment): void {
            $assignment->assigned_at ??= CarbonImmutable::now();
            $assignment->first_exposed_at ??= $assignment->assigned_at;
            $assignment->last_seen_at ??= $assignment->assigned_at;
        });

        static::saving(function (Assignment $assignment): void {
            $assignment->assertExperimentAndVariantConsistency();
        });
    }

    private function assertExperimentAndVariantConsistency(): void
    {
        $experiment = Experiment::query()
            ->withoutOwnerScope()
            ->whereKey($this->experiment_id)
            ->first();

        if (! $experiment instanceof Experiment) {
            throw new InvalidArgumentException('Assignment experiment could not be resolved.');
        }

        if (! $this->exists && $this->owner_type === null && $this->owner_id === null) {
            $this->owner_type = $experiment->owner_type;
            $this->owner_id = $experiment->owner_id;
        }

        $variant = Variant::query()
            ->withoutOwnerScope()
            ->whereKey($this->variant_id)
            ->first();

        if (! $variant instanceof Variant) {
            throw new InvalidArgumentException('Assignment variant could not be resolved.');
        }

        if ($variant->experiment_id !== $experiment->getKey()) {
            throw new InvalidArgumentException('Assignment variant must belong to the same experiment.');
        }

        if ($experiment->owner_type !== $this->owner_type || (string) $experiment->owner_id !== (string) $this->owner_id) {
            throw new InvalidArgumentException('Assignment owner must match the parent experiment owner.');
        }
    }
}
