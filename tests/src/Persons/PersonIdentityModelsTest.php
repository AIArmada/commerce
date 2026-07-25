<?php

declare(strict_types=1);

use AIArmada\Persons\Enums\AffiliationType;
use AIArmada\Persons\Enums\AssignmentStatus;
use AIArmada\Persons\Enums\CredentialType;
use AIArmada\Persons\Enums\Gender;
use AIArmada\Persons\Enums\IssuerType;
use AIArmada\Persons\Enums\PersonNameType;
use AIArmada\Persons\Enums\TitleUsagePosition;
use AIArmada\Persons\Models\Affiliation;
use AIArmada\Persons\Models\AffiliationRole;
use AIArmada\Persons\Models\CredentialAssignment;
use AIArmada\Persons\Models\CredentialDefinition;
use AIArmada\Persons\Models\Person;
use AIArmada\Persons\Models\PersonName;
use AIArmada\Persons\Models\Title;
use AIArmada\Persons\Models\TitleAssignment;
use AIArmada\Persons\Models\TitleCategory;
use AIArmada\Persons\Models\TitleIssuer;

beforeEach(function (): void {
    persons_register_morph_map('person');
});

describe('person identity models', function (): void {

    it('creates a person with basic fields', function (): void {
        $person = Person::create([
            'name' => 'Ahmad Rahman',
            'family_name' => 'Rahman',
            'middle_name' => 'Bin',
            'gender' => 'male',
            'status' => 'verified',
        ]);

        expect($person->name)->toBe('Ahmad Rahman');
        expect($person->family_name)->toBe('Rahman');
        expect($person->middle_name)->toBe('Bin');
        expect($person->gender)->toBe(Gender::Male);
        expect($person->status)->toBe('verified');
    });

    it('creates a person with multi-context names', function (): void {
        $person = Person::factory()->create();

        PersonName::create([
            'person_id' => $person->id,
            'name_type' => PersonNameType::Display,
            'full_name' => 'Ahmad Rahman',
            'language_code' => 'en',
            'is_primary' => true,
        ]);

        PersonName::create([
            'person_id' => $person->id,
            'name_type' => PersonNameType::Religious,
            'full_name' => 'أحمد بن عبد الرحمن',
            'language_code' => 'ar',
        ]);

        expect($person->fresh()->names)->toHaveCount(2);
        expect($person->names->first()->name_type)->toBe(PersonNameType::Display);
    });

    it('creates a title category with titles', function (): void {
        $category = TitleCategory::create([
            'code' => 'test_category',
            'name' => 'Test Category',
            'sort_order' => 10,
        ]);

        $title = Title::create([
            'category_id' => $category->id,
            'name' => 'Test Title',
            'short_form' => 'TT',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 10,
        ]);

        expect($category->titles)->toHaveCount(1);
        expect($title->category->code)->toBe('test_category');
        expect($title->usage_position)->toBe(TitleUsagePosition::BeforeName);
    });

    it('assigns a title to a person polymorphically', function (): void {
        $person = Person::factory()->create();
        $category = TitleCategory::create(['code' => 'state_honour', 'name' => 'State Honour']);
        $title = Title::create([
            'category_id' => $category->id,
            'name' => 'Datuk',
            'short_form' => 'Datuk',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 90,
        ]);

        $assignment = TitleAssignment::create([
            'titleable_type' => 'person',
            'titleable_id' => $person->id,
            'title_id' => $title->id,
            'status' => AssignmentStatus::Active,
        ]);

        expect($person->fresh()->titleAssignments)->toHaveCount(1);
        expect($assignment->title->name)->toBe('Datuk');
        expect($assignment->titleable->id)->toBe($person->id);
    });

    it('creates credential definition and assignment', function (): void {
        $person = Person::factory()->create();
        $definition = CredentialDefinition::create([
            'name' => 'Doctor of Philosophy',
            'short_form' => 'PhD',
            'credential_type' => CredentialType::AcademicDegree,
        ]);

        $assignment = CredentialAssignment::create([
            'credentialable_type' => 'person',
            'credentialable_id' => $person->id,
            'credential_id' => $definition->id,
            'date_obtained' => '2020-06-15',
        ]);

        expect($person->fresh()->credentialAssignments)->toHaveCount(1);
        expect($assignment->credential->short_form)->toBe('PhD');
    });

    it('creates affiliation with roles for a person', function (): void {
        $person = Person::factory()->create();

        $affiliation = Affiliation::create([
            'affiliatable_type' => 'person',
            'affiliatable_id' => $person->id,
            'institution_id' => null,
            'affiliation_type' => AffiliationType::Employee,
            'is_primary' => true,
        ]);

        AffiliationRole::create([
            'affiliation_id' => $affiliation->id,
            'role_name' => 'CEO',
            'is_current' => true,
        ]);

        AffiliationRole::create([
            'affiliation_id' => $affiliation->id,
            'role_name' => 'Board Member',
            'is_current' => true,
        ]);

        expect($person->fresh()->affiliations)->toHaveCount(1);
        expect($affiliation->fresh()->roles)->toHaveCount(2);
    });

    it('formats display name with ordered titles', function (): void {
        $person = Person::create(['name' => 'Ahmad Rahman', 'status' => 'verified']);
        $academic = TitleCategory::create(['code' => 'academic', 'name' => 'Academic']);
        $honour = TitleCategory::create(['code' => 'state_honour', 'name' => 'State Honour']);

        $datuk = Title::create([
            'category_id' => $honour->id,
            'name' => 'Datuk',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 90,
        ]);

        $dr = Title::create([
            'category_id' => $academic->id,
            'name' => 'Dr.',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 110,
        ]);

        $phd = Title::create([
            'category_id' => $academic->id,
            'name' => 'PhD',
            'usage_position' => TitleUsagePosition::AfterName,
            'sort_order' => 10,
        ]);

        TitleAssignment::create(['titleable_type' => 'person', 'titleable_id' => $person->id, 'title_id' => $datuk->id, 'status' => 'active']);
        TitleAssignment::create(['titleable_type' => 'person', 'titleable_id' => $person->id, 'title_id' => $dr->id, 'status' => 'active']);
        TitleAssignment::create(['titleable_type' => 'person', 'titleable_id' => $person->id, 'title_id' => $phd->id, 'status' => 'active']);

        expect($person->formatted_name)->toBe('Datuk Dr. Ahmad Rahman, PhD');
    });

    it('enforces enum casts on model properties', function (): void {
        $category = TitleCategory::create(['code' => 'religious', 'name' => 'Religious']);
        $title = Title::create([
            'category_id' => $category->id,
            'name' => 'Ustaz',
            'usage_position' => TitleUsagePosition::BeforeName,
            'sort_order' => 10,
        ]);

        expect($title->usage_position)->toBe(TitleUsagePosition::BeforeName);
        expect($title->usage_position->value)->toBe('before_name');
    });

    it('creates a title issuer', function (): void {
        $issuer = TitleIssuer::create([
            'issuer_name' => 'Board of Engineers Malaysia',
            'issuer_type' => IssuerType::ProfessionalBoard,
        ]);

        expect($issuer->issuer_name)->toBe('Board of Engineers Malaysia');
        expect($issuer->issuer_type)->toBe(IssuerType::ProfessionalBoard);
    });

    it('has no soft deletes or FK constraints in migrations', function (): void {
        $dir = __DIR__ . '/../../../packages/persons/database/migrations/';
        $files = glob($dir . '*.php');
        expect($files)->not->toBeEmpty();

        foreach ($files as $file) {
            $content = file_get_contents($file);

            expect($content)->not->toContain('softDeletes')
                ->and($content)->not->toContain('softDeletesTz')
                ->and($content)->not->toContain("foreign('")
                ->and($content)->not->toContain('constrained(');
        }
    });

    it('has uuid primary keys in all create table migrations', function (): void {
        $dir = __DIR__ . '/../../../packages/persons/database/migrations/';
        $files = glob($dir . '*.php');
        expect($files)->not->toBeEmpty();

        foreach ($files as $file) {
            $content = file_get_contents($file);
            expect($content)->toContain("\$table->uuid('id')->primary()");
        }
    });

});
