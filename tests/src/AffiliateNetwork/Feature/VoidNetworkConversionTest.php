<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\PostConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Actions\ReverseConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\ReportNetworkConversionController;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\VoidNetworkConversionController;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Ledger\Queries\CreatorBalancesQuery;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Features\Conversions\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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
    $request = Request::create('/api/affiliate-network/conversions', 'POST', [
        'site' => $link->site->domain,
        'link_code' => $link->link->slug,
        'external_reference' => $reference,
        'revenue_minor' => 89900,
        'currency' => 'MYR',
        'engine_touch_at' => $engineTouchAt?->toIso8601String(),
        'network_clicked_at' => CarbonImmutable::now()->subMinutes(10)->toIso8601String(),
    ], [], [], ['HTTP_AUTHORIZATION' => 'Bearer site-secret']);

    $response = app()->call(ReportNetworkConversionController::class, ['request' => $request]);

    expect($response->getStatusCode())->toBe(200);
}

function voidConversion(array $payload, ?string $token)
{
    $server = $token !== null ? ['HTTP_AUTHORIZATION' => 'Bearer ' . $token] : [];
    $payload = array_merge(['reason' => 'refund'], $payload);

    return app()->call(VoidNetworkConversionController::class, [
        'request' => Request::create('/api/affiliate-network/conversions/void', 'POST', $payload, [], [], $server),
    ]);
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
    return OwnerContext::withOwner($creatorOwner, fn (): NetworkConversionLeg => app(PostConversionLeg::class)->execute(
        offerId: (string) $link->offer_id,
        affiliateId: (string) $link->affiliate_id,
        revenueMinor: 89900,
        currency: 'MYR',
        externalReference: $reference,
        linkId: (string) $link->getKey(),
    ));
}

function registerVoidPostbackRoute(): void
{
    config()->set('affiliate-network.postbacks.enabled', true);

    Route::middleware([])
        ->prefix('api/affiliate-network')
        ->group(function (): void {
            Route::post('/conversions/void', VoidNetworkConversionController::class);
        });
}

describe('VoidNetworkConversionController', function (): void {
    test('voids a posted leg, creates one companion, drops creator balance, and retracts the merchant posting', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-posted.example');

        reportForVoid($link, 'ORDER-VOID-1');

        expect(app(CreatorBalancesQuery::class)->for((string) $affiliate->getKey())['MYR']->paidMinor)->toBe(13485);

        $response = voidConversion([
            'site' => 'void-posted.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-VOID-1',
        ], 'site-secret');

        expect($response->getStatusCode())->toBe(200);

        $payload = $response->getData(true);
        $legs = NetworkConversionLeg::query()->where('link_id', $link->getKey())->get();
        $balance = app(CreatorBalancesQuery::class)->for((string) $affiliate->getKey())['MYR'];

        expect($payload['ok'])->toBeTrue()
            ->and($payload['duplicate'])->toBeFalse()
            ->and($payload['conversion']['status'])->toBe(LegStatus::Reversed->value)
            ->and($legs)->toHaveCount(2)
            ->and($legs->where('status', LegStatus::Reversed))->toHaveCount(2)
            ->and($balance->payableMinor)->toBe(0)
            ->and($balance->netPayableMinor)->toBe(-13485);

        $merchantConversion = AffiliateConversion::where('external_reference', 'ORDER-VOID-1')->firstOrFail();
        $merchantStatus = mb_strtolower(class_basename($merchantConversion->status->getMorphClass()));

        expect($merchantStatus)->toBeIn(['rejected', 'rejectedconversion', 'reversed', 'reversedconversion']);
    });

    test('repeat voids are idempotent and never create a second companion', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-twice.example');

        reportForVoid($link, 'ORDER-VOID-2');

        $payload = [
            'site' => 'void-twice.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-VOID-2',
        ];

        $first = voidConversion($payload, 'site-secret');
        $second = voidConversion($payload, 'site-secret');

        expect($first->getStatusCode())->toBe(200)
            ->and($second->getStatusCode())->toBe(200)
            ->and($second->getData(true)['duplicate'])->toBeTrue()
            ->and($second->getData(true)['conversion']['status'])->toBe(LegStatus::Reversed->value)
            ->and(NetworkConversionLeg::query()->where('link_id', $link->getKey())->count())->toBe(2);
    });

    test('voiding a superseded leg is a no-op acknowledgement without a companion', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-superseded.example');

        reportForVoid($link, 'ORDER-VOID-3', CarbonImmutable::now()->subMinute());

        $response = voidConversion([
            'site' => 'void-superseded.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-VOID-3',
        ], 'site-secret');

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true)['conversion']['status'])->toBe(LegStatus::Superseded->value)
            ->and($response->getData(true)['duplicate'])->toBeTrue()
            ->and(NetworkConversionLeg::query()->where('link_id', $link->getKey())->count())->toBe(1);
    });

    test('unknown reference is 404 and bad credentials are 401', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-missing.example');

        $payload = [
            'site' => 'void-missing.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-UNKNOWN',
        ];

        expect(voidConversion($payload, 'site-secret')->getStatusCode())->toBe(404)
            ->and(voidConversion($payload, 'wrong-secret')->getStatusCode())->toBe(401);
    });

    test('refuses remote voids for provisional checkout-owned legs', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-provisional.example');
        $leg = app(PostConversionLeg::class)->execute(
            offerId: (string) $offer->getKey(),
            affiliateId: (string) $affiliate->getKey(),
            revenueMinor: 89900,
            currency: 'MYR',
            externalReference: 'ORDER-PROVISIONAL',
            status: LegStatus::Provisional,
            linkId: (string) $link->getKey(),
        );
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
            ->assertJsonPath('conversion.status', LegStatus::Reversed->value);

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
        $balanceBefore = OwnerContext::withOwner($ownerB, fn (): array => app(CreatorBalancesQuery::class)
            ->for((string) $affiliateB->getKey()));

        registerVoidPostbackRoute();

        $response = OwnerContext::withOwner($ownerA, fn () => $this->withToken('site-secret-tenant-a')
            ->postJson('/api/affiliate-network/conversions/void', [
                'site' => $siteA->domain,
                'link_code' => $linkB->link->slug,
                'external_reference' => 'ORDER-TENANT-B',
                'reason' => 'refund',
            ]));

        $balanceAfter = OwnerContext::withOwner($ownerB, fn (): array => app(CreatorBalancesQuery::class)
            ->for((string) $affiliateB->getKey()));
        $legCount = OwnerContext::withOwner($ownerB, fn (): int => NetworkConversionLeg::query()
            ->where('link_id', $linkB->getKey())
            ->count());
        $freshLeg = OwnerContext::withOwner($ownerB, fn (): NetworkConversionLeg => NetworkConversionLeg::query()
            ->findOrFail($legB->getKey()));

        $response->assertNotFound();
        expect($freshLeg->status)->toBe(LegStatus::Posted)
            ->and($balanceAfter['MYR']->payableMinor)->toBe($balanceBefore['MYR']->payableMinor)
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
        $balance = OwnerContext::withOwner($creatorOwner, fn (): array => app(CreatorBalancesQuery::class)
            ->for((string) $affiliate->getKey()));

        $response->assertSuccessful()
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('conversion.status', LegStatus::Reversed->value);

        expect($freshLeg->status)->toBe(LegStatus::Reversed)
            ->and($legCount)->toBe(2)
            ->and($balance['MYR']->netPayableMinor ?? 0)->toBe(0);
    });

    test('reports a concurrent reversal as duplicate after the original was read stale', function (): void {
        [$site, $offer, $affiliate, $link] = voidFixtures('void-stale-read.example');
        reportForVoid($link, 'ORDER-STALE-READ');
        $leg = NetworkConversionLeg::query()
            ->where('link_id', $link->getKey())
            ->where('external_reference', 'ORDER-STALE-READ')
            ->firstOrFail();

        app(ReverseConversionLeg::class)
            ->execute((string) $leg->getKey(), 'first concurrent refund');

        $response = voidConversion([
            'site' => 'void-stale-read.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-STALE-READ',
        ], 'site-secret');

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true)['duplicate'])->toBeTrue()
            ->and(NetworkConversionLeg::query()->where('link_id', $link->getKey())->count())->toBe(2);
    });
});
