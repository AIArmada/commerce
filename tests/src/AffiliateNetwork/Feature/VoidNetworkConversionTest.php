<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Contracts\AffiliateIdentityResolver;
use AIArmada\AffiliateNetwork\Data\NetworkAffiliate;
use AIArmada\AffiliateNetwork\Enums\LegStatus;
use AIArmada\AffiliateNetwork\Http\Controllers\ReportNetworkConversionController;
use AIArmada\AffiliateNetwork\Http\Controllers\VoidNetworkConversionController;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Services\CreatorBalances;
use AIArmada\AffiliateNetwork\Services\NetworkBooks;
use AIArmada\AffiliateNetwork\Services\OfferLinkService;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

function voidFixtures(string $domain, int $rateBp = 1500): array
{
    $site = AffiliateSite::factory()->verified()->create([
        'domain' => $domain,
        'catalog_token_encrypted' => encrypt('site-secret'),
    ]);

    $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
        'rate_base_bp' => $rateBp,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
    ]);

    $affiliate = Affiliate::create([
        'code' => 'VOIDPB' . uniqid(),
        'handle' => 'void-postback-' . uniqid(),
        'name' => 'Void Postback Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);

    $link = AffiliateOfferLink::factory()
        ->forOffer($offer)
        ->forAffiliateId((string) $affiliate->getKey())
        ->create(['currency' => 'MYR']);

    return [$site, $offer, $affiliate, $link];
}

function reportForVoid(AffiliateOfferLink $link, string $reference, ?CarbonImmutable $engineTouchAt = null): void
{
    $server = ['HTTP_AUTHORIZATION' => 'Bearer site-secret'];
    $request = Request::create('/api/affiliate-network/conversions', 'POST', [
        'site' => $link->site->domain,
        'link_code' => $link->link->slug,
        'external_reference' => $reference,
        'revenue_minor' => 89900,
        'currency' => 'MYR',
        'engine_touch_at' => $engineTouchAt?->toIso8601String(),
        'network_clicked_at' => CarbonImmutable::now()->subMinutes(10)->toIso8601String(),
    ], [], [], $server);

    $response = app(ReportNetworkConversionController::class)($request, app(OfferLinkService::class));

    expect($response->getStatusCode())->toBe(200);
}

function voidRequest(array $payload, ?string $token): Request
{
    $server = $token !== null ? ['HTTP_AUTHORIZATION' => 'Bearer ' . $token] : [];
    $payload = array_merge(['reason' => 'refund'], $payload);

    return Request::create('/api/affiliate-network/conversions/void', 'POST', $payload, [], [], $server);
}

function voidOwner(string $suffix): User
{
    return User::query()->create([
        'name' => 'Void Owner ' . $suffix,
        'email' => 'void-owner-' . $suffix . '@example.com',
        'password' => 'secret',
    ]);
}

/** @return array{AffiliateSite, AffiliateOffer, Affiliate, AffiliateOfferLink} */
function voidOwnedSiteLink(User $siteOwner, User $creatorOwner, string $domain, string $suffix): array
{
    $site = OwnerContext::withOwner($siteOwner, fn (): AffiliateSite => AffiliateSite::factory()
        ->verified()
        ->create([
            'domain' => $domain,
            'catalog_token_encrypted' => encrypt('site-secret-' . $suffix),
        ]));

    $offer = OwnerContext::withOwner($siteOwner, fn (): AffiliateOffer => AffiliateOffer::factory()
        ->published()
        ->forSite($site)
        ->create([
            'rate_base_bp' => 1500,
            'rate_fixed_minor' => null,
            'currency' => 'MYR',
        ]));

    $affiliate = OwnerContext::withOwner($creatorOwner, fn (): Affiliate => Affiliate::create([
        'code' => 'VOID-' . $suffix . '-' . uniqid(),
        'handle' => 'void-' . $suffix . '-' . uniqid(),
        'name' => 'Void Creator ' . $suffix,
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]));

    $link = OwnerContext::withOwner($creatorOwner, fn (): AffiliateOfferLink => AffiliateOfferLink::factory()
        ->forOffer($offer)
        ->forAffiliateId((string) $affiliate->getKey())
        ->create(['currency' => 'MYR']));
    $link->setRelation('offer', $offer);

    return [$site, $offer, $affiliate, $link];
}

function postVoidOwnedLeg(AffiliateOfferLink $link, User $creatorOwner, string $reference): NetworkConversionLeg
{
    return OwnerContext::withOwner($creatorOwner, fn (): NetworkConversionLeg => app(NetworkBooks::class)
        ->post($link, 89900, 'MYR', $reference));
}

function registerVoidPostbackRoute(): void
{
    config()->set('affiliate-network.postbacks.enabled', true);
    config()->set('affiliate-network.postbacks.prefix', 'api/affiliate-network');
    config()->set('affiliate-network.postbacks.middleware', []);

    require dirname(__DIR__, 4) . '/packages/affiliate-network/routes/api.php';
}

describe('VoidNetworkConversionController', function (): void {
    test('voids a posted leg, creates one companion, drops creator balance, and retracts the merchant posting', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-posted.example');

        reportForVoid($link, 'ORDER-VOID-1');

        expect(app(CreatorBalances::class)->for((string) $affiliate->getKey()))->toBe(['MYR' => 13485]);

        $response = app(VoidNetworkConversionController::class)(
            voidRequest([
                'site' => 'void-posted.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'ORDER-VOID-1',
            ], 'site-secret'),
            app(NetworkBooks::class),
            app(AffiliateIdentityResolver::class),
        );

        expect($response->getStatusCode())->toBe(200);

        $payload = $response->getData(true);
        $legs = NetworkConversionLeg::query()->where('link_id', $link->getKey())->get();

        expect($payload['ok'])->toBeTrue()
            ->and($payload['duplicate'])->toBeFalse()
            ->and($payload['leg_status'])->toBe(LegStatus::Reversed->value)
            ->and($legs)->toHaveCount(2)
            ->and($legs->where('status', LegStatus::Reversed))->toHaveCount(2)
            ->and(app(CreatorBalances::class)->for((string) $affiliate->getKey())['MYR'] ?? 0)->toBe(0);

        $merchantConversion = AffiliateConversion::where('external_reference', 'ORDER-VOID-1')->firstOrFail();
        $merchantStatus = mb_strtolower(class_basename($merchantConversion->status->getMorphClass()));

        expect($merchantStatus)->toBeIn(['rejected', 'rejectedconversion', 'reversed', 'reversedconversion']);
    });

    test('repeat voids are idempotent and never create a second companion', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-twice.example');

        reportForVoid($link, 'ORDER-VOID-2');

        $void = fn () => app(VoidNetworkConversionController::class)(
            voidRequest([
                'site' => 'void-twice.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'ORDER-VOID-2',
            ], 'site-secret'),
            app(NetworkBooks::class),
            app(AffiliateIdentityResolver::class),
        );

        $first = $void();
        $second = $void();

        expect($first->getStatusCode())->toBe(200)
            ->and($second->getStatusCode())->toBe(200)
            ->and($second->getData(true)['duplicate'])->toBeTrue()
            ->and($second->getData(true)['leg_status'])->toBe(LegStatus::Reversed->value)
            ->and(NetworkConversionLeg::query()->where('link_id', $link->getKey())->count())->toBe(2);
    });

    test('voiding a superseded leg is a no-op acknowledgement without a companion', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-superseded.example');

        reportForVoid($link, 'ORDER-VOID-3', CarbonImmutable::now()->subMinute());

        $response = app(VoidNetworkConversionController::class)(
            voidRequest([
                'site' => 'void-superseded.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'ORDER-VOID-3',
            ], 'site-secret'),
            app(NetworkBooks::class),
            app(AffiliateIdentityResolver::class),
        );

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true)['leg_status'])->toBe(LegStatus::Superseded->value)
            ->and($response->getData(true)['duplicate'])->toBeTrue()
            ->and(NetworkConversionLeg::query()->where('link_id', $link->getKey())->count())->toBe(1);
    });

    test('unknown reference is 404 and bad credentials are 401', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-missing.example');

        $call = fn (?string $token) => app(VoidNetworkConversionController::class)(
            voidRequest([
                'site' => 'void-missing.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'ORDER-UNKNOWN',
            ], $token),
            app(NetworkBooks::class),
            app(AffiliateIdentityResolver::class),
        );

        expect($call('site-secret')->getStatusCode())->toBe(404)
            ->and($call('wrong-secret')->getStatusCode())->toBe(401);
    });

    test('refuses remote voids for provisional checkout-owned legs', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-provisional.example');
        $leg = app(NetworkBooks::class)->post($link, 89900, 'MYR', 'ORDER-PROVISIONAL', LegStatus::Provisional);
        registerVoidPostbackRoute();

        $response = $this->withToken('site-secret')->postJson('/api/affiliate-network/conversions/void', [
            'site' => 'void-provisional.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-PROVISIONAL',
            'reason' => 'refund',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'provisional'));

        expect($leg->fresh()->status)->toBe(LegStatus::Provisional)
            ->and(NetworkConversionLeg::query()->where('link_id', $link->getKey())->count())->toBe(1);
    });

    test('allows a refund reversal after a link becomes inactive', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-inactive-link.example');
        reportForVoid($link, 'ORDER-INACTIVE-LINK');
        $link->forceFill(['is_active' => false])->save();
        registerVoidPostbackRoute();

        $response = $this->withToken('site-secret')->postJson('/api/affiliate-network/conversions/void', [
            'site' => 'void-inactive-link.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-INACTIVE-LINK',
            'reason' => 'refund',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('leg_status', LegStatus::Reversed->value);

        expect(NetworkConversionLeg::query()->where('link_id', $link->getKey())->count())->toBe(2);
    });

    test('does not let site A token void site B link and reference over HTTP', function (): void {
        config()->set('affiliate-network.owner.enabled', true);
        config()->set('affiliate-network.owner.include_global', false);
        config()->set('affiliate-network.owner.auto_assign_on_create', true);
        config()->set('affiliates.owner.enabled', true);
        config()->set('affiliates.owner.include_global', false);
        config()->set('affiliates.owner.auto_assign_on_create', true);

        $ownerA = voidOwner('tenant-a');
        $ownerB = voidOwner('tenant-b');
        [$siteA] = voidOwnedSiteLink($ownerA, $ownerA, 'void-site-a.example', 'tenant-a');
        [, , $affiliateB, $linkB] = voidOwnedSiteLink($ownerB, $ownerB, 'void-site-b.example', 'tenant-b');
        $legB = postVoidOwnedLeg($linkB, $ownerB, 'ORDER-TENANT-B');
        $balanceBefore = OwnerContext::withOwner($ownerB, fn (): array => app(CreatorBalances::class)
            ->for((string) $affiliateB->getKey()));

        registerVoidPostbackRoute();

        $response = OwnerContext::withOwner($ownerA, fn () => $this->withToken('site-secret-tenant-a')
            ->postJson('/api/affiliate-network/conversions/void', [
                'site' => $siteA->domain,
                'link_code' => $linkB->link->slug,
                'external_reference' => 'ORDER-TENANT-B',
                'reason' => 'refund',
            ]));

        $balanceAfter = OwnerContext::withOwner($ownerB, fn (): array => app(CreatorBalances::class)
            ->for((string) $affiliateB->getKey()));
        $legCount = OwnerContext::withOwner($ownerB, fn (): int => NetworkConversionLeg::query()
            ->where('link_id', $linkB->getKey())
            ->count());
        $freshLeg = OwnerContext::withOwner($ownerB, fn (): NetworkConversionLeg => NetworkConversionLeg::query()
            ->findOrFail($legB->getKey()));

        $response->assertNotFound();
        expect($freshLeg->status)->toBe(LegStatus::Posted)
            ->and($balanceAfter)->toBe($balanceBefore)
            ->and($legCount)->toBe(1);
    });

    test('voids a merchant leg under the creator owner when the request owner differs', function (): void {
        config()->set('affiliate-network.owner.enabled', true);
        config()->set('affiliate-network.owner.include_global', false);
        config()->set('affiliate-network.owner.auto_assign_on_create', true);
        config()->set('affiliates.owner.enabled', true);
        config()->set('affiliates.owner.include_global', false);
        config()->set('affiliates.owner.auto_assign_on_create', true);

        $merchantOwner = voidOwner('request-actor');
        $creatorOwner = voidOwner('creator');
        [$site, , $affiliate, $link] = voidOwnedSiteLink($merchantOwner, $creatorOwner, 'void-cross-owner.example', 'cross-owner');
        $leg = postVoidOwnedLeg($link, $creatorOwner, 'ORDER-CROSS-OWNER');

        registerVoidPostbackRoute();

        $response = OwnerContext::withOwner($merchantOwner, function () use ($merchantOwner, $site, $link): TestResponse {
            expect(OwnerContext::resolve()?->getKey())->toBe($merchantOwner->getKey());

            return $this->withToken('site-secret-cross-owner')
                ->postJson('/api/affiliate-network/conversions/void', [
                    'site' => $site->domain,
                    'link_code' => $link->link->slug,
                    'external_reference' => 'ORDER-CROSS-OWNER',
                    'reason' => 'refund',
                ]);
        });

        $freshLeg = OwnerContext::withOwner($creatorOwner, fn (): NetworkConversionLeg => NetworkConversionLeg::query()
            ->findOrFail($leg->getKey()));
        $legCount = OwnerContext::withOwner($creatorOwner, fn (): int => NetworkConversionLeg::query()
            ->where('link_id', $link->getKey())
            ->count());

        $response->assertSuccessful()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('leg_status', LegStatus::Reversed->value);

        expect($freshLeg->status)->toBe(LegStatus::Reversed)
            ->and($legCount)->toBe(2)
            ->and(OwnerContext::withOwner($creatorOwner, fn (): array => app(CreatorBalances::class)
                ->for((string) $affiliate->getKey())))->toBe([]);
    });

    test('reports a concurrent reversal as duplicate after the original was read stale', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-stale-read.example');
        reportForVoid($link, 'ORDER-STALE-READ');
        $leg = NetworkConversionLeg::query()
            ->where('link_id', $link->getKey())
            ->where('external_reference', 'ORDER-STALE-READ')
            ->firstOrFail();

        $identities = app(AffiliateIdentityResolver::class);
        $identity = $identities->find((string) $affiliate->getKey());
        expect($identity)->toBeInstanceOf(NetworkAffiliate::class);

        $racingIdentities = Mockery::mock(AffiliateIdentityResolver::class);
        $racingIdentities->shouldReceive('find')
            ->once()
            ->with((string) $affiliate->getKey())
            ->andReturnUsing(function (string $affiliateId) use ($identity, $leg): NetworkAffiliate {
                app(NetworkBooks::class)->reverse($leg, 'first concurrent refund');

                return $identity;
            });

        $response = app(VoidNetworkConversionController::class)(
            voidRequest([
                'site' => 'void-stale-read.example',
                'link_code' => $link->link->slug,
                'external_reference' => 'ORDER-STALE-READ',
            ], 'site-secret'),
            app(NetworkBooks::class),
            $racingIdentities,
        );

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true)['duplicate'])->toBeTrue()
            ->and(NetworkConversionLeg::query()->where('link_id', $link->getKey())->count())->toBe(2);
    });
});
