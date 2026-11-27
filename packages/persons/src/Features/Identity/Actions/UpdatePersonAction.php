<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity\Actions;

use AIArmada\Persons\Models\Person;

final readonly class UpdatePersonAction
{
    /** @param array<string, mixed> $attributes */
    public function execute(Person $person, array $attributes): Person
    {
        return $person->getConnection()->transaction(function () use ($person, $attributes): Person {
            $locked = $person->newQuery()->whereKey($person->getKey())->lockForUpdate()->firstOrFail();
            $locked->fill($attributes);
            app(PersistPerson::class)->execute($locked);

            return $locked;
        });
    }
}
