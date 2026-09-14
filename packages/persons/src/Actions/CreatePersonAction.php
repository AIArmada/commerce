<?php

declare(strict_types=1);

namespace AIArmada\Persons\Actions;

use AIArmada\Persons\Enums\Gender;
use AIArmada\Persons\Enums\PersonStatus;
use AIArmada\Persons\Models\Person;
use InvalidArgumentException;

final class CreatePersonAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): Person
    {
        $this->validate($attributes);

        return Person::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function validate(array $attributes): void
    {
        $name = $attributes['name'] ?? null;

        if (! is_string($name) || mb_trim($name) === '') {
            throw new InvalidArgumentException('Person name is required and must be a non-empty string.');
        }

        if (mb_strlen($name) > 255) {
            throw new InvalidArgumentException('Person name must not exceed 255 characters.');
        }

        foreach (['family_name', 'middle_name'] as $key) {
            $value = $attributes[$key] ?? null;

            if ($value !== null && (! is_string($value) || mb_strlen($value) > 100)) {
                throw new InvalidArgumentException("Person {$key} must be a string of at most 100 characters.");
            }
        }

        $gender = $attributes['gender'] ?? null;

        if ($gender !== null && ! $gender instanceof Gender && Gender::tryFrom((string) $gender) === null) {
            throw new InvalidArgumentException('Person gender must be a valid gender value.');
        }

        $status = $attributes['status'] ?? null;

        if ($status !== null && ! $status instanceof PersonStatus && PersonStatus::tryFrom((string) $status) === null) {
            throw new InvalidArgumentException('Person status must be a valid person status value.');
        }

        $slug = $attributes['slug'] ?? null;

        if ($slug !== null && (! is_string($slug) || mb_trim($slug) === '' || mb_strlen($slug) > 255)) {
            throw new InvalidArgumentException('Person slug must be a non-empty string of at most 255 characters.');
        }
    }
}
