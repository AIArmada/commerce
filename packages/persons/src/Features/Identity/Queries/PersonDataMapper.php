<?php

declare(strict_types=1);

namespace AIArmada\Persons\Features\Identity\Queries;

use AIArmada\Persons\Data\PersonData;
use AIArmada\Persons\Data\PersonNameData;
use AIArmada\Persons\Enums\Gender;
use AIArmada\Persons\Enums\PersonStatus;
use AIArmada\Persons\Features\Identity\Models\PersonName;
use AIArmada\Persons\Models\Person;

final readonly class PersonDataMapper
{
    public function fromPerson(Person $person): PersonData
    {
        return new PersonData(
            id: $person->id,
            name: $person->name,
            family_name: $person->family_name,
            middle_name: $person->middle_name,
            gender: $person->gender instanceof Gender ? $person->gender->value : null,
            nationality_country_id: $person->nationality_country_id,
            slug: $person->slug,
            status: $person->status instanceof PersonStatus ? $person->status->value : null,
            formatted_name: $person->formatted_name,
        );
    }

    public function fromPersonName(PersonName $name): PersonNameData
    {
        return new PersonNameData(
            id: $name->id,
            person_id: $name->person_id,
            name_type: $name->name_type->value,
            full_name: $name->full_name,
            language_code: $name->language_code,
            is_primary: $name->is_primary,
        );
    }
}
