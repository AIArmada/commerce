<?php

declare(strict_types=1);

namespace AIArmada\Persons\Support;

use AIArmada\Persons\Models\Person;

/**
 * Resolves host application model subclasses configured for the persons package.
 */
final class ModelResolver
{
    /**
     * @return class-string<Person>
     */
    public static function personClass(): string
    {
        /** @var class-string<Person> $modelClass */
        $modelClass = config('persons.models.person', Person::class);

        return $modelClass;
    }

    /**
     * @return class-string|null
     */
    public static function countryClass(): ?string
    {
        $modelClass = config('persons.models.country');

        return is_string($modelClass) && class_exists($modelClass) ? $modelClass : null;
    }

    /**
     * @return class-string|null
     */
    public static function institutionClass(): ?string
    {
        $modelClass = config('persons.models.institution');

        return is_string($modelClass) && class_exists($modelClass) ? $modelClass : null;
    }
}
