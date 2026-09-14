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
        'code' => $link->code,
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
            ->forAffiliate($this->affiliate)
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
});
