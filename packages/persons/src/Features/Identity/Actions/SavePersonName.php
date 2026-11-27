<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity\Actions;

use AIArmada\Persons\Features\Identity\Models\PersonName;
use AIArmada\Persons\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

final readonly class SavePersonName
{
    /** @param array<string, mixed> $attributes */
    public function create(Person $person, array $attributes): PersonName
    {
        return $person->getConnection()->transaction(function () use ($person, $attributes): PersonName {
            $locked = $this->lockPerson($person);
            $name = $locked->names()->create($this->attributes($attributes));
            $locked->unsetRelation('names')->save();

            return $name;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Person $person, PersonName $name, array $attributes): PersonName
    {
        return $person->getConnection()->transaction(function () use ($person, $name, $attributes): PersonName {
            $locked = $this->lockPerson($person);
            $record = $this->lockName($locked, $name);
            $record->fill($this->attributes($attributes))->save();
            $locked->unsetRelation('names')->save();

            return $record;
        });
    }

    public function delete(Person $person, PersonName $name): bool
    {
        return $person->getConnection()->transaction(function () use ($person, $name): bool {
            $locked = $this->lockPerson($person);
            $record = $this->lockName($locked, $name);
            $deleted = (bool) $record->delete();
            $locked->unsetRelation('names')->save();

            return $deleted;
        });
    }

    private function lockPerson(Person $person): Person
    {
        if (! $person->exists || $person->getKey() === null) {
            throw new InvalidArgumentException('Person must be persisted.');
        }

        return $person->newQuery()->whereKey($person->getKey())->lockForUpdate()->firstOrFail();
    }

    private function lockName(Person $person, PersonName $name): PersonName
    {
        $record = $person->names()->whereKey($name->getKey())->lockForUpdate()->first();

        if ($record === null) {
            throw new AuthorizationException('The name does not belong to this person.');
        }

        return $record;
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function attributes(array $attributes): array
    {
        return array_intersect_key($attributes, array_flip(['name_type', 'full_name', 'language_code', 'is_primary']));
    }
}
