<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Listeners\RecordNetworkConversionForOrder;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Illuminate\Http\Request;

function networkAttributionRequest(string $cookieName, AffiliateOfferLink $link): void
{
    $value = encrypt(json_encode([
        'code' => $link->link->slug,
        'affiliate_id' => $link->affiliate_id,
        'offer_id' => $link->offer_id,
        'clicked_at' => now()->toIso8601String(),
    ], JSON_THROW_ON_ERROR));

    app()->instance('request', Request::create('/', 'GET', [], [$cookieName => $value]));
}

describe('RecordNetworkConversionForOrder', function (): void {
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

    test('records conversion once and ignores redelivered events', function (): void {
        networkAttributionRequest('affiliate_network_link', $this->link);

        $order = Order::factory()->paid()->create();
        $listener = app(RecordNetworkConversionForOrder::class);

        $listener->handle(new CommissionAttributionRequired($order));
        $listener->handle(new CommissionAttributionRequired($order->fresh()));

        expect($this->link->fresh()->conversions)->toBe(1)
            ->and($order->fresh()->metadata['network_attribution']['link_id'])->toBe($this->link->id);
    });

    test('skips orders that already carry network attribution', function (): void {
        $order = Order::factory()->paid()->create([
            'metadata' => ['network_attribution' => ['link_id' => 'existing']],
        ]);

        app(RecordNetworkConversionForOrder::class)->handle(new CommissionAttributionRequired($order));

        expect($this->link->fresh()->conversions)->toBe(0);
    });

    test('aggregates revenue when the order currency matches the link currency', function (): void {
        networkAttributionRequest('affiliate_network_link', $this->link);

        $order = Order::factory()->paid()->create([
            'currency' => $this->link->currency,
            'grand_total' => 12345,
        ]);

        app(RecordNetworkConversionForOrder::class)->handle(new CommissionAttributionRequired($order));

        $fresh = $this->link->fresh();

        expect($fresh->conversions)->toBe(1)
            ->and($fresh->revenue)->toBe(12345);
    });

    test('skips revenue but keeps the conversion when currencies differ', function (): void {
        networkAttributionRequest('affiliate_network_link', $this->link);

        $order = Order::factory()->paid()->create([
            'currency' => 'MYR',
            'grand_total' => 12345,
        ]);

        $this->link->update(['currency' => 'USD']);

        app(RecordNetworkConversionForOrder::class)->handle(new CommissionAttributionRequired($order));

        $fresh = $this->link->fresh();

        expect($fresh->conversions)->toBe(1)
            ->and($fresh->revenue)->toBe(0);
    });
});
