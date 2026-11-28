<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\CommerceSupport\Concerns\ParsesPostgresTimestamps;
use AIArmada\CommerceSupport\Persistence\DeletesAtomically;
use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Enums\Gender;
use AIArmada\Persons\Enums\PersonStatus;
use AIArmada\Persons\Features\Identity\Api\PersonDependentsCleaner;
use AIArmada\Persons\Runtime\PersonNameRelationModel;
use AIArmada\Persons\Runtime\PersonTitleFormatting;
use AIArmada\Persons\Support\ModelResolver;
use AIArmada\Persons\Support\PersonsModelReferenceGuard;
use AIArmada\Persons\Traits\HasAffiliations;
use AIArmada\Persons\Traits\HasCredentials;
use AIArmada\Persons\Traits\HasTitles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property string $id
 * @property string $name
 * @property string|null $family_name
 * @property string|null $middle_name
 * @property string|null $gender
 * @property CarbonImmutable|null $date_of_birth
 * @property string|null $nationality_country_id
 * @property string|null $slug
 * @property string|null $searchable_name
 * @property array|null $bio
 * @property PersonStatus $status
 * @property bool $is_featured
 * @property int|null $order_column
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $verified_at
 * @property string|null $verified_by
 * @property CarbonImmutable|null $rejected_at
 * @property CarbonImmutable|null $last_state_change_at
 * @property-read EloquentCollection<int, RelatedPersonName> $names
 * @property-read string $formatted_name
 *
 * Person is the shared, global human-identity root. It deliberately has no
 * owner scope; tenant-owned profiles link to it without changing that rule.
 *
 * @phpstan-type RelatedPersonName \AIArmada\Persons\Features\Identity\Models\PersonName
 */
class Person extends Model implements Sortable
{
    use DeletesAtomically;
    use HasAffiliations;
    use HasCredentials;
    use HasFactory;
    use HasTitles;
    use HasUuids;
    use ParsesPostgresTimestamps;
    use SortableTrait;

    /** @var array<string, mixed> */
    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => false,
    ];

    protected $attributes = [
        'status' => PersonStatus::Active->value,
        'is_featured' => false,
    ];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'family_name',
        'middle_name',
        'gender',
        'date_of_birth',
        'nationality_country_id',
        'slug',
        'bio',
        'status',
        'is_featured',
        'order_column',
        'verified_at',
        'verified_by',
        'rejected_at',
        'last_state_change_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (Person $person): void {
            $person->guardReferences();
            $person->validateBio();
            $person->syncStatusLifecycle();
            $person->normalizeIdentityValues();
            $person->syncFeaturedOrder();
        });

        static::deleting(function (Person $person): void {
            app(PersonDependentsCleaner::class)->cleanup((string) $person->getKey(), $person->getMorphClass());
        });
    }

    public function getTable(): string
    {
        return config('persons.database.tables.persons', 'persons');
    }

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'immutable_date',
            'bio' => 'array',
            'gender' => Gender::class,
            'status' => PersonStatus::class,
            'is_featured' => 'boolean',
            'order_column' => 'integer',
            'published_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'last_state_change_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Model, $this>
     */
    public function nationalityCountry(): BelongsTo
    {
        /** @var BelongsTo<Model, $this> $relation */
        $relation = $this->belongsTo(ModelResolver::requireCountryClass(), 'nationality_country_id');

        return $relation;
    }

    /**
     * @return HasMany<RelatedPersonName, $this>
     */
    public function names(): HasMany
    {
        return $this->hasMany(app(PersonNameRelationModel::class)->name(), 'person_id');
    }

    /**
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    /**
     * Eager-load the title graph consumed by `formatted_name` so list pages
     * avoid one query per person. The accessor stays pure (it never populates
     * relations itself); callers opt in via `Person::withFormattedName()->get()`.
     *
     * @param  Builder<Person>  $query
     * @return Builder<Person>
     */
    public function scopeWithFormattedName(Builder $query): Builder
    {
        return $query->with(['titleAssignments' => fn ($assignments): mixed => $assignments
            ->where('status', AssignmentStatus::Active)
            ->with('title.category')]);
    }

    /**
     * Assign a curated position the first time a person is featured, and
     * append a new position when a previously featured person is re-featured.
     * Explicit positions supplied on create are preserved.
     */
    protected function syncFeaturedOrder(): void
    {
        if (! $this->is_featured) {
            return;
        }

        if ($this->order_column !== null && ! ($this->exists && $this->isDirty('is_featured'))) {
            return;
        }

        $this->setHighestOrderNumber();
    }

    private function normalizeIdentityValues(): void
    {
        if ($this->getKey() === null) {
            $this->setAttribute($this->getKeyName(), (string) Str::orderedUuid());
        }

        $this->name = mb_trim((string) $this->name);

        $explicitSlug = is_string($this->slug) && mb_trim($this->slug) !== '';
        $base = Str::slug($explicitSlug ? $this->slug : $this->name) ?: 'person';
        $key = mb_substr((string) $this->getKey(), 0, 8);
        $candidate = $explicitSlug
            ? $base
            : ($key !== '' ? $base . '-' . $key : $base);
        $suffix = 2;
        $found = false;

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $query = static::query()->where('slug', $candidate);

            if ($this->exists) {
                $query->whereKeyNot($this->getKey());
            }

            if (! $query->exists()) {
                $this->slug = $candidate;
                $found = true;

                break;
            }

            $candidate = $explicitSlug
                ? $base . '-' . $suffix
                : $base . '-' . $key . '-' . $suffix;
            $suffix++;
        }

        if (! $found) {
            throw new LogicException('Person slug generation exhausted its collision budget.');
        }

        $parts = array_filter([
            $this->name,
            $this->family_name,
            $this->middle_name,
            ...$this->primaryNameValues(),
        ], static fn (mixed $value): bool => is_string($value) && mb_trim($value) !== '');

        $this->searchable_name = mb_strtolower(mb_trim(implode(' ', $parts)));
    }

    /**
     * @return list<string>
     */
    private function primaryNameValues(): array
    {
        if ($this->relationLoaded('names')) {
            /** @var EloquentCollection<int, RelatedPersonName> $names */
            $names = $this->getRelation('names');
        } else {
            /** @var EloquentCollection<int, RelatedPersonName> $names */
            $names = $this->names()->where('is_primary', true)->get();
        }

        return $names
            ->filter(static fn ($name): bool => $name->is_primary)
            ->map(static fn ($name): string => mb_trim($name->full_name))
            ->filter(static fn (string $name): bool => $name !== '')
            ->values()
            ->all();
    }

    /**
     * Display name with ordered titles, e.g. "Datuk Dr. Ahmad Rahman, PhD".
     * Titles are ordered by category sort order, then title sort order, within
     * each usage_position scope (before/after name).
     */
    public function getFormattedNameAttribute(): string
    {
        return app(PersonTitleFormatting::class)->format($this);
    }

    /**
     * Mutate the status lifecycle in memory only; the caller must save.
     * (Persisting here would recurse via the `saving` → `syncStatusLifecycle` path.)
     */
    public function transitionStatus(PersonStatus $status, ?CarbonImmutable $at = null): void
    {
        $this->status = $status;
        $this->published_at = $status === PersonStatus::Published
            ? ($at ?? CarbonImmutable::now())
            : null;
    }

    private function syncStatusLifecycle(): void
    {
        if ($this->status === PersonStatus::Published && $this->published_at === null) {
            $this->transitionStatus(PersonStatus::Published);

            return;
        }

        if ($this->status !== PersonStatus::Published && $this->published_at !== null) {
            $this->transitionStatus($this->status);
        }
    }

    private function guardReferences(): void
    {
        $guard = app(PersonsModelReferenceGuard::class);
        $guard->resolve(
            ModelResolver::countryClass(),
            $this->getAttribute('nationality_country_id'),
            'person nationality country',
        );
    }

    private function validateBio(): void
    {
        $bio = $this->getAttribute('bio');

        if ($bio === null) {
            return;
        }

        if (! is_array($bio)) {
            throw new InvalidArgumentException('Person bio must be a locale map or a list of locale/text entries.');
        }

        if (array_is_list($bio)) {
            foreach ($bio as $entry) {
                if (! is_array($entry)
                    || ! is_string($entry['locale'] ?? null)
                    || mb_trim($entry['locale']) === ''
                    || ! is_string($entry['text'] ?? null)) {
                    throw new InvalidArgumentException('Each person bio entry must contain a locale and text string.');
                }
            }

            return;
        }

        foreach ($bio as $locale => $text) {
            if (! is_string($locale) || mb_trim($locale) === '' || ! is_string($text)) {
                throw new InvalidArgumentException('Person bio locale maps must contain non-empty string keys and text values.');
            }
        }
    }
}
