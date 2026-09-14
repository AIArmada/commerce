<?php

declare(strict_types=1);

use AIArmada\Persons\Actions\AssignCredentialAction;
use AIArmada\Persons\Actions\AssignTitleAction;
use AIArmada\Persons\Actions\CreatePersonAction;
use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Enums\CredentialType;
use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Enums\PersonStatus;
use AIArmada\Persons\Enums\TitleUsagePosition;
use AIArmada\Persons\Models\CredentialAssignment;
use AIArmada\Persons\Models\CredentialDefinition;
use AIArmada\Persons\Models\Person;
use AIArmada\Persons\Models\PersonName;
use AIArmada\Persons\Models\Title;
use AIArmada\Persons\Models\TitleAssignment;
use AIArmada\Persons\Models\TitleCategory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function (): void {
    persons_register_morph_map('person');
});

describe('person integrity guards', function (): void {
    it('rejects missing or blank names in CreatePersonAction', function (): void {
        expect(fn () => app(CreatePersonAction::class)->execute([]))
            ->toThrow(InvalidArgumentException::class, 'name is required');

        expect(fn () => app(CreatePersonAction::class)->execute(['name' => '   ']))
            ->toThrow(InvalidArgumentException::class, 'name is required');

        expect(fn () => app(CreatePersonAction::class)->execute(['name' => str_repeat('a', 256)]))
            ->toThrow(InvalidArgumentException::class, '255');

        expect(fn () => app(CreatePersonAction::class)->execute(['name' => 'Valid', 'status' => 'bogus']))
            ->toThrow(InvalidArgumentException::class, 'status');
    });

    it('creates a person through the validated action', function (): void {
        $person = app(CreatePersonAction::class)->execute([
            'name' => 'Action Created',
            'status' => PersonStatus::Published,
        ]);

        expect($person->exists)->toBeTrue()
            ->and($person->slug)->toStartWith('action-created-')
            ->and($person->published_at)->not->toBeNull();
    });

    it('eager-loads the formatted-name graph without N+1', function (): void {
        $person = Person::create(['name' => 'Titled Person']);
        $category = TitleCategory::create(['code' => 'honour', 'name' => 'Honour', 'sort_order' => 1]);
        $title = Title::create([
            'category_id' => $category->id,
            'name' => 'Datuk',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 1,
        ]);
        TitleAssignment::create([
            'titleable_type' => 'person',
            'titleable_id' => $person->id,
            'title_id' => $title->id,
            'status' => AssignmentStatus::Active,
        ]);

        $loaded = Person::withFormattedName()->findOrFail($person->id);

        expect($loaded->relationLoaded('titleAssignments'))->toBeTrue();

        DB::enableQueryLog();
        $name = $loaded->formatted_name;
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        expect($name)->toBe('Datuk Titled Person')
            ->and($queries)->toBeEmpty();
    });

    it('enforces assignment uniqueness at the database level', function (): void {
        $titleTable = (new TitleAssignment)->getTable();
        $credentialTable = (new CredentialAssignment)->getTable();

        expect(Schema::hasIndex($titleTable, 'title_assignments_target_title_unique'))->toBeTrue()
            ->and(Schema::hasIndex($credentialTable, 'credential_assignments_target_credential_unique'))->toBeTrue();
    });

    it('returns the existing assignment instead of duplicating', function (): void {
        $person = Person::create(['name' => 'Double Assign']);
        $category = TitleCategory::create(['code' => 'academic', 'name' => 'Academic', 'sort_order' => 1]);
        $title = Title::create([
            'category_id' => $category->id,
            'name' => 'Dr.',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 1,
        ]);

        $first = app(AssignTitleAction::class)->execute($person, $title->id);
        $second = app(AssignTitleAction::class)->execute($person, $title->id);

        expect($second->id)->toBe($first->id)
            ->and(TitleAssignment::count())->toBe(1);

        expect(fn () => DB::table($first->getTable())->insert([
            'id' => (string) Str::uuid(),
            'titleable_type' => 'person',
            'titleable_id' => $person->id,
            'title_id' => $title->id,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]))->toThrow(QueryException::class);
    });

    it('rejects unknown or invalid assignment attributes', function (): void {
        $person = Person::create(['name' => 'Attr Guard']);
        $category = TitleCategory::create(['code' => 'academic', 'name' => 'Academic', 'sort_order' => 1]);
        $title = Title::create([
            'category_id' => $category->id,
            'name' => 'Dr.',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 1,
        ]);

        expect(fn () => app(AssignTitleAction::class)->execute($person, $title->id, ['title_id' => 'x']))
            ->toThrow(InvalidArgumentException::class, 'Unknown');

        expect(fn () => app(AssignTitleAction::class)->execute($person, $title->id, ['status' => 'bogus']))
            ->toThrow(InvalidArgumentException::class, 'status');

        $credential = CredentialDefinition::create([
            'name' => 'CPA',
            'credential_type' => CredentialType::ProfessionalLicense,
        ]);

        expect(fn () => app(AssignCredentialAction::class)->execute($person, $credential->id, ['status' => 'bogus']))
            ->toThrow(InvalidArgumentException::class, 'status');
    });

    it('deletes a person with all children atomically', function (): void {
        $person = Person::create(['name' => 'Cascade Delete']);
        PersonName::create([
            'person_id' => $person->id,
            'name_type' => PersonNameType::Display,
            'full_name' => 'Cascade Delete',
            'language_code' => 'en',
            'is_primary' => false,
        ]);
        $category = TitleCategory::create(['code' => 'honour', 'name' => 'Honour', 'sort_order' => 1]);
        $title = Title::create([
            'category_id' => $category->id,
            'name' => 'Datuk',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 1,
        ]);
        app(AssignTitleAction::class)->execute($person, $title->id);

        $person->delete();

        expect(Person::query()->whereKey($person->id)->exists())->toBeFalse()
            ->and(PersonName::query()->where('person_id', $person->id)->exists())->toBeFalse()
            ->and(TitleAssignment::query()->where('titleable_id', $person->id)->exists())->toBeFalse();
    });

    it('keeps derived identity fields out of mass assignment', function (): void {
        $person = Person::create([
            'name' => 'Derived Guard',
            'searchable_name' => 'forged searchable',
            'published_at' => '2026-01-01 00:00:00',
        ]);

        expect($person->searchable_name)->toBe('derived guard')
            ->and($person->published_at)->toBeNull();
    });

    it('skips existence re-checks when assignment references are unchanged', function (): void {
        $person = Person::create(['name' => 'Dirty Guard']);
        $category = TitleCategory::create(['code' => 'honour', 'name' => 'Honour', 'sort_order' => 1]);
        $title = Title::create([
            'category_id' => $category->id,
            'name' => 'Datuk',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 1,
        ]);
        $assignment = app(AssignTitleAction::class)->execute($person, $title->id);

        $assignment->status = AssignmentStatus::Revoked;

        DB::enableQueryLog();
        $assignment->save();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $selects = array_filter($queries, static fn (array $query): bool => str_starts_with(mb_strtolower($query['query']), 'select'));

        expect($selects)->toBeEmpty();
    });
});
