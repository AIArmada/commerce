<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function runHeldMinorMigration(string $conversionsTable): void
{
    config()->set('affiliates.database.tables.conversions', $conversionsTable);

    $migration = require dirname(__DIR__, 4) . '/packages/affiliates/database/migrations/2000_01_01_000029_add_held_minor_to_affiliate_conversions_table.php';
    $migration->up();
}

it('leaves existing rows at held_minor zero without backfilling', function (): void {
    $conversions = 'affiliate_conversions_held_nobackfill';

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

    runHeldMinorMigration($conversions);

    expect(DB::table($conversions)->value('held_minor'))->toBe(0);

    Schema::dropIfExists($conversions);
});
