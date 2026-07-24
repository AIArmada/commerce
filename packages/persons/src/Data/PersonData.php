<?php

declare(strict_types=1);

namespace AIArmada\Persons\Data;

use AIArmada\Persons\Models\Person;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class PersonData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string | null | Optional $family_name,
        public readonly string | null | Optional $middle_name,
        public readonly string | null | Optional $gender,
        public readonly string | null | Optional $nationality_country_id,
        public readonly string | null | Optional $slug,
        public readonly string | null | Optional $status,
        public readonly string $formatted_name,
    ) {}

    public static function fromPerson(Person $person): self
    {
        return new self(
            id: $person->id,
            name: $person->name,
            family_name: $person->family_name,
            middle_name: $person->middle_name,
            gender: $person->gender,
            nationality_country_id: $person->nationality_country_id,
            slug: $person->slug,
            status: $person->status,
            formatted_name: $person->formatted_name,
        );
    }
}
