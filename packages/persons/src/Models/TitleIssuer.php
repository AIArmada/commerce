<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\IssuerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string|null $country_id
 * @property string|null $institution_id
 * @property string $issuer_name
 * @property IssuerType $issuer_type
 *
 * `country_id` and `institution_id` are loose UUID columns. Relations into the
 * host application's Country/Institution models are wired at the application
 * layer (see docs/04-usage.md), keeping this package standalone.
 */
class TitleIssuer extends Model
{
    use HasFactory;
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'country_id',
        'institution_id',
        'issuer_name',
        'issuer_type',
    ];

    public function getTable(): string
    {
        return config('persons.database.tables.title_issuers', 'title_issuers');
    }

    protected function casts(): array
    {
        return [
            'issuer_type' => IssuerType::class,
        ];
    }
}
