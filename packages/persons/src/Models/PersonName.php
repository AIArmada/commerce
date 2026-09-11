<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Support\ModelResolver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property string $id
 * @property string $person_id
 * @property PersonNameType $name_type
 * @property string $full_name
 * @property string $language_code
 * @property bool $is_primary
 * @property-read Person $person
 *
 * Names inherit the globality of Person. Do not scope Person without
 * scoping person_names in the same release.
 */
class PersonName extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'person_id',
        'name_type',
        'full_name',
        'language_code',
        'is_primary',
    ];

    protected static function booted(): void
    {
        static::saving(function (PersonName $name): void {
            $name->normalizeForSave();
        });
    }

    /**
     * Save a primary name while serializing primary replacement per person.
     * The parent and sibling locks are the application-level race backstop;
     * the partial unique index is the database-level invariant.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        $this->normalizeForSave();

        $needsPrimarySync = $this->is_primary
            && (! $this->exists
                || $this->isDirty('is_primary')
                || $this->isDirty('person_id')
                || $this->isDirty('name_type')
                || $this->isDirty('language_code'));

        if (! $needsPrimarySync) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options): bool {
            $this->person()->lockForUpdate()->firstOrFail();

            static::query()
                ->where('person_id', $this->person_id)
                ->where('name_type', $this->name_type instanceof PersonNameType
                    ? $this->name_type->value
                    : (string) $this->getAttribute('name_type'))
                ->where('language_code', $this->language_code)
                ->whereKeyNot($this->getKey())
                ->lockForUpdate()
                ->update(['is_primary' => false]);

            $saved = parent::save($options);

            return $saved;
        });
    }

    private function normalizeForSave(): void
    {
        $this->full_name = mb_trim($this->full_name);
        $this->language_code = mb_strtolower(mb_trim($this->language_code));
    }

    public function getTable(): string
    {
        return config('persons.database.tables.person_names', 'person_names');
    }

    protected function casts(): array
    {
        return [
            'name_type' => PersonNameType::class,
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(ModelResolver::personClass(), 'person_id');
    }
}
