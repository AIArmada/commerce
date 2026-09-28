<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function runHeldMinorMigration(string $conversionsTable, string $balancesTable): void
{
    config()->set('affiliates.database.tables.conversions', $conversionsTable);
    config()->set('affiliates.database.tables.balances', $balancesTable);

    $migration = require dirname(__DIR__, 4) . '/packages/affiliates/database/migrations/2000_01_01_000029_add_held_minor_to_affiliate_conversions_table.php';
    $migration->up();
}

function createHeldMinorFixtureTables(string $conversionsTable, string $balancesTable): void
{
    Schema::create($conversionsTable, function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('affiliate_id')->nullable();
        $table->string('status', 32);
        $table->bigInteger('commission_minor')->default(0);
        $table->string('commission_currency', 3)->default('USD');
    });

    Schema::create($balancesTable, function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('affiliate_id');
        $table->string('currency', 3);
        $table->bigInteger('holding_minor')->default(0);
    });
}

it('backfills held_minor only where a covering balance exists', function (): void {
    $conversions = 'affiliate_conversions_held_fixture';
    $balances = 'affiliate_balances_held_fixture';
    createHeldMinorFixtureTables($conversions, $balances);

    $covered = (string) Str::uuid();
    $bare = (string) Str::uuid();
    $short = (string) Str::uuid();
    $foreign = (string) Str::uuid();

    DB::table($balances)->insert([
        ['id' => (string) Str::uuid(), 'affiliate_id' => $covered, 'currency' => 'USD', 'holding_minor' => 5000],
        ['id' => (string) Str::uuid(), 'affiliate_id' => $short, 'currency' => 'USD', 'holding_minor' => 1000],
        ['id' => (string) Str::uuid(), 'affiliate_id' => $foreign, 'currency' => 'EUR', 'holding_minor' => 9000],
    ]);

    DB::table($conversions)->insert([
        ['id' => (string) Str::uuid(), 'affiliate_id' => $covered, 'status' => 'pending', 'commission_minor' => 5000, 'commission_currency' => 'USD'],
        ['id' => (string) Str::uuid(), 'affiliate_id' => $covered, 'status' => 'qualified', 'commission_minor' => 3000, 'commission_currency' => 'usd'],
        ['id' => (string) Str::uuid(), 'affiliate_id' => $bare, 'status' => 'pending', 'commission_minor' => 5000, 'commission_currency' => 'USD'],
        ['id' => (string) Str::uuid(), 'affiliate_id' => $short, 'status' => 'pending', 'commission_minor' => 5000, 'commission_currency' => 'USD'],
        ['id' => (string) Str::uuid(), 'affiliate_id' => $foreign, 'status' => 'pending', 'commission_minor' => 5000, 'commission_currency' => 'USD'],
        ['id' => (string) Str::uuid(), 'affiliate_id' => null, 'status' => 'pending', 'commission_minor' => 5000, 'commission_currency' => 'USD'],
        ['id' => (string) Str::uuid(), 'affiliate_id' => $covered, 'status' => 'approved', 'commission_minor' => 7000, 'commission_currency' => 'USD'],
    ]);

    runHeldMinorMigration($conversions, $balances);

    $heldBy = fn (string $affiliateId, string $status): mixed => DB::table($conversions)
        ->where('affiliate_id', $affiliateId)->where('status', $status)->value('held_minor');

    expect($heldBy($covered, 'pending'))->toBe(5000)
        ->and($heldBy($covered, 'qualified'))->toBe(3000)
        ->and($heldBy($bare, 'pending'))->toBe(0)
        ->and($heldBy($short, 'pending'))->toBe(0)
        ->and($heldBy($foreign, 'pending'))->toBe(0)
        ->and(DB::table($conversions)->whereNull('affiliate_id')->value('held_minor'))->toBe(0)
        ->and($heldBy($covered, 'approved'))->toBe(0);

    Schema::dropIfExists($conversions);
    Schema::dropIfExists($balances);
});

it('leaves held_minor at zero when the balances table does not exist', function (): void {
    $conversions = 'affiliate_conversions_held_nobal';
    $balances = 'affiliate_balances_held_missing';

    Schema::create($conversions, function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('affiliate_id')->nullable();
        $table->string('status', 32);
        $table->bigInteger('commission_minor')->default(0);
        $table->string('commission_currency', 3)->default('USD');
    });

    DB::table($conversions)->insert([
        'id' => (string) Str::uuid(), 'affiliate_id' => (string) Str::uuid(),
        'status' => 'pending', 'commission_minor' => 5000, 'commission_currency' => 'USD',
    ]);

    runHeldMinorMigration($conversions, $balances);

    expect(DB::table($conversions)->value('held_minor'))->toBe(0);

    Schema::dropIfExists($conversions);
});
