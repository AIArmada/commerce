<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\ReportNetworkConversionController;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Listeners\FinalizeNetworkAttribution;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Listeners\RecordProvisionalNetworkConversion;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\CreatorBalancesQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Platform\AttributionCookie;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Events\CommissionAttributionRequired;
use AIArmada\Orders\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
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

function reportConversion(array $payload, ?string $token): JsonResponse
{
    return app()->call(ReportNetworkConversionController::class, [
        'request' => postbackRequest($payload, $token),
    ]);
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

        $response = reportConversion([
            'site' => 'postback.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-1',
            'revenue_minor' => 89900,
            'currency' => 'MYR',
        ], 'site-secret');

        expect($response->getStatusCode())->toBe(200);

        $payload = $response->getData(true);

        expect($payload['duplicate'])->toBeFalse()
            ->and($payload['conversion']['commissionMinor'])->toBe(13485)
            ->and($payload['conversion']['affiliateId'])->toBe((string) $affiliate->getKey())
            ->and($payload['conversion']['externalReference'])->toBe('ORDER-1')
            ->and($payload['conversion']['metadata']['attribution_winner'])->toBe('network')
            ->and($payload['decision'])->toBe(['attribution_winner' => 'network', 'attribution_decision_reason' => 'engine_unattributed'])
            ->and($link->fresh()->conversions)->toBe(1)
            ->and($link->fresh()->revenue)->toBe(89900)
            ->and(AffiliateConversion::where('external_reference', 'ORDER-1')->count())->toBe(1);
    });

    test('redeliveries return existing state without touching counters', function (): void {
        [$site, $offer, $affiliate, $link] = postbackFixtures('redeliver.example');

        $payload = [
            'site' => 'redeliver.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-9',
            'revenue_minor' => 5000,
        ];

        $first = reportConversion($payload, 'site-secret');
        expect($first->getData(true)['duplicate'])->toBeFalse();
        $second = reportConversion($payload, 'site-secret');
        expect($second->getData(true)['duplicate'])->toBeTrue();

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
        $snapshot['signature'] = AttributionCookie::signature($snapshot);
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
        $duplicate = reportConversion([
            'site' => 'checkout-leg-duplicate.example',
            'link_code' => $link->link->slug,
            'external_reference' => $externalReference,
            'revenue_minor' => 9999,
            'engine_touch_at' => $clickedAt->subMinute()->toIso8601String(),
            'network_clicked_at' => $clickedAt->toIso8601String(),
        ], 'site-secret')->getData(true);

        expect($finalized->status)->toBe(LegStatus::Superseded)
            ->and($finalized->metadata['attribution_winner'])->toBe('engine')
            ->and($finalized->metadata['attribution_decision_reason'])->toBe('last_touch')
            ->and($duplicate['duplicate'])->toBeTrue()
            ->and($duplicate['decision'])->toBe(['attribution_winner' => 'engine', 'attribution_decision_reason' => 'last_touch']);
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

        $response = OwnerContext::withOwner($actor, fn () => reportConversion([
            'site' => 'profileless-postback.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'PROFILELESS-POSTBACK-ORDER',
            'revenue_minor' => 5000,
        ], 'site-secret'));
        $payload = $response->getData(true);

        expect($response->getStatusCode())->toBe(422)
            ->and($payload['error'])->toBe('affiliate_profile_required')
            ->and($payload['message'])->toContain('Complete affiliate registration')
            ->and(NetworkConversionLeg::query()->withoutOwnerScope()->exists())->toBeFalse()
            ->and($link->fresh()->conversions)->toBe(0);
    });

    test('network wins when its click is newer than the merchant engine touch', function (): void {
        [, , , $link] = postbackFixtures('network-wins.example');
        $networkClickedAt = CarbonImmutable::now()->subMinutes(2);
        $engineTouchAt = $networkClickedAt->subSecond();

        $payload = reportConversion([
            'site' => 'network-wins.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-NETWORK-WINS',
            'revenue_minor' => 5000,
            'engine_touch_at' => $engineTouchAt->toIso8601String(),
            'network_clicked_at' => $networkClickedAt->toIso8601String(),
        ], 'site-secret')->getData(true);

        expect($payload['decision'])->toBe(['attribution_winner' => 'network', 'attribution_decision_reason' => 'network_last_touch'])
            ->and($payload['conversion']['status'])->toBe(LegStatus::Posted->value)
            ->and(NetworkConversionLeg::query()->sole()->metadata['attribution_winner'])->toBe('network');
    });

    test('engine wins ties and supersedes the network leg without payable balance or fulfillment', function (): void {
        [, , , $link] = postbackFixtures('engine-wins.example');
        $touchAt = CarbonImmutable::now()->subMinutes(2);

        $payload = reportConversion([
            'site' => 'engine-wins.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-ENGINE-WINS',
            'revenue_minor' => 7000,
            'engine_touch_at' => $touchAt->toIso8601String(),
            'network_clicked_at' => $touchAt->toIso8601String(),
        ], 'site-secret')->getData(true);
        $leg = NetworkConversionLeg::query()->sole();
        $balances = app(CreatorBalancesQuery::class)->for((string) $link->affiliate_id);

        expect($payload['decision'])->toBe(['attribution_winner' => 'engine', 'attribution_decision_reason' => 'last_touch'])
            ->and($leg->status)->toBe(LegStatus::Superseded)
            ->and($leg->metadata['attribution_winner'])->toBe('engine')
            ->and($leg->metadata['attribution_decision_reason'])->toBe('last_touch')
            ->and($link->fresh()->conversions)->toBe(1)
            ->and($link->fresh()->revenue)->toBe(7000)
            ->and($balances['MYR']->netPayableMinor)->toBe(0)
            ->and($balances['MYR']->payableMinor)->toBe(0)
            ->and($leg->metadata['fulfilled'] ?? null)->toBeNull()
            ->and(AffiliateConversion::where('external_reference', 'ORDER-ENGINE-WINS')->exists())->toBeFalse();
    });

    test('a null engine touch lets the network win as an engine abstention', function (): void {
        [, , , $link] = postbackFixtures('engine-abstained.example');

        $payload = reportConversion([
            'site' => 'engine-abstained.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-ENGINE-ABSTAINED',
            'revenue_minor' => 2500,
            'engine_touch_at' => null,
        ], 'site-secret')->getData(true);

        expect($payload['decision'])->toBe(['attribution_winner' => 'network', 'attribution_decision_reason' => 'engine_unattributed'])
            ->and(NetworkConversionLeg::query()->sole()->status)->toBe(LegStatus::Posted);
    });

    test('redelivery keeps the first decision when reported touch times change', function (): void {
        [, , , $link] = postbackFixtures('decision-redeliver.example');
        $networkClickedAt = CarbonImmutable::now()->subMinutes(3);

        $first = reportConversion([
            'site' => 'decision-redeliver.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-FIRST-DECISION',
            'revenue_minor' => 2500,
            'engine_touch_at' => $networkClickedAt->subMinute()->toIso8601String(),
            'network_clicked_at' => $networkClickedAt->toIso8601String(),
        ], 'site-secret')->getData(true);

        $redelivery = reportConversion([
            'site' => 'decision-redeliver.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-FIRST-DECISION',
            'revenue_minor' => 9999,
            'engine_touch_at' => $networkClickedAt->addMinute()->toIso8601String(),
            'network_clicked_at' => $networkClickedAt->toIso8601String(),
        ], 'site-secret')->getData(true);

        expect($first['decision'])->toBe(['attribution_winner' => 'network', 'attribution_decision_reason' => 'network_last_touch'])
            ->and($redelivery['duplicate'])->toBeTrue()
            ->and($redelivery['conversion']['status'])->toBe(LegStatus::Posted->value)
            ->and($link->fresh()->conversions)->toBe(1)
            ->and($link->fresh()->revenue)->toBe(2500);
    });

    test('rejects reported touches more than five minutes in the future', function (): void {
        [, , , $link] = postbackFixtures('future-touch.example');

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

            expect(reportConversion($payload, 'site-secret')->getStatusCode())->toBe(422);
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

        expect(fn () => app()->call(ReportNetworkConversionController::class, [
            'request' => Request::create('/api/affiliate-network/conversions', 'POST', $payload, [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer site-secret',
            ]),
        ]))->toThrow(ValidationException::class)
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

        $response = OwnerContext::withOwner($requestOwner, fn () => reportConversion([
            'site' => 'owner-postback.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'OWNER-ORDER-1',
            'revenue_minor' => 5000,
            'currency' => 'MYR',
        ], 'site-secret'));

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

        $payload = [
            'site' => 'secure.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-X',
            'revenue_minor' => 100,
        ];

        expect(reportConversion($payload, 'wrong')->getStatusCode())->toBe(401);
        expect(reportConversion($payload, null)->getStatusCode())->toBe(401);

        AffiliateSite::factory()->verified()->create([
            'domain' => 'other.example',
            'catalog_token_encrypted' => encrypt('other-secret'),
        ]);

        $foreign = array_merge($payload, ['site' => 'other.example']);

        expect(reportConversion($foreign, 'other-secret')->getStatusCode())->toBe(404);
        expect($link->fresh()->conversions)->toBe(0);
    });
});
