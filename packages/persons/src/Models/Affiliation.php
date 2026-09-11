<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\AffiliationType;
use AIArmada\Persons\Support\ModelResolver;
use AIArmada\Persons\Support\PersonsModelReferenceGuard;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;

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

    protected static function booted(): void
    {
        static::saving(function (Affiliation $affiliation): void {
            $affiliation->guardReferences();
        });

        static::deleting(function (Affiliation $affiliation): void {
            $affiliation->roles()->get()->each->delete();
        });

    }

    /**
     * Save a primary affiliation while serializing primary replacement per
     * affiliatable model. The database partial unique is the final backstop.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        $this->guardReferences();

        $needsPrimarySync = $this->is_primary
            && (! $this->exists
                || $this->isDirty('is_primary')
                || $this->isDirty('affiliatable_type')
                || $this->isDirty('affiliatable_id'));

        if (! $needsPrimarySync) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options): bool {
            $this->affiliatable()->lockForUpdate()->firstOrFail();

            static::query()
                ->where('affiliatable_type', $this->affiliatable_type)
                ->where('affiliatable_id', $this->affiliatable_id)
                ->whereKeyNot($this->getKey())
                ->lockForUpdate()
                ->update(['is_primary' => false]);

            return parent::save($options);
        });
    }

    private function guardReferences(): void
    {
        app(PersonsModelReferenceGuard::class)->resolve(
            ModelResolver::institutionClass(),
            $this->getAttribute('institution_id'),
            'affiliation institution',
        );
    }

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
     * @return BelongsTo<Model, $this>
     */
    public function institution(): BelongsTo
    {
        /** @var BelongsTo<Model, $this> $relation */
        $relation = $this->belongsTo(ModelResolver::requireInstitutionClass(), 'institution_id');

        return $relation;
    }

    /**
     * @return HasMany<AffiliationRole, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(AffiliationRole::class, 'affiliation_id');
    }
}
