<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('deactivates and canonicalizes stored buy x get y promotions', function (): void {
    $promotionId = (string) Str::uuid();
    $now = now();

    DB::table('promotions')->insert([
        'id' => $promotionId,
        'name' => 'Legacy Buy 2 Get 1',
        'type' => 'buy_x_get_y',
        'discount_value' => 1,
        'is_active' => true,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $migration = require dirname(__DIR__, 4) . '/packages/promotions/database/migrations/2026_09_07_110000_deactivate_buy_x_get_y_promotions.php';

    $migration->up();
    $migration->up();

    $promotion = DB::table('promotions')->where('id', $promotionId)->first();

    expect($promotion)->not->toBeNull()
        ->and($promotion->type)->toBe('fixed')
        ->and($promotion->discount_value)->toBe(0)
        ->and($promotion->is_active)->toBe(0)
        ->and($promotion->deactivated_at)->not->toBeNull();
});
