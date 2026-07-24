<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use AIArmada\Persons\Models\CredentialDefinition;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class CredentialDefinitionData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string | null | Optional $short_form,
        public readonly string | null | Optional $field,
        public readonly string $credential_type,
    ) {}

    public static function fromCredentialDefinition(CredentialDefinition $definition): self
    {
        return new self(
            id: $definition->id,
            name: $definition->name,
            short_form: $definition->short_form,
            field: $definition->field,
            credential_type: $definition->credential_type->value,
        );
    }
}
