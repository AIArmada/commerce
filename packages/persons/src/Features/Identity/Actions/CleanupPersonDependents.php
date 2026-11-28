<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity\Actions;

use AIArmada\Persons\Features\Affiliations\Api\PersonAffiliationsCleaner;
use AIArmada\Persons\Features\Credentials\Api\PersonCredentialsCleaner;
use AIArmada\Persons\Features\Identity\Api\PersonDependentsCleaner;
use AIArmada\Persons\Features\Identity\Models\PersonName;
use AIArmada\Persons\Features\Titles\Api\PersonTitlesCleaner;
use AIArmada\Persons\Support\ModelResolver;
use InvalidArgumentException;
use RuntimeException;

final readonly class CleanupPersonDependents implements PersonDependentsCleaner
{
    public function __construct(
        private PersonTitlesCleaner $titles,
        private PersonCredentialsCleaner $credentials,
        private PersonAffiliationsCleaner $affiliations,
    ) {}

    public function cleanup(string $personId, string $personType): void
    {
        $model = new (ModelResolver::personClass());

        $model->getConnection()->transaction(function () use ($model, $personId, $personType): void {
            $person = $model->newQuery()->whereKey($personId)->lockForUpdate()->firstOrFail();

            if ($person->getMorphClass() !== $personType) {
                throw new InvalidArgumentException('Person cleanup requires the configured person morph type.');
            }

            $person->names()->eachById(static function (PersonName $name): void {
                if (! $name->delete()) {
                    throw new RuntimeException('Person dependent deletion was cancelled.');
                }
            }, 500);
            $this->titles->cleanup($personId, $personType);
            $this->credentials->cleanup($personId, $personType);
            $this->affiliations->cleanup($personId, $personType);
        });
    }
}
