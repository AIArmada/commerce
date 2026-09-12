<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
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

it('ships a single unique code index on vouchers', function (): void {
    $create = file_get_contents(dirname(__DIR__, 4) . '/packages/vouchers/database/migrations/2001_04_01_000001_create_vouchers_table.php');

    expect($create)->toBeString()
        ->and($create)->toContain("->string('code')->unique()")
        ->and($create)->not->toContain("->index('code')");
});
