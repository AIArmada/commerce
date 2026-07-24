<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\AffiliationType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $affiliatable_type
 * @property string $affiliatable_id
 * @property string|null $institution_id
 * @property AffiliationType $affiliation_type
 * @property CarbonImmutable|null $joined_at
 * @property CarbonImmutable|null $left_at
 * @property bool $is_primary
 * @property-read Model $affiliatable
 * @property-read Collection<int, AffiliationRole> $roles
 */
class Affiliation extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'affiliatable_type',
        'affiliatable_id',
        'institution_id',
        'affiliation_type',
        'joined_at',
        'left_at',
        'is_primary',
    ];

    public function getTable(): string
    {
        return config('persons.database.tables.affiliations', 'affiliations');
    }

    protected function casts(): array
    {
        return [
            'affiliation_type' => AffiliationType::class,
            'joined_at' => 'immutable_date',
            'left_at' => 'immutable_date',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function affiliatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<AffiliationRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(AffiliationRole::class, 'affiliation_id');
    }
}
