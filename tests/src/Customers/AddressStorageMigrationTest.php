<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('drops the retired customer address table after shape preflight and tolerates reruns', function (): void {
    $migrationPath = dirname(__DIR__, 3) . '/packages/customers/database/migrations/2026_09_12_000003_drop_legacy_customer_address_storage.php';
    $retiredTable = 'customer_retired_' . Str::lower(Str::random(8));
    $unrelatedTable = 'customer_unrelated_' . Str::lower(Str::random(8));
    $originalTable = config('customers.database.tables.addresses');

    try {
        config()->set('customers.database.tables.addresses', $retiredTable);

        Schema::create($retiredTable, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('customer_id');
            $table->string('type');
            $table->string('line1');
            $table->string('city')->index();
            $table->string('keep_me');
        });

        $migration = require $migrationPath;
        $migration->up();
        $migration->up();

        expect(Schema::hasTable($retiredTable))->toBeFalse();

        config()->set('customers.database.tables.addresses', $unrelatedTable);
        Schema::create($unrelatedTable, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('keep_me');
        });

        $migration->up();

        expect(Schema::hasTable($unrelatedTable))->toBeTrue();
    } finally {
        Schema::dropIfExists($retiredTable);
        Schema::dropIfExists($unrelatedTable);
        config()->set('customers.database.tables.addresses', $originalTable);
    }
});
