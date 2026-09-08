<?php

declare(strict_types=1);

namespace AIArmada\Persons\Actions;

use AIArmada\Persons\Models\Person;

final class CreatePersonAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function execute(array $attributes): Person
    {
        return Person::query()->create($attributes);
    }
}
