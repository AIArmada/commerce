<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\AffiliateNetworkServiceProvider;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Listeners\FinalizeNetworkAttribution;
use AIArmada\AffiliateNetwork\Listeners\RecordProvisionalNetworkConversion;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\Affiliates\Actions\Affiliates\AttachAffiliateToCart;
use AIArmada\Affiliates\Actions\Conversions\RecordAffiliateConversion;
use AIArmada\Affiliates\AffiliatesServiceProvider;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Cart\Cart;
use AIArmada\Cart\Contracts\CartManagerInterface;
use AIArmada\Cart\Testing\InMemoryStorage;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

function decisionAttributionRequest(string $cookieName, AffiliateOfferLink $link, ?string $clickedAt = null): void
{
    $value = encrypt(json_encode([
        'code' => $link->link->slug,
        'affiliate_id' => $link->affiliate_id,
        'offer_id' => $link->offer_id,
        'clicked_at' => $clickedAt ?? now()->toIso8601String(),
    ], JSON_THROW_ON_ERROR));

    app()->instance('request', Request::create('/', 'GET', [], [$cookieName => $value]));
}

/**
 * @return array{link_code: string, affiliate_id: string, offer_id: string, clicked_at: string, signature: string}
 */
function decisionNetworkAttributionSnapshot(AffiliateOfferLink $link, string $clickedAt): array
{
    $snapshot = [
        'link_code' => (string) $link->trackedSlug(),
        'affiliate_id' => (string) $link->affiliate_id,
        'offer_id' => (string) $link->offer_id,
        'clicked_at' => $clickedAt,
    ];

    return [
        ...$snapshot,
        'signature' => hash_hmac(
            'sha256',
            implode('|', [$snapshot['link_code'], $snapshot['affiliate_id'], $snapshot['offer_id'], $snapshot['clicked_at']]),
            (string) config('app.key'),
        ),
    ];
}

describe('attribution decision', function (): void {
    beforeEach(function (): void {
        config(['affiliate-network.checkout.enabled' => true]);

        $this->site = AffiliateSite::factory()->verified()->create();
        $this->offer = AffiliateOffer::factory()->published()->forSite($this->site)->create();
        $this->affiliate = createTestAffiliate();
        $this->link = AffiliateOfferLink::factory()
            ->forOffer($this->offer)
            ->forAffiliateId((string) $this->affiliate->getKey())
            ->create();
    });

    test('provisional records a leg and stamps the touch once', function (): void {
        decisionAttributionRequest('affiliate_network_link', $this->link);

        $order = Order::factory()->paid()->create();
        $listener = app(RecordProvisionalNetworkConversion::class);

        $listener->handle(new CommissionAttributionRequired($order));
        $listener->handle(new CommissionAttributionRequired($order->fresh()));

        $metadata = $order->fresh()->metadata;

        expect($this->link->fresh()->conversions)->toBe(1)
            ->and($metadata['network_attribution']['link_id'])->toBe($this->link->id)
            ->and($metadata['network_touch_at'])->not->toBeNull()
            ->and(NetworkConversionLeg::query()->count())->toBe(1)
            ->and(NetworkConversionLeg::query()->first()->status)->toBe(LegStatus::Provisional);
    });

    test('provisional abstains without a cookie', function (): void {
        $order = Order::factory()->paid()->create();

        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        $metadata = $order->fresh()->metadata;

        expect($metadata['network_touch_at'])->toBeNull()
            ->and(NetworkConversionLeg::query()->exists())->toBeFalse()
            ->and($this->link->fresh()->conversions)->toBe(0);
    });

    test('profile-less network touch abstains and lets the engine decide', function (): void {
        config([
            'affiliate-network.owner.enabled' => false,
            'affiliates.owner.enabled' => false,
            'orders.owner.enabled' => false,
        ]);

        $owner = User::query()->create([
            'name' => 'Profileless Network Creator',
            'email' => 'profileless-network-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $site = AffiliateSite::factory()->verified()->create([
            'domain' => 'profileless-network.example',
        ]);
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create();
        $profilelessLink = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $owner->getKey())
            ->create();

        config([
            'affiliate-network.owner.enabled' => true,
            'affiliates.owner.enabled' => true,
            'affiliates.tracking.block_self_referral' => false,
            'orders.owner.enabled' => true,
        ]);

        [$cartId, $cart] = OwnerContext::withOwner($owner, function () use ($owner): array {
            $engineAffiliate = Affiliate::forceCreate([
                'code' => 'PROFILELESS-ENGINE-' . uniqid(),
                'name' => 'Profileless Touch Engine Affiliate',
                'status' => 'active',
                'commission_type' => 'percentage',
                'commission_rate' => 1000,
                'currency' => 'MYR',
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => (string) $owner->getKey(),
            ]);
            $cart = new Cart(new InMemoryStorage, 'profileless-cart-' . uniqid(), events: null, eventsEnabled: false);
            app(AttachAffiliateToCart::class)->handle($engineAffiliate, $cart);
            $cart->add('profileless-network-item', 'Order item', 10.00, 1);

            AffiliateAttribution::query()
                ->where('cart_identifier', $cart->getIdentifier())
                ->update(['last_referred_at' => now()]);

            return [$cart->getId(), $cart];
        });

        $cartManager = mock(CartManagerInterface::class);
        $cartManager->shouldReceive('getById')->once()->with($cartId)->andReturn($cart);
        app()->instance(CartManagerInterface::class, $cartManager);
        $engineAttribution = OwnerContext::withOwner($owner, fn () => app(RecordAffiliateConversion::class)
            ->resolveAttributionFor($cart));

        expect($engineAttribution)->toBeInstanceOf(AffiliateAttribution::class);

        $networkTouch = now()->subMinute()->toIso8601String();
        $order = OwnerContext::withOwner($owner, fn (): Order => Order::factory()->paid()->create([
            'order_number' => 'PROFILELESS-NETWORK-ORDER',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'metadata' => [
                'cart_id' => $cartId,
                'network_attribution' => decisionNetworkAttributionSnapshot($profilelessLink, $networkTouch),
            ],
        ]));

        config(['affiliate-network.checkout.enabled' => true]);
        Event::forget(CommissionAttributionRequired::class);
        (new AffiliatesServiceProvider(app()))->packageBooted();
        app()->getProvider(AffiliateNetworkServiceProvider::class)->packageBooted();

        OwnerContext::withOwner($owner, fn () => event(new CommissionAttributionRequired($order)));

        $metadata = OwnerContext::withOwner($owner, fn (): array => $order->fresh()->metadata);

        expect($metadata['network_touch_at'])->toBeNull()
            ->and($metadata['attribution_winner'])->toBe('engine')
            ->and($metadata['engine_touch_at'])->not->toBeNull()
            ->and(OwnerContext::withOwner(null, fn (): bool => NetworkConversionLeg::query()->withoutOwnerScope()->exists()))
            ->toBeFalse()
            ->and(OwnerContext::withOwner($owner, fn (): int => AffiliateConversion::query()->count()))
            ->toBe(1);
    });

    test('replayed order uses its durable network snapshot without request cookies', function (): void {
        $clickedAt = now()->subHour()->toIso8601String();
        app()->instance('request', Request::create('/orders/replay', 'GET'));

        $order = Order::factory()->paid()->create([
            'metadata' => [
                'network_attribution' => decisionNetworkAttributionSnapshot($this->link, $clickedAt),
            ],
        ]);

        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        $provisional = NetworkConversionLeg::query()->sole();
        $metadata = $order->fresh()->metadata;
        $metadata['attribution_winner'] = 'network';
        $order->update(['metadata' => $metadata]);

        app(FinalizeNetworkAttribution::class)->handle(new CommissionAttributionRequired($order->fresh()));

        expect($metadata['network_attribution']['leg_id'])->toBe((string) $provisional->getKey())
            ->and($provisional->fresh()->status)->toBe(LegStatus::Posted)
            ->and($provisional->fresh()->metadata['fulfilled'])->not->toBeNull();
    });

    test('provisional keeps counters and zeroes mismatched revenue', function (): void {
        decisionAttributionRequest('affiliate_network_link', $this->link);

        $order = Order::factory()->paid()->create([
            'currency' => 'MYR',
            'grand_total' => 12345,
        ]);

        $this->link->update(['currency' => 'USD']);

        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        $fresh = $this->link->fresh();

        expect($fresh->conversions)->toBe(1)
            ->and($fresh->revenue)->toBe(0);
    });

    test('provisional rejects a future click when attribution expiry is disabled', function (): void {
        config(['affiliate-network.checkout.attribution_window_hours' => 0]);
        $clickedAt = now()->addMinutes(6)->toIso8601String();
        app()->instance('request', Request::create('/orders/replay', 'GET'));

        $order = Order::factory()->paid()->create([
            'metadata' => [
                'network_attribution' => decisionNetworkAttributionSnapshot($this->link, $clickedAt),
            ],
        ]);

        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        expect($order->fresh()->metadata['network_touch_at'])->toBeNull()
            ->and(NetworkConversionLeg::query()->exists())->toBeFalse()
            ->and($this->link->fresh()->conversions)->toBe(0);
    });

    test('provisional rejects a durable snapshot with a tampered click time', function (): void {
        $snapshot = decisionNetworkAttributionSnapshot($this->link, now()->subHour()->toIso8601String());
        $snapshot['clicked_at'] = now()->toIso8601String();
        app()->instance('request', Request::create('/orders/replay', 'GET'));

        $order = Order::factory()->paid()->create([
            'metadata' => ['network_attribution' => $snapshot],
        ]);

        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        expect($order->fresh()->metadata['network_touch_at'])->toBeNull()
            ->and(NetworkConversionLeg::query()->exists())->toBeFalse()
            ->and($this->link->fresh()->conversions)->toBe(0);
    });

    test('provisional rejects a durable snapshot with a tampered affiliate id', function (): void {
        $snapshot = decisionNetworkAttributionSnapshot($this->link, now()->subHour()->toIso8601String());
        $replacementAffiliate = createTestAffiliate();
        $snapshot['affiliate_id'] = (string) $replacementAffiliate->getKey();
        $this->link->update(['affiliate_id' => (string) $replacementAffiliate->getKey()]);
        app()->instance('request', Request::create('/orders/replay', 'GET'));

        $order = Order::factory()->paid()->create([
            'metadata' => ['network_attribution' => $snapshot],
        ]);

        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        expect($order->fresh()->metadata['network_touch_at'])->toBeNull()
            ->and(NetworkConversionLeg::query()->exists())->toBeFalse()
            ->and($this->link->fresh()->conversions)->toBe(0);
    });

    test('provisional ignores an unsigned durable snapshot', function (): void {
        $snapshot = decisionNetworkAttributionSnapshot($this->link, now()->subHour()->toIso8601String());
        unset($snapshot['signature']);
        app()->instance('request', Request::create('/orders/replay', 'GET'));

        $order = Order::factory()->paid()->create([
            'metadata' => ['network_attribution' => $snapshot],
        ]);

        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        expect($order->fresh()->metadata['network_touch_at'])->toBeNull()
            ->and(NetworkConversionLeg::query()->exists())->toBeFalse()
            ->and($this->link->fresh()->conversions)->toBe(0);
    });

    test('finalizer supersedes the leg when the engine wins', function (): void {
        decisionAttributionRequest('affiliate_network_link', $this->link);

        $order = Order::factory()->paid()->create();
        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        $metadata = $order->fresh()->metadata;
        $metadata['engine_touch_at'] = now()->toIso8601String();
        $metadata['attribution_winner'] = 'engine';
        $metadata['attribution_decision_reason'] = 'last_touch';
        $order->update(['metadata' => $metadata]);

        app(FinalizeNetworkAttribution::class)->handle(new CommissionAttributionRequired($order->fresh()));

        expect(NetworkConversionLeg::query()->first()->status)->toBe(LegStatus::Superseded)
            ->and(NetworkConversionLeg::query()->first()->metadata['attribution_winner'])->toBe('engine')
            ->and(NetworkConversionLeg::query()->first()->metadata['attribution_decision_reason'])->toBe('last_touch');
    });

    test('finalizer confirms and fulfills when the network wins', function (): void {
        decisionAttributionRequest('affiliate_network_link', $this->link);

        $order = Order::factory()->paid()->create();
        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        $metadata = $order->fresh()->metadata;
        $metadata['engine_touch_at'] = now()->subHour()->toIso8601String();
        $metadata['attribution_winner'] = 'network';
        $order->update(['metadata' => $metadata]);

        config(['affiliate-network.notifications.enabled' => false]);
        $recordedLegId = null;
        Event::listen(NetworkConversionRecorded::class, function (NetworkConversionRecorded $event) use (&$recordedLegId): void {
            $recordedLegId = $event->leg?->getKey();
        });

        app(FinalizeNetworkAttribution::class)->handle(new CommissionAttributionRequired($order->fresh()));

        $leg = NetworkConversionLeg::query()->first();

        expect($leg->status)->toBe(LegStatus::Posted)
            ->and($leg->metadata['attribution_winner'])->toBe('network')
            ->and($leg->metadata['attribution_decision_reason'])->toBe('engine_abstained')
            ->and($leg->metadata['fulfilled'])->not->toBeNull()
            ->and($recordedLegId)->toBe($leg->getKey());
    });

    test('finalizer emits counter revenue for a currency-mismatched leg', function (): void {
        decisionAttributionRequest('affiliate_network_link', $this->link);
        $this->link->update(['currency' => 'USD']);

        $order = Order::factory()->paid()->create([
            'currency' => 'MYR',
            'grand_total' => 12345,
        ]);
        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        $leg = NetworkConversionLeg::query()->sole();
        $metadata = $order->fresh()->metadata;
        $metadata['attribution_winner'] = 'network';
        $order->update(['metadata' => $metadata]);

        config(['affiliate-network.notifications.enabled' => false]);
        $recordedRevenue = [];
        Event::listen(NetworkConversionRecorded::class, function (NetworkConversionRecorded $event) use (&$recordedRevenue, $leg): void {
            if ($event->leg?->getKey() === $leg->getKey()) {
                $recordedRevenue[] = $event->revenueMinor;
            }
        });

        app(FinalizeNetworkAttribution::class)->handle(new CommissionAttributionRequired($order->fresh()));

        expect($recordedRevenue)->toBe([0])
            ->and($leg->fresh()->revenue_minor)->toBe(12345);
    });

    test('finalizer resolves and mutates legs in the affiliate creator owner context', function (): void {
        config()->set('affiliate-network.owner.enabled', true);
        config()->set('affiliates.owner.enabled', true);
        config()->set('orders.owner.enabled', true);

        $creator = User::query()->create([
            'name' => 'Network Creator Owner',
            'email' => 'network-creator-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $orderOwner = User::query()->create([
            'name' => 'Different Order Owner',
            'email' => 'network-order-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);

        foreach (['network', 'engine'] as $winner) {
            [$affiliate, $link] = OwnerContext::withOwner($creator, function () use ($creator, $winner): array {
                $affiliate = Affiliate::forceCreate([
                    'code' => 'OWN' . uniqid(),
                    'handle' => 'owner-' . mb_strtolower($winner) . '-' . uniqid(),
                    'name' => 'Owner Finalizer Affiliate',
                    'status' => 'active',
                    'commission_type' => 'percentage',
                    'commission_rate' => 1000,
                    'currency' => 'MYR',
                    'owner_type' => $creator->getMorphClass(),
                    'owner_id' => (string) $creator->getKey(),
                ]);
                $site = AffiliateSite::factory()->verified()->forOwner($creator)->create([
                    'domain' => 'owner-finalizer-' . $winner . '.example',
                ]);
                $offer = AffiliateOffer::factory()->published()->forSite($site)->create();
                $link = AffiliateOfferLink::factory()
                    ->forOffer($offer)
                    ->forAffiliateId((string) $affiliate->getKey())
                    ->create();

                return [$affiliate, $link];
            });

            $order = OwnerContext::withOwner($orderOwner, fn (): Order => Order::factory()->paid()->create([
                'metadata' => [
                    'network_attribution' => decisionNetworkAttributionSnapshot($link, now()->toIso8601String()),
                ],
            ]));

            OwnerContext::withOwner($orderOwner, fn () => app(RecordProvisionalNetworkConversion::class)
                ->handle(new CommissionAttributionRequired($order)));

            $metadata = OwnerContext::withOwner($orderOwner, fn (): array => $order->fresh()->metadata);
            $legId = $metadata['network_attribution']['leg_id'];
            $metadata['attribution_winner'] = $winner;
            OwnerContext::withOwner($orderOwner, fn () => $order->update(['metadata' => $metadata]));

            OwnerContext::withOwner($orderOwner, fn () => app(FinalizeNetworkAttribution::class)
                ->handle(new CommissionAttributionRequired($order->fresh())));

            $leg = OwnerContext::withOwner(null, fn (): ?NetworkConversionLeg => NetworkConversionLeg::query()
                ->withoutOwnerScope()
                ->whereKey($legId)
                ->first());

            expect($leg)->toBeInstanceOf(NetworkConversionLeg::class)
                ->and($leg->status)->toBe($winner === 'network' ? LegStatus::Posted : LegStatus::Superseded);
        }
    });

    test('finalizer confirms when the engine abstained or is absent', function (): void {
        decisionAttributionRequest('affiliate_network_link', $this->link);

        $order = Order::factory()->paid()->create();
        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        $metadata = $order->fresh()->metadata;
        $metadata['engine_touch_at'] = null;
        $order->update(['metadata' => $metadata]);

        app(FinalizeNetworkAttribution::class)->handle(new CommissionAttributionRequired($order->fresh()));

        $fresh = $order->fresh();

        expect(NetworkConversionLeg::query()->first()->status)->toBe(LegStatus::Posted)
            ->and($fresh->metadata['attribution_winner'])->toBe('network');
    });

    test('finalizer ignores legs that already finalized', function (): void {
        decisionAttributionRequest('affiliate_network_link', $this->link);

        $order = Order::factory()->paid()->create();
        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        $finalizer = app(FinalizeNetworkAttribution::class);
        $finalizer->handle(new CommissionAttributionRequired($order->fresh()));
        $finalizer->handle(new CommissionAttributionRequired($order->fresh()));

        expect(NetworkConversionLeg::query()->count())->toBe(1)
            ->and(NetworkConversionLeg::query()->first()->status)->toBe(LegStatus::Posted);
    });

    test('order listeners register in decision order', function (): void {
        Event::forget(CommissionAttributionRequired::class);

        app()->getProvider(AffiliateNetworkServiceProvider::class)->packageBooted();

        expect(Event::getRawListeners()[CommissionAttributionRequired::class])->toBe([
            RecordProvisionalNetworkConversion::class,
            'AIArmada\Affiliates\Listeners\RecordCommissionForOrder',
            FinalizeNetworkAttribution::class,
        ]);
    });
});
