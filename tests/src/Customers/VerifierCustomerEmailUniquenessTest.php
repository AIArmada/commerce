<?php

declare(strict_types=1);

use AIArmada\Customers\Models\Customer;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('adds owner-aware customer email indexes to canonical contact methods', function (): void {
    $tableName = 'verifier_contact_methods_' . Str::lower(Str::random(8));

    Schema::create($tableName, function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->uuid('owner_id')->nullable();
        $table->string('contactable_type');
        $table->uuid('contactable_id')->nullable();
        $table->string('type');
        $table->text('value')->nullable();
        $table->text('normalized_value')->nullable();
    });

    config()->set('contacting.database.tables.contact_methods', $tableName);

    try {
        $migration = require dirname(__DIR__, 3) . '/packages/customers/database/migrations/2026_09_08_000002_add_owner_email_uniqueness_to_contact_methods_table.php';
        $migration->up();
        $migration->up();

        $indexNames = collect(Schema::getIndexes($tableName))
            ->filter(static fn (array $index): bool => ($index['unique'] ?? false) === true)
            ->pluck('name')
            ->all();

        expect($indexNames)->toContain($tableName . '_customer_email_owner_unique')
            ->and($indexNames)->toContain($tableName . '_customer_email_global_unique');

        $customerMorph = (new Customer)->getMorphClass();
        $ownerType = 'VerifierOwner';
        $ownerA = (string) Str::uuid();
        $ownerB = (string) Str::uuid();

        $insert = static function (array $attributes) use ($customerMorph, $tableName): void {
            DB::table($tableName)->insert(array_merge([
                'id' => (string) Str::uuid(),
                'contactable_type' => $customerMorph,
                'contactable_id' => (string) Str::uuid(),
                'type' => 'email',
                'value' => 'shared@example.com',
                'normalized_value' => null,
            ], $attributes));
        };

        $insert(['owner_type' => $ownerType, 'owner_id' => $ownerA]);

        expect(fn () => $insert([
            'owner_type' => $ownerType,
            'owner_id' => $ownerA,
            'value' => ' Shared@example.com ',
        ]))->toThrow(QueryException::class);

        $insert(['owner_type' => $ownerType, 'owner_id' => $ownerB]);
        $insert(['owner_type' => null, 'owner_id' => null]);

        expect(fn () => $insert([
            'owner_type' => null,
            'owner_id' => null,
        ]))->toThrow(QueryException::class);

        $insert([
            'owner_type' => $ownerType,
            'owner_id' => $ownerA,
            'contactable_type' => 'OtherContactable',
        ]);
        $insert([
            'owner_type' => $ownerType,
            'owner_id' => $ownerA,
            'type' => 'phone',
        ]);
    } finally {
        Schema::dropIfExists($tableName);
        config()->set('contacting.database.tables.contact_methods', 'contact_methods');
    }
});

it('fails before indexing partial owner tuples and duplicate customer emails', function (): void {
    $tableName = 'verifier_contact_methods_' . Str::lower(Str::random(8));

    Schema::create($tableName, function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('owner_type')->nullable();
        $table->uuid('owner_id')->nullable();
        $table->string('contactable_type');
        $table->uuid('contactable_id')->nullable();
        $table->string('type');
        $table->text('value')->nullable();
        $table->text('normalized_value')->nullable();
    });

    config()->set('contacting.database.tables.contact_methods', $tableName);

    try {
        $migration = require dirname(__DIR__, 3) . '/packages/customers/database/migrations/2026_09_08_000002_add_owner_email_uniqueness_to_contact_methods_table.php';
        $customerMorph = (new Customer)->getMorphClass();

        DB::table($tableName)->insert([
            'id' => (string) Str::uuid(),
            'owner_type' => 'VerifierOwner',
            'owner_id' => null,
            'contactable_type' => $customerMorph,
            'contactable_id' => (string) Str::uuid(),
            'type' => 'email',
            'value' => 'partial@example.com',
            'normalized_value' => null,
        ]);

        expect(fn () => $migration->up())->toThrow(RuntimeException::class);

        DB::table($tableName)->delete();

        $duplicate = [
            'owner_type' => 'VerifierOwner',
            'owner_id' => (string) Str::uuid(),
            'contactable_type' => $customerMorph,
            'contactable_id' => (string) Str::uuid(),
            'type' => 'email',
            'value' => 'duplicate@example.com',
            'normalized_value' => null,
        ];

        DB::table($tableName)->insert(array_merge(['id' => (string) Str::uuid()], $duplicate));
        DB::table($tableName)->insert(array_merge([
            'id' => (string) Str::uuid(),
            'contactable_id' => (string) Str::uuid(),
        ], $duplicate));

        expect(fn () => $migration->up())->toThrow(RuntimeException::class);
    } finally {
        Schema::dropIfExists($tableName);
        config()->set('contacting.database.tables.contact_methods', 'contact_methods');
    }
});
