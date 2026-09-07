<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Enums\Gender;
use AIArmada\Persons\Enums\TitleUsagePosition;
use AIArmada\Persons\Traits\HasAffiliations;
use AIArmada\Persons\Traits\HasCredentials;
use AIArmada\Persons\Traits\HasTitles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

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
 * @property string|null $status
 * @property CarbonImmutable|null $published_at
 * @property-read EloquentCollection<int, PersonName> $names
 * @property-read string $formatted_name
 *
 * Person is the shared, global human-identity root. It deliberately has no
 * owner scope; tenant-owned profiles link to it without changing that rule.
 */
class Person extends Model
{
    use HasAffiliations;
    use HasCredentials;
    use HasFactory;
    use HasTitles;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'family_name',
        'middle_name',
        'gender',
        'date_of_birth',
        'nationality_country_id',
        'slug',
        'searchable_name',
        'bio',
        'status',
        'published_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (Person $person): void {
            $person->normalizeIdentityValues();
        });

        static::deleting(function (Person $person): void {
            $person->names()->get()->each->delete();
            $person->titleAssignments()->get()->each->delete();
            $person->credentialAssignments()->get()->each->delete();
            $person->affiliations()->get()->each->delete();
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
            'published_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<PersonName, $this>
     */
    public function names(): HasMany
    {
        return $this->hasMany(PersonName::class, 'person_id');
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
            /** @var EloquentCollection<int, PersonName> $names */
            $names = $this->getRelation('names');
        } else {
            /** @var EloquentCollection<int, PersonName> $names */
            $names = $this->names()->where('is_primary', true)->get();
        }

        return $names
            ->filter(static fn (PersonName $name): bool => $name->is_primary)
            ->map(static fn (PersonName $name): string => mb_trim($name->full_name))
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
        if ($this->relationLoaded('titleAssignments')) {
            $assignments = $this->getRelation('titleAssignments');
        } else {
            $assignments = $this->titleAssignments()
                ->where('status', AssignmentStatus::Active)
                ->with('title.category')
                ->get();

            // Accessors can be evaluated more than once by serializers and
            // form components. Keep the resolved collection on this model
            // so repeated reads do not issue the same relationship query.
            $this->setRelation('titleAssignments', $assignments);
        }

        /** @var EloquentCollection<int, TitleAssignment> $assignments */
        $assignments->loadMissing('title.category');
        $assignments = $assignments
            ->filter(fn (TitleAssignment $assignment): bool => $assignment->status === AssignmentStatus::Active
                && $assignment->title !== null
                && $assignment->title->category !== null)
            ->values();

        $before = $assignments
            ->filter(fn (TitleAssignment $a) => $a->title->usage_position === TitleUsagePosition::BeforeName)
            ->sort($this->compareTitleAssignments(...))
            ->map(fn (TitleAssignment $a) => $a->title->short_form ?? $a->title->name);

        $after = $assignments
            ->filter(fn (TitleAssignment $a) => $a->title->usage_position === TitleUsagePosition::AfterName)
            ->sort($this->compareTitleAssignments(...))
            ->map(fn (TitleAssignment $a) => $a->title->short_form ?? $a->title->name);

        $name = mb_trim(implode(' ', $before->all()) . ' ' . $this->name);

        if ($after->isNotEmpty()) {
            $name .= ', ' . implode(', ', $after->all());
        }

        return mb_trim($name);
    }

    private function compareTitleAssignments(TitleAssignment $left, TitleAssignment $right): int
    {
        $categoryOrder = $left->title->category->sort_order <=> $right->title->category->sort_order;

        if ($categoryOrder !== 0) {
            return $categoryOrder;
        }

        $titleOrder = $left->title->sort_order <=> $right->title->sort_order;

        return $titleOrder !== 0
            ? $titleOrder
            : ((string) $left->title->getKey() <=> (string) $right->title->getKey());
    }
}
