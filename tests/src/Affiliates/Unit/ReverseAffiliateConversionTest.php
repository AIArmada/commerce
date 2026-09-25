<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ReverseAffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ReversedConversion;

function reversalConversion(int $commissionMinor = 13485): AffiliateConversion
{
    $affiliate = createTestAffiliate();

    return AffiliateConversion::create([
        'affiliate_id' => $affiliate->getKey(),
        'affiliate_code' => $affiliate->code,
        'external_reference' => 'ORDER-REV-' . uniqid(),
        'conversion_type' => 'purchase',
        'subtotal_minor' => 89900,
        'value_minor' => 89900,
        'commission_minor' => $commissionMinor,
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
        'owner_type' => $affiliate->owner_type,
        'owner_id' => $affiliate->owner_id,
    ]);
}

describe('ReverseAffiliateConversion', function (): void {
    test('reversing marks the original and posts a negated leg', function (): void {
        $conversion = reversalConversion();

        $reversal = app(ReverseAffiliateConversion::class)->execute($conversion, 'chargeback');

        expect($reversal->commission_minor)->toBe(-13485)
            ->and($reversal->conversion_type)->toBe('reversal')
            ->and($reversal->reversed_at)->not->toBeNull()
            ->and($conversion->refresh()->status->equals(ReversedConversion::class))->toBeTrue()
            ->and($conversion->reversed_at)->not->toBeNull();
    });

    test('reversing is idempotent per conversion and reason', function (): void {
        $conversion = reversalConversion();
        $action = app(ReverseAffiliateConversion::class);

        $first = $action->execute($conversion, 'refund');
        $second = $action->execute($conversion->refresh(), 'refund');

        expect($second->getKey())->toBe($first->getKey())
            ->and(AffiliateConversion::query()->where('conversion_type', 'reversal')->count())->toBe(1);
    });

    test('zero-commission conversions reverse without a leg', function (): void {
        $conversion = reversalConversion(0);

        app(ReverseAffiliateConversion::class)->execute($conversion, 'cancelled');

        expect($conversion->refresh()->status->equals(ReversedConversion::class))->toBeTrue()
            ->and(AffiliateConversion::query()->where('conversion_type', 'reversal')->exists())->toBeFalse();
    });
});
