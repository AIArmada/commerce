<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use AIArmada\Persons\Models\PersonName;
use Spatie\LaravelData\Data;

final class PersonNameData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $person_id,
        public readonly string $name_type,
        public readonly string $full_name,
        public readonly string $language_code,
        public readonly bool $is_primary,
    ) {}

    public static function fromPersonName(PersonName $name): self
    {
        return new self(
            id: $name->id,
            person_id: $name->person_id,
            name_type: $name->name_type->value,
            full_name: $name->full_name,
            language_code: $name->language_code,
            is_primary: $name->is_primary,
        );
    }
}
