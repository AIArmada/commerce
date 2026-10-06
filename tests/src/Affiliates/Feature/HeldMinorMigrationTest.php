<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('creates conversions with held_minor defaulting to zero', function (): void {
    $conversions = (string) config('affiliates.database.tables.conversions', 'affiliate_conversions');

    expect(Schema::hasColumn($conversions, 'held_minor'))->toBeTrue();

    DB::table($conversions)->insert([
        'id' => (string) Str::uuid(),
        'affiliate_id' => (string) Str::uuid(),
        'affiliate_code' => 'TESTCODE',
        'commission_currency' => 'USD',
    ]);

    expect(DB::table($conversions)->value('held_minor'))->toBe(0);
});
