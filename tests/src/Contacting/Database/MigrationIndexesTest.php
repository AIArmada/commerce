<?php

declare(strict_types=1);

use AIArmada\Contacting\Data\ContactMethodData;
use AIArmada\Contacting\Data\SocialProfileData;
use AIArmada\Contacting\Models\ContactMethod;
use AIArmada\Contacting\Models\SocialProfile;
use AIArmada\Customers\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('ships primary, demotion, and validity-window indexes in the base creates', function (): void {
    $contactMethodsTable = config('contacting.database.tables.contact_methods', 'contact_methods');
    $socialProfilesTable = config('contacting.database.tables.social_profiles', 'social_profiles');

    expect(Schema::hasIndex($contactMethodsTable, 'contact_methods_primary_unique'))->toBeTrue()
        ->and(Schema::hasIndex($contactMethodsTable, 'contact_methods_contactable_primary_index'))->toBeTrue()
        ->and(Schema::hasIndex($contactMethodsTable, 'contact_methods_contactable_validity_index'))->toBeTrue()
        ->and(Schema::hasIndex($socialProfilesTable, 'social_profiles_primary_unique'))->toBeTrue()
        ->and(Schema::hasIndex($socialProfilesTable, 'social_profiles_socialable_primary_index'))->toBeTrue()
        ->and(Schema::hasIndex($socialProfilesTable, 'social_profiles_socialable_validity_index'))->toBeTrue();

    $migrationBase = dirname(__DIR__, 4) . '/packages/contacting/database/migrations/';

    foreach ([
        '2000_01_01_000001_create_contact_methods_table.php' => ['_primary_unique', '_contactable_primary_index', '_contactable_validity_index'],
        '2000_01_01_000003_create_contact_social_profiles_table.php' => ['_primary_unique', '_socialable_primary_index', '_socialable_validity_index'],
    ] as $file => $needles) {
        $create = (string) file_get_contents($migrationBase . $file);

        foreach ($needles as $needle) {
            expect($create)->toContain($needle);
        }

        expect($create)->toContain('Schema::create')
            ->and($create)->not->toContain('hasTable');
    }
});

it('keeps primary application guards and rejects direct duplicate writes', function (): void {
    $customer = Customer::create([
        'first_name' => 'Indexed',
        'last_name' => 'Contactable',
    ]);

    $firstContact = $customer->addContactMethod(new ContactMethodData(
        type: 'email',
        purpose: 'general',
        value: 'first-' . Str::lower(Str::random(8)) . '@example.com',
        isPrimary: true,
    ));
    $secondContact = $customer->addContactMethod(new ContactMethodData(
        type: 'email',
        purpose: 'general',
        value: 'second-' . Str::lower(Str::random(8)) . '@example.com',
        isPrimary: true,
    ));

    expect($firstContact->fresh()?->is_primary)->toBeFalse()
        ->and($secondContact->fresh()?->is_primary)->toBeTrue();

    $contactMethodsTable = (new ContactMethod)->getTable();
    expect(fn () => DB::table($contactMethodsTable)->insert([
        'id' => (string) Str::uuid(),
        'contactable_type' => $customer->getMorphClass(),
        'contactable_id' => $customer->getKey(),
        'type' => 'email',
        'purpose' => 'general',
        'value' => 'direct-duplicate@example.com',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    $firstProfile = $customer->addSocialProfile(new SocialProfileData(
        platform: 'facebook',
        purpose: 'general',
        handle: 'first-' . Str::lower(Str::random(8)),
        isPrimary: true,
    ));
    $secondProfile = $customer->addSocialProfile(new SocialProfileData(
        platform: 'facebook',
        purpose: 'general',
        handle: 'second-' . Str::lower(Str::random(8)),
        isPrimary: true,
    ));

    expect($firstProfile->fresh()?->is_primary)->toBeFalse()
        ->and($secondProfile->fresh()?->is_primary)->toBeTrue();

    $socialProfilesTable = (new SocialProfile)->getTable();
    expect(fn () => DB::table($socialProfilesTable)->insert([
        'id' => (string) Str::uuid(),
        'socialable_type' => $customer->getMorphClass(),
        'socialable_id' => $customer->getKey(),
        'platform' => 'facebook',
        'purpose' => 'general',
        'handle' => 'direct-duplicate',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('reports dirty primary contact data without deleting it during preflight', function (): void {
    $customer = Customer::create([
        'first_name' => 'Preflight',
        'last_name' => 'Contactable',
    ]);
    $tableName = (string) config('contacting.database.tables.contact_methods', 'contact_methods');
    Schema::table($tableName, function (Blueprint $table): void {
        $table->dropIndex('contact_methods_primary_unique');
    });

    DB::table($tableName)->insert([
        [
            'id' => (string) Str::uuid(),
            'contactable_type' => $customer->getMorphClass(),
            'contactable_id' => $customer->getKey(),
            'type' => 'phone',
            'purpose' => 'general',
            'value' => '+60123456789',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => (string) Str::uuid(),
            'contactable_type' => $customer->getMorphClass(),
            'contactable_id' => $customer->getKey(),
            'type' => 'phone',
            'purpose' => 'general',
            'value' => '+60129876543',
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $before = DB::table($tableName)
        ->where('contactable_id', $customer->getKey())
        ->where('type', 'phone')
        ->where('is_primary', true)
        ->count();

    $connection = Schema::getConnection();
    $grammar = $connection->getQueryGrammar();

    expect(fn () => $connection->statement(sprintf(
        'CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s = 1 AND %s IS NOT NULL AND %s IS NOT NULL',
        $grammar->wrap('contact_methods_primary_unique'),
        $grammar->wrapTable($tableName),
        implode(', ', array_map($grammar->wrap(...), ['contactable_type', 'contactable_id', 'type', 'purpose'])),
        $grammar->wrap('is_primary'),
        $grammar->wrap('contactable_type'),
        $grammar->wrap('contactable_id'),
    )))->toThrow(QueryException::class);

    expect(DB::table($tableName)
        ->where('contactable_id', $customer->getKey())
        ->where('type', 'phone')
        ->where('is_primary', true)
        ->count())->toBe($before);
});
