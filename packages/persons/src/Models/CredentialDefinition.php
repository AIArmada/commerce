<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\CredentialType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string|null $short_form
 * @property string|null $field
 * @property CredentialType $credential_type
 * @property string|null $language_code
 * @property-read Collection<int, CredentialAssignment> $assignments
 */
class CredentialDefinition extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'short_form',
        'field',
        'credential_type',
        'language_code',
    ];

    public function getTable(): string
    {
        return config('persons.database.tables.credential_definitions', 'credential_definitions');
    }

    protected function casts(): array
    {
        return [
            'credential_type' => CredentialType::class,
        ];
    }

    /**
     * @return HasMany<CredentialAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(CredentialAssignment::class, 'credential_id');
    }
}
