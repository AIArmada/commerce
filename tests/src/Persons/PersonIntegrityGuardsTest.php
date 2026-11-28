<?php

declare(strict_types=1);

use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Enums\CredentialType;
use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Enums\PersonStatus;
use AIArmada\Persons\Enums\TitleUsagePosition;
use AIArmada\Persons\Features\Credentials\Actions\AssignCredentialAction;
use AIArmada\Persons\Features\Credentials\Actions\DeleteCredentialDefinition;
use AIArmada\Persons\Features\Credentials\Models\CredentialAssignment;
use AIArmada\Persons\Features\Credentials\Models\CredentialDefinition;
use AIArmada\Persons\Features\Identity\Actions\CreatePersonAction;
use AIArmada\Persons\Features\Identity\Actions\DeletePersonAction;
use AIArmada\Persons\Features\Identity\Actions\SavePersonName;
use AIArmada\Persons\Features\Identity\Models\PersonName;
use AIArmada\Persons\Features\Titles\Actions\AssignTitleAction;
use AIArmada\Persons\Features\Titles\Actions\DeleteTitle;
use AIArmada\Persons\Features\Titles\Actions\DeleteTitleIssuer;
use AIArmada\Persons\Features\Titles\Actions\ReorderTitleAction;
use AIArmada\Persons\Features\Titles\Models\Title;
use AIArmada\Persons\Features\Titles\Models\TitleAssignment;
use AIArmada\Persons\Features\Titles\Models\TitleCategory;
use AIArmada\Persons\Features\Titles\Models\TitleIssuer;
use AIArmada\Persons\Models\Person;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        app(SavePersonName::class)->create($person, [
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

        expect(app(DeletePersonAction::class)->execute($person))->toBeTrue();

        expect(Person::query()->whereKey($person->id)->exists())->toBeFalse()
            ->and(PersonName::query()->where('person_id', $person->id)->exists())->toBeFalse()
            ->and(TitleAssignment::query()->where('titleable_id', $person->id)->exists())->toBeFalse();
    });

    it('rolls back deletion when a dependent record cannot be deleted', function (): void {
        $person = Person::create(['name' => 'Deletion Veto']);
        $name = app(SavePersonName::class)->create($person, [
            'name_type' => PersonNameType::Display,
            'full_name' => 'Deletion Veto',
            'language_code' => 'en',
            'is_primary' => false,
        ]);
        $category = TitleCategory::create(['code' => 'veto', 'name' => 'Veto', 'sort_order' => 1]);
        $title = Title::create([
            'category_id' => $category->id,
            'name' => 'Dr.',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 1,
        ]);
        $assignment = app(AssignTitleAction::class)->execute($person, $title->id);

        TitleAssignment::deleting(static fn (TitleAssignment $record): bool => $record->id !== $assignment->id);

        expect(fn () => app(DeletePersonAction::class)->execute($person))
            ->toThrow(RuntimeException::class, 'Person dependent deletion was cancelled.');

        expect(Person::query()->whereKey($person->id)->exists())->toBeTrue()
            ->and(PersonName::query()->whereKey($name->id)->exists())->toBeTrue()
            ->and(TitleAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue();
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

it('rolls back title deletion when the parent deletion is cancelled', function (): void {
    $person = Person::create(['name' => 'Title Deletion Veto']);
    $category = TitleCategory::create(['code' => 'title-veto', 'name' => 'Title Veto', 'sort_order' => 1]);
    $title = Title::create([
        'category_id' => $category->id,
        'name' => 'Dr.',
        'usage_position' => TitleUsagePosition::BeforeName,
        'sort_order' => 1,
    ]);
    $assignment = app(AssignTitleAction::class)->execute($person, $title->id);

    Title::deleting(static fn (Title $record): bool => $record->id !== $title->id);

    expect(fn () => app(DeleteTitle::class)->execute($title))
        ->toThrow(RuntimeException::class, 'Title deletion was cancelled.');

    expect(Title::query()->whereKey($title->id)->exists())->toBeTrue()
        ->and(TitleAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue();
});

it('rolls back credential definition deletion when an assignment vetoes deletion', function (): void {
    $person = Person::create(['name' => 'Credential Deletion Veto']);
    $definition = CredentialDefinition::create([
        'name' => 'CPA',
        'credential_type' => CredentialType::ProfessionalLicense,
    ]);
    $assignment = app(AssignCredentialAction::class)->execute($person, $definition->id);

    CredentialAssignment::deleting(static fn (CredentialAssignment $record): bool => $record->id !== $assignment->id);

    expect(fn () => app(DeleteCredentialDefinition::class)->execute($definition))
        ->toThrow(RuntimeException::class, 'CredentialDefinition assignment deletion was cancelled.');

    expect(CredentialDefinition::query()->whereKey($definition->id)->exists())->toBeTrue()
        ->and(CredentialAssignment::query()->whereKey($assignment->id)->exists())->toBeTrue();
});

it('rejects updates and deletes of another person title assignment', function (): void {
    $person = Person::create(['name' => 'Title Owner']);
    $other = Person::create(['name' => 'Other Person']);
    $category = TitleCategory::create(['code' => 'assignment-guard', 'name' => 'Assignment Guard', 'sort_order' => 1]);
    $title = Title::create([
        'category_id' => $category->id,
        'name' => 'Dr.',
        'usage_position' => TitleUsagePosition::BeforeName,
        'sort_order' => 1,
    ]);
    $action = app(AssignTitleAction::class);
    $assignment = $action->execute($person, $title->id);

    expect(fn () => $action->update($other, $assignment, ['status' => 'revoked']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $action->delete($other, $assignment))
        ->toThrow(AuthorizationException::class);

    expect($action->update($person, $assignment, ['status' => 'revoked'])->status)->toBe(AssignmentStatus::Revoked);
    expect($action->delete($person, $assignment))->toBeTrue();
});

it('rejects invalid credential dates and cross-person assignment writes', function (): void {
    $person = Person::create(['name' => 'Credential Owner']);
    $other = Person::create(['name' => 'Other Credential Person']);
    $definition = CredentialDefinition::create([
        'name' => 'CPA',
        'credential_type' => CredentialType::ProfessionalLicense,
    ]);
    $action = app(AssignCredentialAction::class);

    expect(fn () => $action->execute($person, $definition->id, [
        'date_obtained' => '2026-01-02',
        'date_expired' => '2026-01-01',
    ]))->toThrow(InvalidArgumentException::class, 'The expiry date');

    $assignment = $action->execute($person, $definition->id);
    expect(fn () => $action->update($other, $assignment, ['status' => 'revoked']))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $action->delete($other, $assignment))
        ->toThrow(AuthorizationException::class);

    expect($action->update($person, $assignment, ['status' => 'revoked'])->status)->toBe(AssignmentStatus::Revoked);
    expect($action->delete($person, $assignment))->toBeTrue();
});

it('preserves the current title order when updating an outdated record without an ordering change', function (): void {
    $category = TitleCategory::create(['code' => 'stale-order', 'name' => 'Stale Order', 'sort_order' => 1]);
    $action = app(ReorderTitleAction::class);
    $first = $action->create([
        'category_id' => $category->id,
        'name' => 'First',
        'usage_position' => TitleUsagePosition::BeforeName,
        'sort_order' => 1,
    ]);
    $stale = $first->fresh();
    $second = $action->create([
        'category_id' => $category->id,
        'name' => 'Second',
        'usage_position' => TitleUsagePosition::BeforeName,
        'sort_order' => 1,
    ]);

    $updated = $action->update($stale, ['name' => 'Renamed']);

    expect($updated->name)->toBe('Renamed')
        ->and($updated->sort_order)->toBe(2)
        ->and($second->fresh()->sort_order)->toBe(1);
});

it('rejects a title update when its category has changed since it was loaded', function (): void {
    $source = TitleCategory::create(['code' => 'source-order', 'name' => 'Source', 'sort_order' => 1]);
    $target = TitleCategory::create(['code' => 'target-order', 'name' => 'Target', 'sort_order' => 2]);
    $action = app(ReorderTitleAction::class);
    $title = $action->create([
        'category_id' => $source->id,
        'name' => 'Movable',
        'usage_position' => TitleUsagePosition::BeforeName,
        'sort_order' => 1,
    ]);
    $stale = $title->fresh();
    $action->update($title, ['category_id' => $target->id]);

    expect(fn () => $action->update($stale, ['name' => 'Outdated']))
        ->toThrow(ValidationException::class);

    expect($title->fresh()->category_id)->toBe($target->id)
        ->and($title->fresh()->name)->toBe('Movable');
});

it('rejects ordering a title in a missing category', function (): void {
    expect(fn () => app(ReorderTitleAction::class)->create([
        'category_id' => (string) Str::uuid(),
        'name' => 'Orphan',
        'usage_position' => TitleUsagePosition::BeforeName,
        'sort_order' => 1,
    ]))->toThrow(ValidationException::class);

    expect(Title::query()->where('name', 'Orphan')->exists())->toBeFalse();
});

it('nulls title assignment issuer ids when a title issuer is deleted', function (): void {
    $issuer = TitleIssuer::create([
        'issuer_name' => 'Professional Board',
        'issuer_type' => 'professional_board',
    ]);
    $category = TitleCategory::create(['code' => 'issuer-delete', 'name' => 'Issuer Delete', 'sort_order' => 1]);
    $title = Title::create([
        'category_id' => $category->id,
        'name' => 'Certified',
        'usage_position' => TitleUsagePosition::BeforeName,
        'sort_order' => 1,
    ]);
    $person = Person::create(['name' => 'Issuer Assignment']);
    $assignment = TitleAssignment::create([
        'titleable_type' => 'person',
        'titleable_id' => $person->id,
        'title_id' => $title->id,
        'issuer_id' => $issuer->id,
        'status' => AssignmentStatus::Active,
    ]);

    expect(app(DeleteTitleIssuer::class)->execute($issuer))->toBeTrue()
        ->and(TitleIssuer::query()->whereKey($issuer->id)->exists())->toBeFalse()
        ->and($assignment->fresh()?->issuer_id)->toBeNull();
});
