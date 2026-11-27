<?php

declare(strict_types=1);

use AIArmada\Persons\Data\PersonData;
use AIArmada\Persons\Enums\PersonStatus;
use AIArmada\Persons\Features\Identity\Queries\PersonDataMapper;
use AIArmada\Persons\Models\Person;

it('maps person records into immutable framework-independent data', function (): void {
    persons_register_morph_map('person');
    $person = Person::factory()->create(['name' => 'Mapped Person', 'status' => PersonStatus::Active]);
    $data = app(PersonDataMapper::class)->fromPerson($person);

    expect($data)->toBeInstanceOf(PersonData::class)
        ->and($data->id)->toBe($person->id)
        ->and($data->name)->toBe('Mapped Person')
        ->and($data->status)->toBe('active')
        ->and($data->toArray())->toBe($data->jsonSerialize())
        ->and((new ReflectionClass(PersonData::class))->isReadOnly())->toBeTrue();
});
