<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Support\ModelResolver;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $person_id
 * @property PersonNameType $name_type
 * @property string $full_name
 * @property string $language_code
 * @property bool $is_primary
 * @property-read Person $person
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
