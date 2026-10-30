<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Identity\ClaimAffiliateIdentity;
use AIArmada\Affiliates\Actions\Identity\IssueOwnershipCapability;
use Carbon\CarbonImmutable;
use LogicException;

test('the persisted identity seal cannot be rewritten once set', function (): void {
    $affiliate = createTestAffiliate(['code' => 'SEAL-ONCE', 'purpose' => 'host_internal_only']);

    expect($affiliate->identity_sealed_at)->toBeNull();

    $issued = app(IssueOwnershipCapability::class)->run($affiliate);
    app(ClaimAffiliateIdentity::class)->handle(
        $issued['plaintext_secret'],
        'App\\Models\\User',
        '00000000-0000-0000-0000-000000000001',
        true,
    );

    expect($affiliate->fresh()->identity_sealed_at)->not->toBeNull();

    expect(fn () => $affiliate->fresh()->forceFill([
        'identity_sealed_at' => CarbonImmutable::now()->addDay(),
    ])->save())->toThrow(LogicException::class, 'permanent');
});

test('unclaimed affiliates keep a null seal', function (): void {
    $affiliate = createTestAffiliate(['code' => 'SEAL-UNSET', 'purpose' => 'host_internal_only']);

    app(IssueOwnershipCapability::class)->run($affiliate);

    expect($affiliate->fresh()->identity_sealed_at)->toBeNull();
});
