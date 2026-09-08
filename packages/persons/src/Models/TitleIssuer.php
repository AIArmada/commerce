<?php

declare(strict_types=1);

namespace AIArmada\Persons\Models;

use AIArmada\Persons\Enums\IssuerType;
use AIArmada\Persons\Support\ModelResolver;
use AIArmada\Persons\Support\PersonsModelReferenceGuard;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

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

    protected static function booted(): void
    {
        static::saving(function (TitleIssuer $issuer): void {
            $issuerName = $issuer->getAttribute('issuer_name');

            if (! is_string($issuerName) || mb_trim($issuerName) === '') {
                throw new InvalidArgumentException('A title issuer name is required.');
            }

            $issuerType = $issuer->getAttribute('issuer_type');

            if ($issuerType instanceof IssuerType
                && in_array($issuerType, [IssuerType::Government, IssuerType::University], true)
                && $issuer->getAttribute('institution_id') === null) {
                throw new InvalidArgumentException('Government and university title issuers require an institution.');
            }

            $guard = app(PersonsModelReferenceGuard::class);
            $guard->resolve(
                ModelResolver::countryClass(),
                $issuer->getAttribute('country_id'),
                'title issuer country',
            );
            $guard->resolve(
                ModelResolver::institutionClass(),
                $issuer->getAttribute('institution_id'),
                'title issuer institution',
            );

            $issuer->setAttribute('issuer_name', mb_trim($issuerName));
        });
    }

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

    /**
     * @return BelongsTo<Model, $this>
     */
    public function country(): BelongsTo
    {
        /** @var BelongsTo<Model, $this> $relation */
        $relation = $this->belongsTo(ModelResolver::requireCountryClass(), 'country_id');

        return $relation;
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
}
