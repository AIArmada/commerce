<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\Gender;
use AIArmada\Persons\Enums\TitleUsagePosition;
use AIArmada\Persons\Traits\HasAffiliations;
use AIArmada\Persons\Traits\HasCredentials;
use AIArmada\Persons\Traits\HasTitles;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PersonName> $names
 * @property-read string $formatted_name
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
    ];

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
        ];
    }

    /**
     * @return HasMany<PersonName, $this>
     */
    public function names(): HasMany
    {
        return $this->hasMany(PersonName::class, 'person_id');
    }

    /**
     * Display name with ordered titles, e.g. "Datuk Dr. Ahmad Rahman, PhD".
     * Sort order is global within each usage_position scope (before/after name).
     */
    public function getFormattedNameAttribute(): string
    {
        $assignments = $this->relationLoaded('titleAssignments')
            ? $this->getRelation('titleAssignments')
            : $this->titleAssignments()->where('status', 'active')->with('title')->get();

        /** @var Collection<int, TitleAssignment> $assignments */
        $before = $assignments
            ->filter(fn (TitleAssignment $a) => $a->title->usage_position === TitleUsagePosition::BeforeName)
            ->sortBy('title.sort_order')
            ->map(fn (TitleAssignment $a) => $a->title->short_form ?? $a->title->name);

        $after = $assignments
            ->filter(fn (TitleAssignment $a) => $a->title->usage_position === TitleUsagePosition::AfterName)
            ->sortBy('title.sort_order')
            ->map(fn (TitleAssignment $a) => $a->title->short_form ?? $a->title->name);

        $name = mb_trim(implode(' ', $before->all()) . ' ' . $this->name);

        if ($after->isNotEmpty()) {
            $name .= ', ' . implode(', ', $after->all());
        }

        return mb_trim($name);
    }
}
