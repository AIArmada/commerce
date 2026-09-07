<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('backfills promotion ids from valid historical voucher metadata without overwriting values', function (): void {
    $backfilledPromotionId = (string) Str::uuid();
    $existingPromotionId = (string) Str::uuid();

    DB::table('vouchers')->insert([
        'id' => (string) Str::uuid(),
        'code' => 'BACKFILL-' . Str::upper(Str::random(8)),
        'name' => 'Backfill Voucher',
        'type' => 'fixed',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
        'promotion_id' => null,
        'metadata' => json_encode(['source_promotion_id' => $backfilledPromotionId], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('vouchers')->insert([
        'id' => (string) Str::uuid(),
        'code' => 'EXISTING-' . Str::upper(Str::random(8)),
        'name' => 'Existing Promotion Voucher',
        'type' => 'fixed',
        'value' => 1000,
        'currency' => 'MYR',
        'status' => 'active',
        'promotion_id' => $existingPromotionId,
        'metadata' => json_encode(['source_promotion_id' => $backfilledPromotionId], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require dirname(__DIR__, 4) . '/packages/vouchers/database/migrations/2026_09_07_100000_backfill_voucher_promotion_ids.php';
    $migration->up();

    expect(DB::table('vouchers')->where('promotion_id', $backfilledPromotionId)->count())->toBe(1)
        ->and(DB::table('vouchers')->where('promotion_id', $existingPromotionId)->count())->toBe(1);
});

it('removes the unused voucher credit tables and redundant code index', function (): void {
    expect(Schema::hasTable('voucher_assignments'))->toBeFalse()
        ->and(Schema::hasTable('voucher_transactions'))->toBeFalse()
        ->and(Schema::hasIndex('vouchers', 'vouchers_code_index'))->toBeFalse()
        ->and(Schema::hasIndex('vouchers', 'vouchers_code_unique'))->toBeTrue();
});

it('keeps the only code index when the unique code index is absent', function (): void {
    $tableName = 'vouchers_without_unique_code';
    $indexName = $tableName . '_code_index';
    $originalTable = config('vouchers.database.tables.vouchers');

    try {
        config(['vouchers.database.tables.vouchers' => $tableName]);

        Schema::create($tableName, function (Blueprint $table) use ($indexName): void {
            $table->uuid('id')->primary();
            $table->string('code');
            $table->index('code', $indexName);
        });

        $migration = require dirname(__DIR__, 4) . '/packages/vouchers/database/migrations/2026_09_07_100002_drop_redundant_voucher_code_index.php';
        $migration->up();
        $migration->up();

        expect(Schema::hasIndex($tableName, $indexName))->toBeTrue();
    } finally {
        config(['vouchers.database.tables.vouchers' => $originalTable]);
        Schema::dropIfExists($tableName);
    }
});
