<?php

declare(strict_types=1);

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\States\Active;
use AIArmada\Affiliates\States\PendingPayout;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentAffiliates\Resources\AffiliatePayoutResource\Pages\CreateAffiliatePayout;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    config()->set('affiliates.owner.enabled', true);
    config()->set('affiliates.owner.include_global', false);

    Affiliate::query()->delete();
    User::query()->delete();
});

it('maps payout create form data to payee tuple and pending status', function (): void {
    $owner = User::create([
        'name' => 'Payout Owner',
        'email' => 'payout-owner@example.com',
        'password' => 'secret',
    ]);

    $affiliate = OwnerContext::withOwner($owner, function (): Affiliate {
        return Affiliate::create([
            'code' => 'AFF-' . Str::uuid(),
            'name' => 'Payout Form Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);
    });

    $page = new CreateAffiliatePayout;

    $method = new ReflectionMethod(CreateAffiliatePayout::class, 'mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    /** @var array<string, mixed> $mutated */
    $mutated = OwnerContext::withOwner($owner, function () use ($method, $page, $affiliate): array {
        return $method->invoke($page, [
            'affiliate_id' => $affiliate->getKey(),
            'total_minor' => 5500,
            'currency' => 'usd',
            'scheduled_at' => now()->addDay(),
            'notes' => 'Quarterly payout',
        ]);
    });

    expect($mutated['status'])->toBe(PendingPayout::class)
        ->and($mutated['total_minor'])->toBe(5500)
        ->and($mutated['currency'])->toBe('USD')
        ->and($mutated['payee_type'])->toBe($affiliate->getMorphClass())
        ->and($mutated['payee_id'])->toBe($affiliate->getKey())
        ->and($mutated['metadata'])->toBe(['notes' => 'Quarterly payout'])
        ->and($mutated['conversion_count'])->toBe(0)
        ->and($mutated['reference'])->toStartWith('PAY-');
});

it('throws validation exception when affiliate is missing during payout create', function (): void {
    $page = new CreateAffiliatePayout;

    $method = new ReflectionMethod(CreateAffiliatePayout::class, 'mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    expect(fn () => $method->invoke($page, [
        'affiliate_id' => (string) Str::uuid(),
        'total_minor' => 1000,
        'currency' => 'USD',
    ]))->toThrow(ValidationException::class);
});

it('rejects affiliate ids outside the current owner scope during payout create', function (): void {
    $ownerA = User::create([
        'name' => 'Payout Owner A',
        'email' => 'payout-owner-a@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::create([
        'name' => 'Payout Owner B',
        'email' => 'payout-owner-b@example.com',
        'password' => 'secret',
    ]);

    $affiliateB = OwnerContext::withOwner($ownerB, function (): Affiliate {
        return Affiliate::create([
            'code' => 'AFF-' . Str::uuid(),
            'name' => 'Payout Owner B Affiliate',
            'status' => Active::class,
            'commission_type' => 'percentage',
            'commission_rate' => 500,
            'currency' => 'USD',
        ]);
    });

    $page = new CreateAffiliatePayout;

    $method = new ReflectionMethod(CreateAffiliatePayout::class, 'mutateFormDataBeforeCreate');
    $method->setAccessible(true);

    OwnerContext::withOwner($ownerA, function () use ($method, $page, $affiliateB): void {
        expect(fn () => $method->invoke($page, [
            'affiliate_id' => $affiliateB->getKey(),
            'total_minor' => 1000,
            'currency' => 'USD',
        ]))->toThrow(ValidationException::class, 'The selected affiliate is not accessible in the current owner scope.');
    });
});
