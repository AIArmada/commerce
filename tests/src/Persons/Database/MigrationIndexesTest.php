<?php

declare(strict_types=1);

use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Models\Person;
use AIArmada\Persons\Models\PersonName;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('keeps primary application guards and rejects direct duplicate writes', function (): void {
    $person = Person::create([
        'name' => 'Indexed Identity',
        'slug' => 'indexed-identity',
    ]);

    $first = PersonName::create([
        'person_id' => $person->getKey(),
        'name_type' => PersonNameType::Display,
        'full_name' => 'First Display Name',
        'language_code' => 'en',
        'is_primary' => true,
    ]);
    $second = PersonName::create([
        'person_id' => $person->getKey(),
        'name_type' => PersonNameType::Display,
        'full_name' => 'Second Display Name',
        'language_code' => 'en',
        'is_primary' => true,
    ]);

    expect($first->fresh()?->is_primary)->toBeFalse()
        ->and($second->fresh()?->is_primary)->toBeTrue();

    expect(fn () => DB::table((new Person)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'name' => 'Direct duplicate slug',
        'slug' => $person->slug,
        'searchable_name' => 'direct duplicate slug',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table((new PersonName)->getTable())->insert([
        'id' => (string) Str::uuid(),
        'person_id' => $person->getKey(),
        'name_type' => PersonNameType::Display->value,
        'full_name' => 'Direct duplicate primary',
        'language_code' => 'en',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
