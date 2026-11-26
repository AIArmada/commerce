<?php

declare(strict_types=1);

use AIArmada\Affiliates\Features\Programs\Api\ProgramMembershipReader;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Affiliates\States\Active;

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

    expect(app(ProgramMembershipReader::class)->programIds((string) $affiliate->getKey()))->toBe([]);
});
