<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Credentials\Queries;

use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;
use AIArmada\Persons\Runtime\CredentialRelationModel;

final readonly class CredentialRelationModelResolver implements CredentialRelationModel
{
    public function assignment(): string
    {
        return CredentialAssignment::class;
    }
}
