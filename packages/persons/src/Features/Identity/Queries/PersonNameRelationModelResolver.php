<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity\Queries;

use AIArmada\Persons\Features\Identity\Models\PersonName;
use AIArmada\Persons\Runtime\PersonNameRelationModel;

final readonly class PersonNameRelationModelResolver implements PersonNameRelationModel
{
    public function name(): string
    {
        return PersonName::class;
    }
}
