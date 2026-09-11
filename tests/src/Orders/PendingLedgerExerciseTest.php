<?php

declare(strict_types=1);

use AIArmada\Chip\Data\PurchaseData;
use AIArmada\Chip\Exceptions\ChipValidationException;
use AIArmada\Chip\Support\PurchaseIdempotencyLedger;
use AIArmada\CommerceSupport\Support\OwnerContext;

it('fails closed after a crash between reserve and record until reconciliation resolves the ledger', function (): void {
    $ledger = new PurchaseIdempotencyLedger;
    $brandId = 'ledger-brand';
    $idempotencyKey = 'ledger-key-' . uniqid();
    $fingerprint = hash('sha256', 'ledger-payload');

    $entry = OwnerContext::withOwner(null, fn () => $ledger->reserve(
        brandId: $brandId,
        idempotencyKey: $idempotencyKey,
        fingerprint: $fingerprint,
        data: [
            'client' => ['email' => 'ledger@example.com'],
            'purchase' => ['currency' => 'MYR', 'total' => 1000, 'products' => []],
        ],
    ));

    expect(fn () => OwnerContext::withOwner(null, fn () => $ledger->find(
        brandId: $brandId,
        idempotencyKey: $idempotencyKey,
        fingerprint: $fingerprint,
    )))->toThrow(ChipValidationException::class, 'requires reconciliation');

    $resolvedPurchase = PurchaseData::from([
        'id' => 'purchase-reconciled',
        'brand_id' => $brandId,
        'status' => 'created',
        'reference_generated' => 'ref-reconciled',
        'purchase' => [
            'currency' => 'MYR',
            'total' => 1000,
            'products' => [],
        ],
    ]);

    OwnerContext::withOwner(null, function () use ($entry, $ledger, $resolvedPurchase): void {
        $ledger->record($entry, $resolvedPurchase);
    });

    $found = OwnerContext::withOwner(null, fn () => $ledger->find(
        brandId: $brandId,
        idempotencyKey: $idempotencyKey,
        fingerprint: $fingerprint,
    ));

    expect($found)->toBeInstanceOf(PurchaseData::class)
        ->and($found?->id)->toBe('purchase-reconciled');
});
