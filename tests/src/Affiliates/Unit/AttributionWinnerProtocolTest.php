<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\AttachAffiliateToCart;
use AIArmada\Affiliates\Actions\Affiliates\TrackAffiliateVisit;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;

function winnerAffiliate(string $code): Affiliate
{
    return Affiliate::create([
        'code' => $code,
        'name' => 'Winner Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'MYR',
    ]);
}

function winnerOrder(string $cartId, string $reference, ?string $networkTouch): Order
{
    return Order::factory()->paid()->create([
        'order_number' => $reference,
        'metadata' => [
            'cart_id' => $cartId,
            'network_touch_at' => $networkTouch,
        ],
    ]);
}

function touchCart(Affiliate $affiliate, string $item, CarbonImmutable $touch): string
{
    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($affiliate, $cart);
    $cart->add($item, 'Order item', 10.00, 1);

    AffiliateAttribution::query()
        ->where('cart_identifier', $cart->getIdentifier())
        ->update(['last_referred_at' => $touch]);

    return $cart->getId();
}

describe('cross-system winner protocol (engine side)', function (): void {
    test('engine records when its touch is newest', function (): void {
        $affiliate = winnerAffiliate('WIN-NEWEST');
        $cartId = touchCart($affiliate, 'winner-1', CarbonImmutable::now());
        $order = winnerOrder($cartId, 'ORD-WIN-1', CarbonImmutable::now()->subHour()->toIso8601String());

        event(new CommissionAttributionRequired($order));

        $metadata = $order->fresh()->metadata;

        expect(AffiliateConversion::query()->count())->toBe(1)
            ->and($metadata['attribution_winner'])->toBe('engine')
            ->and($metadata['engine_touch_at'])->not->toBeNull();
    });

    test('a repeat referral click refreshes the engine winner touch', function (): void {
        $affiliate = winnerAffiliate('WIN-REPEAT');
        $cart = app('cart')->getCurrentCart();
        $cart->add('winner-repeat', 'Repeat Click Item', 10.00, 1);
        $firstClick = CarbonImmutable::parse('2026-02-01T10:00:00+00:00');
        CarbonImmutable::setTestNow($firstClick);

        app(TrackAffiliateVisit::class)->handle($affiliate->code, [
            'cart_identifier' => $cart->getIdentifier(),
            'cart_instance' => $cart->instance(),
        ], 'winner-repeat-cookie');

        $firstAttribution = AffiliateAttribution::query()
            ->where('cart_identifier', $cart->getIdentifier())
            ->sole();
        $firstSeenAt = $firstAttribution->first_seen_at;
        $secondClick = $firstClick->addHour();
        CarbonImmutable::setTestNow($secondClick);

        app(TrackAffiliateVisit::class)->handle($affiliate->code, [
            'cart_identifier' => $cart->getIdentifier(),
            'cart_instance' => $cart->instance(),
        ], 'winner-repeat-cookie');

        $order = winnerOrder(
            $cart->getId(),
            'ORD-WIN-REPEAT',
            $firstClick->addMinutes(30)->toIso8601String(),
        );

        event(new CommissionAttributionRequired($order));

        $attribution = $firstAttribution->fresh();
        $metadata = $order->fresh()->metadata;

        CarbonImmutable::setTestNow();

        expect($attribution->first_seen_at->equalTo($firstSeenAt))->toBeTrue()
            ->and($attribution->last_referred_at->equalTo($secondClick))->toBeTrue()
            ->and(AffiliateConversion::query()->count())->toBe(1)
            ->and($metadata['attribution_winner'])->toBe('engine');
    });

    test('engine yields when the network touch is newest', function (): void {
        $affiliate = winnerAffiliate('WIN-YIELD');
        $cartId = touchCart($affiliate, 'winner-2', CarbonImmutable::now()->subHours(2));
        $order = winnerOrder($cartId, 'ORD-WIN-2', CarbonImmutable::now()->subHour()->toIso8601String());

        event(new CommissionAttributionRequired($order));

        $metadata = $order->fresh()->metadata;

        expect(AffiliateConversion::query()->count())->toBe(0)
            ->and($metadata['attribution_winner'])->toBe('network')
            ->and($metadata['engine_touch_at'])->not->toBeNull();
    });

    test('engine wins ties and malformed network touches', function (): void {
        $affiliate = winnerAffiliate('WIN-TIE');
        $touch = CarbonImmutable::now()->subHour();
        $cartId = touchCart($affiliate, 'winner-3', $touch);
        $order = winnerOrder($cartId, 'ORD-WIN-3', $touch->toIso8601String());

        event(new CommissionAttributionRequired($order));

        expect(AffiliateConversion::query()->count())->toBe(1)
            ->and($order->fresh()->metadata['attribution_winner'])->toBe('engine');
    });

    test('engine records when the network abstained', function (): void {
        $affiliate = winnerAffiliate('WIN-ABSTAIN');
        $cartId = touchCart($affiliate, 'winner-4', CarbonImmutable::now());
        $order = winnerOrder($cartId, 'ORD-WIN-4', null);

        event(new CommissionAttributionRequired($order));

        expect(AffiliateConversion::query()->count())->toBe(1)
            ->and($order->fresh()->metadata['attribution_winner'])->toBe('engine');
    });

    test('engine records alone with no network marker', function (): void {
        $affiliate = winnerAffiliate('WIN-ALONE');
        $cart = app('cart')->getCurrentCart();
        app(AttachAffiliateToCart::class)->handle($affiliate, $cart);
        $cart->add('winner-5', 'Order item', 10.00, 1);

        $order = Order::factory()->paid()->create([
            'order_number' => 'ORD-WIN-5',
            'metadata' => ['cart_id' => $cart->getId()],
        ]);

        event(new CommissionAttributionRequired($order));

        $metadata = $order->fresh()->metadata;

        expect(AffiliateConversion::query()->count())->toBe(1)
            ->and($metadata['attribution_winner'])->toBe('engine')
            ->and($metadata['attribution_decision_reason'])->toBe('engine_alone');
    });

    test('unattributed carts abstain in favor of the network', function (): void {
        $cart = app('cart')->getCurrentCart();
        $cart->add('winner-6', 'Order item', 10.00, 1);

        $order = winnerOrder(
            $cart->getId(),
            'ORD-WIN-6',
            CarbonImmutable::now()->subHour()->toIso8601String(),
        );

        event(new CommissionAttributionRequired($order));

        $metadata = $order->fresh()->metadata;

        expect(AffiliateConversion::query()->count())->toBe(0)
            ->and($metadata['attribution_winner'])->toBe('network')
            ->and($metadata['engine_touch_at'])->toBeNull();
    });

    test('a null referral timestamp abstains even when first-seen timestamps exist', function (): void {
        $affiliate = winnerAffiliate('WIN-NULL-REFERRAL');
        $cartId = touchCart($affiliate, 'winner-null-referral', CarbonImmutable::now());
        $attribution = AffiliateAttribution::query()
            ->where('cart_identifier', app('cart')->getCurrentCart()->getIdentifier())
            ->sole();
        $attribution->forceFill([
            'last_referred_at' => null,
            'first_seen_at' => CarbonImmutable::now(),
        ])->save();
        $order = winnerOrder(
            $cartId,
            'ORD-WIN-NULL-REFERRAL',
            CarbonImmutable::now()->subHour()->toIso8601String(),
        );

        event(new CommissionAttributionRequired($order));

        $metadata = $order->fresh()->metadata;

        expect(AffiliateConversion::query()->count())->toBe(0)
            ->and($metadata['attribution_winner'])->toBe('network')
            ->and($metadata['engine_touch_at'])->toBeNull();
    });
});
