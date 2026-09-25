<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\AffiliateNetworkServiceProvider;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Listeners\FinalizeNetworkAttribution;
use AIArmada\AffiliateNetwork\Listeners\RecordProvisionalNetworkConversion;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
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

        expect(NetworkConversionLeg::query()->first()->status)->toBe(LegStatus::Superseded);
    });

    test('finalizer confirms and fulfills when the network wins', function (): void {
        decisionAttributionRequest('affiliate_network_link', $this->link);

        $order = Order::factory()->paid()->create();
        app(RecordProvisionalNetworkConversion::class)->handle(new CommissionAttributionRequired($order));

        $metadata = $order->fresh()->metadata;
        $metadata['engine_touch_at'] = now()->subHour()->toIso8601String();
        $metadata['attribution_winner'] = 'network';
        $order->update(['metadata' => $metadata]);

        app(FinalizeNetworkAttribution::class)->handle(new CommissionAttributionRequired($order->fresh()));

        $leg = NetworkConversionLeg::query()->first();

        expect($leg->status)->toBe(LegStatus::Posted)
            ->and($leg->metadata['fulfilled'])->not->toBeNull();
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
