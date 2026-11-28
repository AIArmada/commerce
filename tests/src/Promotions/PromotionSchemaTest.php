<?php

declare(strict_types=1);

use AIArmada\Promotions\Models\Promotion;
use AIArmada\Promotions\PromotionsServiceProvider;
use AIArmada\Promotions\Support\IssuedVoucherTrackingState;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('memoizes voucher tracking detection per scope instead of per process', function (): void {
    $this->app->register(PromotionsServiceProvider::class);

    $first = app(IssuedVoucherTrackingState::class);
    $second = app(IssuedVoucherTrackingState::class);

    expect($first)->toBe($second);

    $detected = Promotion::supportsIssuedVoucherTracking();

    expect($first->supported)->toBe($detected);

    app()->forgetScopedInstances();

    expect(app(IssuedVoucherTrackingState::class))->not->toBe($first)
        ->and(app(IssuedVoucherTrackingState::class)->supported)->toBeNull();
});

it('indexes promotionables for reverse lookups', function (): void {
    $indexes = collect(Schema::getIndexes('promotionables'));

    expect($indexes->pluck('name'))->toContain('promotionables_reverse_index');
});

it('constrains promotion codes per owner instead of globally', function (): void {
    $indexes = collect(Schema::getIndexes('promotions'));

    expect($indexes->pluck('name'))->toContain('promotions_owner_code_unique')
        ->and($indexes->where('name', 'promotions_owner_code_unique')->first()['columns'])
        ->toBe(['owner_type', 'owner_id', 'code']);
});

it('cascades promotion target deletion at the database boundary', function (): void {
    $originalConnection = DB::getDefaultConnection();
    $connection = 'promotion-cascade';

    config(['database.connections.' . $connection => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);

    DB::setDefaultConnection($connection);
    Schema::clearResolvedInstance('db.schema');

    try {
        expect((int) DB::selectOne('PRAGMA foreign_keys')->foreign_keys)->toBe(1);

        $migrationDirectory = dirname(__DIR__, 3) . '/packages/promotions/database/migrations';

        foreach (glob($migrationDirectory . '/*.php') as $file) {
            (require $file)->up();
        }

        $promotionId = (string) Str::uuid();
        DB::table('promotions')->insert([
            'id' => $promotionId,
            'name' => 'Cascade promotion',
            'discount_value' => 10,
        ]);

        DB::table('promotionables')->insert([
            'promotion_id' => $promotionId,
            'promotionable_type' => 'product',
            'promotionable_id' => (string) Str::uuid(),
        ]);

        expect(DB::table('promotions')->where('id', $promotionId)->delete())->toBe(1)
            ->and(DB::table('promotionables')->where('promotion_id', $promotionId)->exists())->toBeFalse();
    } finally {
        DB::setDefaultConnection($originalConnection);
        Schema::clearResolvedInstance('db.schema');
        DB::purge($connection);
    }
});

it('rejects duplicate global promotion codes while allowing automatic promotions', function (): void {
    Promotion::factory()->create(['code' => null]);
    Promotion::factory()->create(['code' => null]);
    Promotion::factory()->create(['code' => 'GLOBAL-UNIQUE-PROMOTION']);

    expect(fn () => Promotion::factory()->create(['code' => 'GLOBAL-UNIQUE-PROMOTION']))->toThrow(QueryException::class);
});
