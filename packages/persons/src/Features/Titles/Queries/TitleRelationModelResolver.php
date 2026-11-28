<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Titles\Queries;

use AIArmada\Persons\Features\Titles\Models\TitleAssignment;
use AIArmada\Persons\Runtime\TitleRelationModel;

final readonly class TitleRelationModelResolver implements TitleRelationModel
{
    public function assignment(): string
    {
        return TitleAssignment::class;
    }
}
