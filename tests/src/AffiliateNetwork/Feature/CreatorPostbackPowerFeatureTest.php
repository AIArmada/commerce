<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Contracts\CreatorPostbackTransport;
use AIArmada\AffiliateNetwork\Data\CreatorPostbackResponse;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\PostbackDeliveryStatus;
use AIArmada\AffiliateNetwork\Data\PostbackEventType;
use AIArmada\AffiliateNetwork\Drivers\HttpCreatorPostbackDriver;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\PostConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\ConfigureCreatorPostback;
use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\DeleteCreatorPostback;
use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\DeliverCreatorPostback;
use AIArmada\AffiliateNetwork\Features\Postbacks\Actions\EnqueueCreatorPostbacks;
use AIArmada\AffiliateNetwork\Features\Postbacks\EntryPoints\Jobs\DeliverCreatorPostbackJob;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackDelivery;
use AIArmada\AffiliateNetwork\Features\Postbacks\Models\CreatorPostbackEndpoint;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\CommerceSupport\Support\PublicHttpUrlGuard;
use Illuminate\Support\Facades\Queue;

final class RecordingPostbackTransport implements CreatorPostbackTransport
{
    /** @var list<array{method: string, url: string, headers: array<string, string>}> */
    public array $sent = [];

    public CreatorPostbackResponse $response;

    public function __construct(?CreatorPostbackResponse $response = null)
    {
        $this->response = $response ?? new CreatorPostbackResponse(true, 200);
    }

    public function send(string $method, string $url, array $headers = [], ?array $body = null): CreatorPostbackResponse
    {
        $this->sent[] = ['method' => $method, 'url' => $url, 'headers' => $headers];

        return $this->response;
    }
}

function postbackAffiliate(): Affiliate
{
    return Affiliate::create([
        'code' => 'PST' . uniqid(),
        'handle' => 'postback-' . uniqid(),
        'name' => 'Postback Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
}

function postbackOffer(): AffiliateOffer
{
    $site = AffiliateSite::factory()->verified()->create(['domain' => 'pb-' . uniqid() . '.example']);

    return AffiliateOffer::factory()->published()->forSite($site)->create([
        'rate_base_bp' => 1000,
        'currency' => 'MYR',
        'requires_approval' => false,
    ]);
}

describe('power feature: creator S2S postbacks', function (): void {
    test('configuring an endpoint stores encrypted secrets and events', function (): void {
        $affiliate = postbackAffiliate();

        $endpoint = app(ConfigureCreatorPostback::class)->execute((string) $affiliate->getKey(), [
            'url_template' => 'https://creator.example/pb?leg={leg_id}&sub={sub1}',
            'http_method' => 'POST',
            'events' => [PostbackEventType::ConversionRecorded->value, PostbackEventType::ConversionReversed->value],
            'signing_secret' => 'shhh',
        ]);

        expect($endpoint->events)->toBe(['conversion.recorded', 'conversion.reversed'])
            ->and($endpoint->signing_secret_encrypted)->not->toBe('shhh')
            ->and(decrypt($endpoint->signing_secret_encrypted))->toBe('shhh');
    });

    test('enqueue resolves macros with rawurlencode and enqueues a delivery job', function (): void {
        Queue::fake();

        $affiliate = postbackAffiliate();
        app(ConfigureCreatorPostback::class)->execute((string) $affiliate->getKey(), [
            'url_template' => 'https://creator.example/pb?leg={leg_id}&sub={sub1}&ref={external_reference}',
            'http_method' => 'POST',
            'events' => [PostbackEventType::ConversionRecorded->value],
        ]);

        $offer = postbackOffer();
        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'ORDER & CO',
            subId: 'bio link',
        );

        $enqueued = app(EnqueueCreatorPostbacks::class)->execute($leg->toSnapshot(), PostbackEventType::ConversionRecorded);

        $delivery = CreatorPostbackDelivery::query()->sole();

        expect($enqueued)->toBe(1)
            ->and($delivery->status)->toBe(PostbackDeliveryStatus::Pending)
            ->and($delivery->resolved_url)->toContain('sub=bio%20link')
            ->and($delivery->resolved_url)->toContain('ref=ORDER%20%26%20CO');

        Queue::assertPushed(DeliverCreatorPostbackJob::class);
    });

    test('enqueue is idempotent per endpoint, leg and event', function (): void {
        Queue::fake();

        $affiliate = postbackAffiliate();
        app(ConfigureCreatorPostback::class)->execute((string) $affiliate->getKey(), [
            'url_template' => 'https://creator.example/pb?leg={leg_id}',
            'http_method' => 'GET',
            'events' => [PostbackEventType::ConversionRecorded->value],
        ]);

        $offer = postbackOffer();
        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'IDEMPOTENT-1',
        );

        $snapshot = $leg->toSnapshot();
        app(EnqueueCreatorPostbacks::class)->execute($snapshot, PostbackEventType::ConversionRecorded);
        $second = app(EnqueueCreatorPostbacks::class)->execute($snapshot, PostbackEventType::ConversionRecorded);

        expect($second)->toBe(0)
            ->and(CreatorPostbackDelivery::query()->count())->toBe(1);
    });

    test('delivery signs the resolved url with HMAC-SHA256 and marks delivered', function (): void {
        $affiliate = postbackAffiliate();
        $endpoint = app(ConfigureCreatorPostback::class)->execute((string) $affiliate->getKey(), [
            'url_template' => 'https://creator.example/pb?leg={leg_id}',
            'http_method' => 'POST',
            'events' => [PostbackEventType::ConversionRecorded->value],
            'signing_secret' => 'top-secret',
        ]);

        $offer = postbackOffer();
        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'SIGN-1',
        );

        Queue::fake();
        app(EnqueueCreatorPostbacks::class)->execute($leg->toSnapshot(), PostbackEventType::ConversionRecorded);
        $delivery = CreatorPostbackDelivery::query()->sole();

        $transport = new RecordingPostbackTransport;
        app()->instance(CreatorPostbackTransport::class, $transport);

        $delivered = app(DeliverCreatorPostback::class)->execute((string) $delivery->getKey());

        expect($delivered->status)->toBe(PostbackDeliveryStatus::Delivered)
            ->and($delivered->http_status)->toBe(200)
            ->and($transport->sent)->toHaveCount(1)
            ->and($transport->sent[0]['headers']['X-Affiliate-Network-Signature'])
            ->toBe(hash_hmac('sha256', (string) $delivery->resolved_url, 'top-secret'));
    });

    test('failed deliveries record the error and retry status', function (): void {
        $affiliate = postbackAffiliate();
        app(ConfigureCreatorPostback::class)->execute((string) $affiliate->getKey(), [
            'url_template' => 'https://creator.example/pb?leg={leg_id}',
            'http_method' => 'POST',
            'events' => [PostbackEventType::ConversionRecorded->value],
        ]);

        $offer = postbackOffer();
        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'FAIL-1',
        );

        Queue::fake();
        app(EnqueueCreatorPostbacks::class)->execute($leg->toSnapshot(), PostbackEventType::ConversionRecorded);
        $delivery = CreatorPostbackDelivery::query()->sole();

        app()->instance(CreatorPostbackTransport::class, new RecordingPostbackTransport(new CreatorPostbackResponse(false, 500, 'http_500')));

        expect(fn () => app(DeliverCreatorPostback::class)->execute((string) $delivery->getKey()))
            ->toThrow(RuntimeException::class);

        $fresh = $delivery->fresh();

        expect($fresh->status)->toBe(PostbackDeliveryStatus::Failed)
            ->and($fresh->http_status)->toBe(500)
            ->and($fresh->attempts)->toBe(1);
    });

    test('deleting an endpoint removes its deliveries', function (): void {
        $affiliate = postbackAffiliate();
        $endpoint = app(ConfigureCreatorPostback::class)->execute((string) $affiliate->getKey(), [
            'url_template' => 'https://creator.example/pb?leg={leg_id}',
            'http_method' => 'POST',
            'events' => [PostbackEventType::ConversionRecorded->value],
        ]);

        CreatorPostbackDelivery::factory()->forEndpoint($endpoint)->create();

        app(DeleteCreatorPostback::class)->execute((string) $endpoint->getKey());

        expect(CreatorPostbackEndpoint::query()->whereKey($endpoint->getKey())->exists())->toBeFalse()
            ->and(CreatorPostbackDelivery::query()->where('endpoint_id', $endpoint->getKey())->exists())->toBeFalse();
    });

    test('the SSRF guard rejects a private-host postback endpoint on delivery', function (): void {
        $affiliate = postbackAffiliate();
        $endpoint = app(ConfigureCreatorPostback::class)->execute((string) $affiliate->getKey(), [
            'url_template' => 'http://169.254.169.254/latest/meta-data/{leg_id}',
            'http_method' => 'GET',
            'events' => [PostbackEventType::ConversionRecorded->value],
        ]);

        $offer = postbackOffer();
        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'SSRF-1',
        );

        Queue::fake();
        app(EnqueueCreatorPostbacks::class)->execute($leg->toSnapshot(), PostbackEventType::ConversionRecorded);
        $delivery = CreatorPostbackDelivery::query()->sole();

        $guard = new PublicHttpUrlGuard(static fn (string $host): array => ['93.184.216.34']);
        app()->instance(PublicHttpUrlGuard::class, $guard);

        $result = (new HttpCreatorPostbackDriver($guard))->send('GET', (string) $delivery->resolved_url);

        expect($result->successful)->toBeFalse()
            ->and($result->error)->not->toBeEmpty();
    });
});

describe('power feature: dynamic sub id passthrough', function (): void {
    test('conversion legs carry click-time sub ids through to the postback macros', function (): void {
        Queue::fake();

        $affiliate = postbackAffiliate();
        app(ConfigureCreatorPostback::class)->execute((string) $affiliate->getKey(), [
            'url_template' => 'https://creator.example/pb?sub1={sub1}&sub2={sub2}&sub3={sub3}',
            'http_method' => 'GET',
            'events' => [PostbackEventType::ConversionRecorded->value],
        ]);

        $offer = postbackOffer();
        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $affiliate->getKey())
            ->create();

        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'SUBS-1',
            linkId: (string) $link->getKey(),
            linkCode: $link->link->slug,
            subId: 'creator-a',
            subId2: 'stories',
            subId3: 'hero',
        );

        expect($leg->sub_id)->toBe('creator-a')
            ->and($leg->sub_id_2)->toBe('stories')
            ->and($leg->sub_id_3)->toBe('hero');

        app(EnqueueCreatorPostbacks::class)->execute($leg->toSnapshot(), PostbackEventType::ConversionRecorded);

        $delivery = CreatorPostbackDelivery::query()->sole();

        expect($delivery->resolved_url)->toContain('sub1=creator-a')
            ->and($delivery->resolved_url)->toContain('sub2=stories')
            ->and($delivery->resolved_url)->toContain('sub3=hero');
    });

    test('a leg that was posted directly records status Posted for postback events', function (): void {
        $offer = postbackOffer();
        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) postbackAffiliate()->getKey(),
            revenueMinor: 10000,
            currency: 'MYR',
            externalReference: 'STATUS-1',
        );

        expect($leg->status)->toBe(LegStatus::Posted)
            ->and(NetworkConversionLeg::query()->where('external_reference', 'STATUS-1')->exists())->toBeTrue();
    });
});
