<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Conversions\ReverseAffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\ApprovedConversion;
use AIArmada\Affiliates\States\ReversedConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;

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

        $reversal = ReverseAffiliateConversion::run($conversion, 'chargeback');

        expect($reversal->commission_minor)->toBe(-13485)
            ->and($reversal->conversion_type)->toBe('reversal')
            ->and($reversal->reversed_at)->not->toBeNull()
            ->and($conversion->refresh()->status->equals(ReversedConversion::class))->toBeTrue()
            ->and($conversion->reversed_at)->not->toBeNull();
    });

    test('reversing is idempotent per conversion and reason', function (): void {
        $conversion = reversalConversion();
        $action = app(ReverseAffiliateConversion::class);

        $first = $action->handle($conversion, 'refund');
        $second = $action->handle($conversion->refresh(), 'refund');

        expect($second->getKey())->toBe($first->getKey())
            ->and(AffiliateConversion::query()->where('conversion_type', 'reversal')->count())->toBe(1);
    });

    test('reversal preserves the original conversion owner', function (): void {
        config()->set('affiliates.owner.enabled', true);
        config()->set('affiliates.owner.include_global', false);

        $owner = User::create([
            'name' => 'Reversal Owner',
            'email' => 'reversal-owner-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $conversion = OwnerContext::withOwner($owner, fn (): AffiliateConversion => reversalConversion());

        $reversal = OwnerContext::withOwner(
            $owner,
            fn (): AffiliateConversion => ReverseAffiliateConversion::run($conversion, 'refund'),
        );

        expect($conversion->fresh()->owner_type)->toBe($owner->getMorphClass())
            ->and($conversion->fresh()->owner_id)->toBe($owner->getKey())
            ->and($reversal->owner_type)->toBe($conversion->fresh()->owner_type)
            ->and($reversal->owner_id)->toBe($conversion->fresh()->owner_id);
    });

    test('zero-commission conversions reverse without a leg', function (): void {
        $conversion = reversalConversion(0);

        ReverseAffiliateConversion::run($conversion, 'cancelled');

        expect($conversion->refresh()->status->equals(ReversedConversion::class))->toBeTrue()
            ->and(AffiliateConversion::query()->where('conversion_type', 'reversal')->exists())->toBeFalse();
    });
});
