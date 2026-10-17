<?php

declare(strict_types=1);

use AIArmada\Affiliates\Actions\Affiliates\AttachAffiliateToCart;
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateConversion;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\States\Active;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Processing as OrderProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('records affiliate conversions when order commission attribution is required', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'ORDER-AFF',
        'name' => 'Order Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'MYR',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($affiliate, $cart);
    $cart->add('order-item-1', 'Order item', 10.00, 1);

    $cartId = $cart->getId();
    $orderReference = 'ORD-LISTENER-001';

    expect($cartId)->not()->toBeNull();

    $order = Order::factory()->paid()->create([
        'order_number' => $orderReference,
        'metadata' => [
            'cart_id' => $cartId,
        ],
    ]);

    event(new CommissionAttributionRequired($order));

    $conversion = AffiliateConversion::query()->sole();

    expect($conversion->external_reference)->toBe($orderReference)
        ->and($conversion->conversion_type)->toBe('purchase');
});

it('fails closed when an order carries a malformed owner tuple', function (): void {
    Log::spy();

    $affiliate = Affiliate::create([
        'code' => 'ORDER-AFF-MALFORMED',
        'name' => 'Order Affiliate Malformed',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'MYR',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($affiliate, $cart);
    $cart->add('order-item-2', 'Order item', 10.00, 1);

    $cartId = $cart->getId();

    expect($cartId)->not()->toBeNull();

    $order = Order::factory()->paid()->create([
        'order_number' => 'ORD-LISTENER-MALFORMED-001',
        'metadata' => [
            'cart_id' => $cartId,
        ],
    ]);

    // Inject a malformed owner tuple directly into the DB, bypassing the Order model guard.
    // This simulates data corruption or a future migration that invalidates stored owner tuples.
    $bogusType = 'Nonexistent\\OwnerModel\\ThatDoesNotExist';
    $bogusId = Str::uuid()->toString();
    DB::table($order->getTable())->where('id', $order->id)->update([
        'owner_type' => $bogusType,
        'owner_id' => $bogusId,
    ]);
    $order = $order->fresh();

    event(new CommissionAttributionRequired($order));

    expect(AffiliateConversion::query()->count())->toBe(0);

    Log::shouldHaveReceived('warning')
        ->once()
        ->with('affiliates.order_commission_skipped_malformed_owner_tuple', Mockery::on(function (array $context) use ($order, $bogusType): bool {
            return ($context['order_id'] ?? null) === $order->id
                && ($context['owner_type'] ?? null) === $bogusType
                && array_key_exists('message', $context);
        }));
});

it('leaves registration-line commissions to the checkout registration step', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'ORDER-AFF-REGISTRATIONS',
        'name' => 'Registration Line Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'MYR',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($affiliate, $cart);
    $cart->add('registration-line-item', 'Ticket registration', 10.00, 1);

    $order = Order::factory()->paid()->create([
        'order_number' => 'ORD-REGISTRATION-LINES-001',
        'metadata' => [
            'cart_id' => $cart->getId(),
            'payment_data' => [
                'affiliate_commission' => ['strategy' => 'registration_lines'],
            ],
        ],
    ]);

    event(new CommissionAttributionRequired($order));

    expect(AffiliateConversion::query()->count())->toBe(0);
});

it('uses the explicit checkout idempotency key for frozen commercial conversions', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'ORDER-AFF-FROZEN',
        'name' => 'Frozen Attribution Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($affiliate, $cart, [
        'affiliate_program_id' => (string) Str::uuid(),
    ]);
    $cart->add('frozen-item', 'Ticket registration', 100.00, 1);
    $attribution = AffiliateAttribution::query()
        ->where('affiliate_id', $affiliate->getKey())
        ->where('cart_identifier', $cart->getIdentifier())
        ->firstOrFail();

    $action = app(RecordAffiliateConversion::class);
    $payload = [
        'idempotency_key' => 'stable-ticket-registration-key',
        'external_reference' => 'ticket:order-id:registration-id',
        'conversion_type' => 'purchase',
        'subtotal' => 10_000,
        'total' => 10_000,
        'commission_currency' => 'MYR',
    ];
    $first = $action->recordCommercialForFrozenAttribution($cart, $attribution, $payload);
    $retry = $action->recordCommercialForFrozenAttribution($cart, $attribution, $payload);

    $storedIdempotencyKey = hash('sha256', implode('|', [
        'affiliate-conversion',
        $attribution->owner_type ?? 'global',
        $attribution->owner_id ?? 'global',
        (string) $affiliate->getKey(),
        'stable-ticket-registration-key',
    ]));

    expect($first?->id)->toBe($retry?->id)
        ->and(AffiliateConversion::query()->count())->toBe(1)
        ->and(AffiliateConversion::query()->sole()->idempotency_key)->toBe($storedIdempotencyKey)
        ->and(AffiliateConversion::query()->sole()->metadata['caller_idempotency_key'])->toBe('stable-ticket-registration-key');
});

it('namespaces explicit idempotency keys by affiliate', function (): void {
    $firstAffiliate = Affiliate::create([
        'code' => 'ORDER-AFF-KEY-A',
        'name' => 'Idempotency Affiliate A',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
    $secondAffiliate = Affiliate::create([
        'code' => 'ORDER-AFF-KEY-B',
        'name' => 'Idempotency Affiliate B',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($firstAffiliate, $cart);
    $cart->add('same-key-item-a', 'Same key item', 100.00, 1);
    $firstAttribution = AffiliateAttribution::query()
        ->where('affiliate_id', $firstAffiliate->getKey())
        ->where('cart_identifier', $cart->getIdentifier())
        ->firstOrFail();

    $secondAttribution = $firstAttribution->replicate();
    $secondAttribution->affiliate_id = $secondAffiliate->getKey();
    $secondAttribution->affiliate_code = $secondAffiliate->code;
    $secondAttribution->save();

    $action = app(RecordAffiliateConversion::class);
    $common = [
        'idempotency_key' => 'same-caller-key',
        'subtotal' => 10_000,
        'total' => 10_000,
        'commission_currency' => 'MYR',
    ];

    $first = $action->recordCommercialForFrozenAttribution($cart, $firstAttribution, [
        ...$common,
        'external_reference' => 'ticket:first-affiliate',
    ]);
    $second = $action->recordCommercialForFrozenAttribution($cart, $secondAttribution, [
        ...$common,
        'external_reference' => 'ticket:second-affiliate',
    ]);

    expect($first?->id)->not->toBe($second?->id)
        ->and(AffiliateConversion::query()->count())->toBe(2)
        ->and(AffiliateConversion::query()->pluck('idempotency_key')->unique())->toHaveCount(2);
});

it('rejects payload attribution overrides that disagree with the frozen row', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'ORDER-AFF-OVERRIDE',
        'name' => 'Frozen Attribution Override Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($affiliate, $cart, [
        'affiliate_program_id' => (string) Str::uuid(),
        'affiliate_link_id' => (string) Str::uuid(),
    ]);
    $cart->add('frozen-override-item', 'Ticket registration', 100.00, 1);
    $attribution = AffiliateAttribution::query()
        ->where('affiliate_id', $affiliate->getKey())
        ->where('cart_identifier', $cart->getIdentifier())
        ->firstOrFail();

    foreach ([
        'affiliate_code' => 'SOMEONE-ELSE',
        'affiliate_link_id' => (string) Str::uuid(),
        'affiliate_program_id' => (string) Str::uuid(),
    ] as $key => $value) {
        expect(fn () => app(RecordAffiliateConversion::class)->recordCommercialForFrozenAttribution($cart, $attribution, [
            'external_reference' => 'ticket:frozen-override:' . $key,
            $key => $value,
            'subtotal' => 10_000,
            'total' => 10_000,
            'commission_currency' => 'MYR',
        ]))->toThrow(InvalidArgumentException::class, 'Frozen commission attribution');
    }

    expect(AffiliateConversion::query()->count())->toBe(0);
});

it('rejects frozen commercial conversion recording for a Dawah-purpose affiliate', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'ORDER-AFF-DAWAH',
        'name' => 'Dawah Affiliate',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
        'purpose' => 'share_member',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($affiliate, $cart);
    $cart->add('dawah-ticket-item', 'Ticket registration', 100.00, 1);
    $attribution = AffiliateAttribution::query()
        ->where('affiliate_id', $affiliate->getKey())
        ->where('cart_identifier', $cart->getIdentifier())
        ->firstOrFail();

    expect(fn () => app(RecordAffiliateConversion::class)->recordCommercialForFrozenAttribution(
        $cart,
        $attribution,
        ['external_reference' => 'dawah-ticket-registration'],
    ))->toThrow(InvalidArgumentException::class, 'commercial-purpose');

    expect(AffiliateConversion::query()->count())->toBe(0);
});

it('records zero-value conversions for free orders without phantom money', function (): void {
    $affiliate = Affiliate::create([
        'code' => 'ORDER-AFF-FREE',
        'name' => 'Order Affiliate Free',
        'status' => Active::class,
        'commission_type' => 'percentage',
        'commission_rate' => 100,
        'currency' => 'MYR',
    ]);

    $cart = app('cart')->getCurrentCart();
    app(AttachAffiliateToCart::class)->handle($affiliate, $cart);
    $cart->add('free-item-1', 'Free item', 0.00, 1);

    $cartId = $cart->getId();

    expect($cartId)->not()->toBeNull();

    $order = Order::factory()->create([
        'order_number' => 'ORD-LISTENER-FREE-001',
        'status' => OrderProcessing::class,
        'subtotal' => 0,
        'discount_total' => 0,
        'shipping_total' => 0,
        'tax_total' => 0,
        'grand_total' => 0,
        'currency' => 'MYR',
        'metadata' => [
            'cart_id' => $cartId,
        ],
    ]);

    event(new CommissionAttributionRequired($order));

    $conversion = AffiliateConversion::query()->sole();
    $balance = $affiliate->balanceFor('MYR')->fresh();

    expect($conversion->commission_minor)->toBe(0)
        ->and($balance->holding_minor)->toBe(0)
        ->and($balance->available_minor)->toBe(0)
        ->and($balance->lifetime_earnings_minor)->toBe(0);
});
