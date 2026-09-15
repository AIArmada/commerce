<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Affiliate Model Tests - Full Coverage
test('Affiliate can be created with required fields', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'AFFTEST001',
        'name' => 'Test Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'USD',
    ]);

    expect($affiliate)->toBeInstanceOf(Affiliate::class);
    expect($affiliate->code)->toBe('AFFTEST001');
    expect($affiliate->name)->toBe('Test Affiliate');
});

test('Affiliate has programs relationship', function (): void {
    $affiliate = new Affiliate;

    expect($affiliate->programs())->toBeInstanceOf(BelongsToMany::class);
});
