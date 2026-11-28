<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity\Actions;

use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Features\Identity\Models\PersonName;
use AIArmada\Persons\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use RuntimeException;

final readonly class SavePersonName
{
    /** @param array<string, mixed> $attributes */
    public function create(Person $person, array $attributes): PersonName
    {
        return $person->getConnection()->transaction(function () use ($person, $attributes): PersonName {
            $locked = $this->lockPerson($person);
            $name = $locked->names()->make($this->attributes($attributes));
            $this->persist($locked, $name);
            $locked->unsetRelation('names');
            app(PersistPerson::class)->execute($locked);

            return $name;
        });
    }

    /** @param array<string, mixed> $attributes */
    public function update(Person $person, PersonName $name, array $attributes): PersonName
    {
        return $person->getConnection()->transaction(function () use ($person, $name, $attributes): PersonName {
            $locked = $this->lockPerson($person);
            $record = $this->lockName($locked, $name);
            $record->fill($this->attributes($attributes));
            $this->persist($locked, $record);
            $locked->unsetRelation('names');
            app(PersistPerson::class)->execute($locked);

            return $record;
        });
    }

    public function delete(Person $person, PersonName $name): bool
    {
        return $person->getConnection()->transaction(function () use ($person, $name): bool {
            $locked = $this->lockPerson($person);
            $record = $this->lockName($locked, $name);
            if (! $record->delete()) {
                throw new RuntimeException('Person name deletion was cancelled.');
            }
            $locked->unsetRelation('names');
            app(PersistPerson::class)->execute($locked);

            return true;
        });
    }

    private function persist(Person $person, PersonName $name): void
    {
        $name->full_name = mb_trim((string) $name->full_name);
        $name->language_code = mb_strtolower(mb_trim((string) $name->language_code));

        if ($name->full_name === '' || $name->language_code === '' || ! $name->name_type instanceof PersonNameType) {
            throw new InvalidArgumentException('A name, language code and valid name type are required.');
        }

        if ($name->is_primary) {
            $siblings = $person->names()
                ->where('name_type', $name->name_type->value)
                ->where('language_code', $name->language_code)
                ->where('is_primary', true);

            if ($name->exists) {
                $siblings->whereKeyNot($name->getKey());
            }

            $siblings->update(['is_primary' => false]);
        }

        if (! $name->save()) {
            throw new RuntimeException('Person name persistence was cancelled.');
        }
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
