<?php

declare(strict_types=1);

namespace AIArmada\Persons\Support;

use AIArmada\Persons\Models\Person;
use Illuminate\Database\Eloquent\Model;
use LogicException;

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
        if (! (bool) config('persons.integrations.addressing.enabled', false)) {
            return null;
        }

        $modelClass = config('persons.models.country');

        return is_string($modelClass)
            && class_exists($modelClass)
            && is_a($modelClass, Model::class, true)
            ? $modelClass
            : null;
    }

    /**
     * @return class-string|null
     */
    public static function institutionClass(): ?string
    {
        $modelClass = config('persons.models.institution');

        return is_string($modelClass)
            && class_exists($modelClass)
            && is_a($modelClass, Model::class, true)
            ? $modelClass
            : null;
    }

    /**
     * @return class-string<Model>
     */
    public static function requireCountryClass(): string
    {
        $modelClass = self::countryClass();

        if ($modelClass === null) {
            throw new LogicException('Configure persons.models.country with a persisted Eloquent model before using country references.');
        }

        return $modelClass;
    }

    /**
     * @return class-string<Model>
     */
    public static function requireInstitutionClass(): string
    {
        $modelClass = self::institutionClass();

        if ($modelClass === null) {
            throw new LogicException('Configure persons.models.institution with a persisted Eloquent model before using institution references.');
        }

        return $modelClass;
    }
}
