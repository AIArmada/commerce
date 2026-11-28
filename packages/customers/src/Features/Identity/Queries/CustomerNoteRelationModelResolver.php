<?php

declare(strict_types=1);

namespace AIArmada\Customers\Features\Identity\Queries;

use AIArmada\Customers\Features\Identity\Models\CustomerNote;
use AIArmada\Customers\Runtime\CustomerNoteRelationModel;

final readonly class CustomerNoteRelationModelResolver implements CustomerNoteRelationModel
{
    public function note(): string
    {
        return CustomerNote::class;
    }
}
