<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Http\Controllers\ReportNetworkConversionController;
use AIArmada\AffiliateNetwork\Listeners\FinalizeNetworkAttribution;
use AIArmada\AffiliateNetwork\Listeners\RecordProvisionalNetworkConversion;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\CreatorBalances;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

function postbackRequest(array $payload, ?string $token): Request
{
    $server = $token !== null ? ['HTTP_AUTHORIZATION' => 'Bearer ' . $token] : [];
    $payload = array_merge([
        'engine_touch_at' => null,
        'network_clicked_at' => CarbonImmutable::now()->subMinute()->toIso8601String(),
    ], $payload);

    return Request::create('/api/affiliate-network/conversions', 'POST', $payload, [], [], $server);
}

function postbackFixtures(string $domain, int $rateBp = 1500, ?User $owner = null): array
{
    $createFixtures = static function () use ($domain, $owner, $rateBp): array {
        $siteFactory = AffiliateSite::factory()->verified();

        if ($owner !== null) {
            $siteFactory = $siteFactory->forOwner($owner);
        }

        $site = $siteFactory->create([
            'domain' => $domain,
            'catalog_token_encrypted' => encrypt('site-secret'),
        ]);

        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'rate_base_bp' => $rateBp,
            'rate_fixed_minor' => null,
            'currency' => 'MYR',
        ]);

        $affiliateAttributes = [
            'code' => 'POSTBACK' . uniqid(),
            'handle' => 'postback-' . uniqid(),
            'name' => 'Postback Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'MYR',
        ];

        if ($owner !== null) {
            $affiliateAttributes['owner_type'] = $owner->getMorphClass();
            $affiliateAttributes['owner_id'] = (string) $owner->getKey();
            $affiliate = Affiliate::forceCreate($affiliateAttributes);
        } else {
            $affiliate = Affiliate::create($affiliateAttributes);
        }

        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $affiliate->getKey())
            ->create(['currency' => 'MYR']);

        return [$site, $offer, $affiliate, $link];
    };

    return $owner !== null
        ? OwnerContext::withOwner($owner, $createFixtures)
        : $createFixtures();
}

describe('ReportNetworkConversionController', function (): void {
    test('records counters and ledger conversion for valid site credentials', function (): void {
        [$site, $offer, $affiliate, $link] = postbackFixtures('postback.example');

        $response = app(ReportNetworkConversionController::class)(
            postbackRequest([
                'site' => 'postback.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'ORDER-1',
                'revenue_minor' => 89900,
                'currency' => 'MYR',
            ], 'site-secret'),
            app(OfferLinkService::class)
        );

        expect($response->getStatusCode())->toBe(200);

        $payload = $response->getData(true);

        expect($payload['duplicate'])->toBeFalse()
            ->and($payload['conversion']['commission_minor'])->toBe(13485)
            ->and($payload['conversion']['affiliate_id'])->toBe((string) $affiliate->getKey())
            ->and($payload['conversion']['external_reference'])->toBe('ORDER-1')
            ->and($payload['conversion']['metadata']['attribution_winner'])->toBe('network')
            ->and($payload['decision'])->toBe(['winner' => 'network', 'reason' => 'engine_unattributed'])
            ->and($link->fresh()->conversions)->toBe(1)
            ->and($link->fresh()->revenue)->toBe(89900)
            ->and(AffiliateConversion::where('external_reference', 'ORDER-1')->count())->toBe(1);
    });

    test('redeliveries return existing state without touching counters', function (): void {
        [$site, $offer, $affiliate, $link] = postbackFixtures('redeliver.example');

        $makeRequest = static fn (): Request => postbackRequest([
            'site' => 'redeliver.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-9',
            'revenue_minor' => 5000,
        ], 'site-secret');

        $controller = app(ReportNetworkConversionController::class);
        $links = app(OfferLinkService::class);

        expect($controller($makeRequest(), $links)->getData(true)['duplicate'])->toBeFalse();
        expect($controller($makeRequest(), $links)->getData(true)['duplicate'])->toBeTrue();

        expect($link->fresh()->conversions)->toBe(1)
            ->and(AffiliateConversion::where('external_reference', 'ORDER-9')->count())->toBe(1);
    });

    test('checkout-created leg duplicate postback returns the finalized decision', function (): void {
        config(['affiliate-network.checkout.enabled' => true]);
        [, , , $link] = postbackFixtures('checkout-leg-duplicate.example');
        $clickedAt = CarbonImmutable::now()->subMinutes(3);
        $externalReference = 'CHECKOUT-LEG-DUPLICATE';
        $snapshot = [
            'link_code' => (string) $link->trackedSlug(),
            'affiliate_id' => (string) $link->affiliate_id,
            'offer_id' => (string) $link->offer_id,
            'clicked_at' => $clickedAt->toIso8601String(),
        ];
        $snapshot['signature'] = hash_hmac(
            'sha256',
            implode('|', [$snapshot['link_code'], $snapshot['affiliate_id'], $snapshot['offer_id'], $snapshot['clicked_at']]),
            (string) config('app.key'),
        );
        $order = Order::factory()->paid()->create([
            'order_number' => $externalReference,
            'metadata' => ['network_attribution' => $snapshot],
        ]);
        $event = new CommissionAttributionRequired($order);

        app(RecordProvisionalNetworkConversion::class)->handle($event);
        $provisional = NetworkConversionLeg::query()->sole();
        $metadata = $order->fresh()->metadata;
        $metadata['attribution_winner'] = 'engine';
        $metadata['attribution_decision_reason'] = 'last_touch';
        $order->update(['metadata' => $metadata]);
        app(FinalizeNetworkAttribution::class)->handle(new CommissionAttributionRequired($order->fresh()));

        $finalized = $provisional->fresh();
        $duplicate = app(ReportNetworkConversionController::class)(
            postbackRequest([
                'site' => 'checkout-leg-duplicate.example',
                'link_code' => $link->link->slug,
                'external_reference' => $externalReference,
                'revenue_minor' => 9999,
                'engine_touch_at' => $clickedAt->subMinute()->toIso8601String(),
                'network_clicked_at' => $clickedAt->toIso8601String(),
            ], 'site-secret'),
            app(OfferLinkService::class),
        )->getData(true);

        expect($finalized->status)->toBe(LegStatus::Superseded)
            ->and($finalized->metadata['attribution_winner'])->toBe('engine')
            ->and($finalized->metadata['attribution_decision_reason'])->toBe('last_touch')
            ->and($duplicate['duplicate'])->toBeTrue()
            ->and($duplicate['decision'])->toBe(['winner' => 'engine', 'reason' => 'last_touch']);
    });

    test('profile-less merchant postback returns a clear 422 requirement', function (): void {
        config([
            'affiliate-network.owner.enabled' => false,
            'affiliates.owner.enabled' => false,
        ]);

        $actor = User::query()->create([
            'name' => 'Postback Profileless Creator',
            'email' => 'postback-profileless-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $site = AffiliateSite::factory()->verified()->create([
            'domain' => 'profileless-postback.example',
            'catalog_token_encrypted' => encrypt('site-secret'),
        ]);
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'requires_approval' => false,
        ]);
        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) $actor->getKey())
            ->create(['currency' => 'MYR']);

        config([
            'affiliate-network.owner.enabled' => true,
            'affiliates.owner.enabled' => true,
        ]);

        $response = OwnerContext::withOwner($actor, fn () => app(ReportNetworkConversionController::class)(
            postbackRequest([
                'site' => 'profileless-postback.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'PROFILELESS-POSTBACK-ORDER',
                'revenue_minor' => 5000,
            ], 'site-secret'),
            app(OfferLinkService::class),
        ));
        $payload = $response->getData(true);

        expect($response->getStatusCode())->toBe(422)
            ->and($payload['error'])->toBe('affiliate_profile_required')
            ->and($payload['message'])->toContain('Affiliate profile required')
            ->and(NetworkConversionLeg::query()->withoutOwnerScope()->exists())->toBeFalse()
            ->and($link->fresh()->conversions)->toBe(0);
    });

    test('network wins when its click is newer than the merchant engine touch', function (): void {
        [, , , $link] = postbackFixtures('network-wins.example');
        $networkClickedAt = CarbonImmutable::now()->subMinutes(2);
        $engineTouchAt = $networkClickedAt->subSecond();

        $response = app(ReportNetworkConversionController::class)(
            postbackRequest([
                'site' => 'network-wins.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'ORDER-NETWORK-WINS',
                'revenue_minor' => 5000,
                'engine_touch_at' => $engineTouchAt->toIso8601String(),
                'network_clicked_at' => $networkClickedAt->toIso8601String(),
            ], 'site-secret'),
            app(OfferLinkService::class),
        );
        $payload = $response->getData(true);

        expect($payload['decision'])->toBe(['winner' => 'network', 'reason' => 'last_touch'])
            ->and($payload['conversion']['status'])->toBe(LegStatus::Posted->value)
            ->and(NetworkConversionLeg::query()->sole()->metadata['attribution_winner'])->toBe('network');
    });

    test('engine wins ties and supersedes the network leg without payable balance or fulfillment', function (): void {
        [, , , $link] = postbackFixtures('engine-wins.example');
        $touchAt = CarbonImmutable::now()->subMinutes(2);

        $response = app(ReportNetworkConversionController::class)(
            postbackRequest([
                'site' => 'engine-wins.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'ORDER-ENGINE-WINS',
                'revenue_minor' => 7000,
                'engine_touch_at' => $touchAt->toIso8601String(),
                'network_clicked_at' => $touchAt->toIso8601String(),
            ], 'site-secret'),
            app(OfferLinkService::class),
        );
        $payload = $response->getData(true);
        $leg = NetworkConversionLeg::query()->sole();

        expect($payload['decision'])->toBe(['winner' => 'engine', 'reason' => 'last_touch'])
            ->and($leg->status)->toBe(LegStatus::Superseded)
            ->and($leg->metadata['attribution_winner'])->toBe('engine')
            ->and($leg->metadata['attribution_decision_reason'])->toBe('last_touch')
            ->and($link->fresh()->conversions)->toBe(1)
            ->and($link->fresh()->revenue)->toBe(7000)
            ->and(app(CreatorBalances::class)->for((string) $link->affiliate_id))->toBe([])
            ->and($leg->metadata['fulfilled'] ?? null)->toBeNull()
            ->and(AffiliateConversion::where('external_reference', 'ORDER-ENGINE-WINS')->exists())->toBeFalse();
    });

    test('a null engine touch lets the network win as an engine abstention', function (): void {
        [, , , $link] = postbackFixtures('engine-abstained.example');

        $response = app(ReportNetworkConversionController::class)(
            postbackRequest([
                'site' => 'engine-abstained.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'ORDER-ENGINE-ABSTAINED',
                'revenue_minor' => 2500,
                'engine_touch_at' => null,
            ], 'site-secret'),
            app(OfferLinkService::class),
        );

        expect($response->getData(true)['decision'])->toBe(['winner' => 'network', 'reason' => 'engine_unattributed'])
            ->and(NetworkConversionLeg::query()->sole()->status)->toBe(LegStatus::Posted);
    });

    test('redelivery keeps the first decision when reported touch times change', function (): void {
        [, , , $link] = postbackFixtures('decision-redeliver.example');
        $networkClickedAt = CarbonImmutable::now()->subMinutes(3);
        $controller = app(ReportNetworkConversionController::class);
        $links = app(OfferLinkService::class);

        $first = $controller(postbackRequest([
            'site' => 'decision-redeliver.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-FIRST-DECISION',
            'revenue_minor' => 2500,
            'engine_touch_at' => $networkClickedAt->subMinute()->toIso8601String(),
            'network_clicked_at' => $networkClickedAt->toIso8601String(),
        ], 'site-secret'), $links)->getData(true);

        $redelivery = $controller(postbackRequest([
            'site' => 'decision-redeliver.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-FIRST-DECISION',
            'revenue_minor' => 9999,
            'engine_touch_at' => $networkClickedAt->addMinute()->toIso8601String(),
            'network_clicked_at' => $networkClickedAt->toIso8601String(),
        ], 'site-secret'), $links)->getData(true);

        expect($first['decision'])->toBe(['winner' => 'network', 'reason' => 'last_touch'])
            ->and($redelivery['duplicate'])->toBeTrue()
            ->and($redelivery['decision'])->toBe($first['decision'])
            ->and($redelivery['conversion']['status'])->toBe(LegStatus::Posted->value)
            ->and($link->fresh()->conversions)->toBe(1)
            ->and($link->fresh()->revenue)->toBe(2500);
    });

    test('rejects reported touches more than five minutes in the future', function (): void {
        [, , , $link] = postbackFixtures('future-touch.example');
        $controller = app(ReportNetworkConversionController::class);
        $links = app(OfferLinkService::class);

        foreach (['engine_touch_at', 'network_clicked_at'] as $field) {
            $payload = [
                'site' => 'future-touch.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'ORDER-FUTURE-' . $field,
                'revenue_minor' => 2500,
                'engine_touch_at' => null,
                'network_clicked_at' => CarbonImmutable::now()->subMinute()->toIso8601String(),
            ];
            $payload[$field] = CarbonImmutable::now()->addMinutes(6)->toIso8601String();

            expect($controller(postbackRequest($payload, 'site-secret'), $links)->getStatusCode())->toBe(422);
        }

        expect(NetworkConversionLeg::query()->count())->toBe(0)
            ->and($link->fresh()->conversions)->toBe(0);
    });

    test('requires both touch fields in the remote contract', function (): void {
        [, , , $link] = postbackFixtures('required-touch.example');
        $payload = [
            'site' => 'required-touch.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-MISSING-TOUCH',
            'revenue_minor' => 100,
        ];

        expect(fn () => app(ReportNetworkConversionController::class)(
            Request::create('/api/affiliate-network/conversions', 'POST', $payload, [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer site-secret',
            ]),
            app(OfferLinkService::class),
        ))->toThrow(ValidationException::class)
            ->and($link->fresh()->conversions)->toBe(0);
    });

    test('site credential lookup works before a request owner is resolved', function (): void {
        config()->set('affiliate-network.owner.enabled', true);
        config()->set('affiliates.owner.enabled', true);

        $creator = User::query()->create([
            'name' => 'Postback Creator Owner',
            'email' => 'postback-creator-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        $requestOwner = User::query()->create([
            'name' => 'Postback Request Owner',
            'email' => 'postback-request-' . uniqid() . '@example.com',
            'password' => 'secret',
        ]);
        [$site, $offer, $affiliate, $link] = postbackFixtures('owner-postback.example', owner: $creator);

        $response = OwnerContext::withOwner($requestOwner, fn () => app(ReportNetworkConversionController::class)(
            postbackRequest([
                'site' => 'owner-postback.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'OWNER-ORDER-1',
                'revenue_minor' => 5000,
                'currency' => 'MYR',
            ], 'site-secret'),
            app(OfferLinkService::class),
        ));

        $conversion = OwnerContext::withOwner(null, fn (): ?AffiliateConversion => AffiliateConversion::query()
            ->withoutOwnerScope()
            ->where('external_reference', 'OWNER-ORDER-1')
            ->first());
        $freshLink = OwnerContext::withOwner($creator, fn (): ?AffiliateOfferLink => AffiliateOfferLink::query()
            ->whereKey($link->getKey())
            ->first());

        expect($response->getStatusCode())->toBe(200)
            ->and($freshLink?->conversions)->toBe(1)
            ->and($conversion)->toBeInstanceOf(AffiliateConversion::class)
            ->and($conversion->owner_id)->toBe((string) $creator->getKey());
    });

    test('rejects bad credentials and foreign links', function (): void {
        [$site, $offer, $affiliate, $link] = postbackFixtures('secure.example');

        $controller = app(ReportNetworkConversionController::class);
        $links = app(OfferLinkService::class);

        $payload = [
            'site' => 'secure.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-X',
            'revenue_minor' => 100,
        ];

        expect($controller(postbackRequest($payload, 'wrong'), $links)->getStatusCode())->toBe(401);
        expect($controller(postbackRequest($payload, null), $links)->getStatusCode())->toBe(401);

        $other = AffiliateSite::factory()->verified()->create([
            'domain' => 'other.example',
            'catalog_token_encrypted' => encrypt('other-secret'),
        ]);

        $foreign = postbackRequest(array_merge($payload, ['site' => 'other.example']), 'other-secret');

        expect($controller($foreign, $links)->getStatusCode())->toBe(404);
        expect($link->fresh()->conversions)->toBe(0);
    });
});
