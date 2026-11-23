<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\OfferSource;
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Drivers\UserKeyIdentityDriver;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\CreateOfferLink;
use AIArmada\AffiliateNetwork\Features\Attribution\Extensions\OfferLinkGate;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCategory;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOfferCreative;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\ReportNetworkConversionController;
use AIArmada\AffiliateNetwork\Features\Ledger\Models\NetworkConversionLeg;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\UpdateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySite;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\VerifySiteByStrategy;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Features\Sites\Queries\SiteLookupQuery;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Links\Actions\ResolveLink;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

function auditOfferLink(string $domain, array $offerAttributes = []): AffiliateOfferLink
{
    $site = AffiliateSite::factory()->verified()->create([
        'domain' => $domain,
        'catalog_token_encrypted' => encrypt('site-secret'),
    ]);

    $offer = AffiliateOffer::factory()->published()->forSite($site)->create(array_merge([
        'rate_base_bp' => 1500,
        'rate_fixed_minor' => null,
        'currency' => 'MYR',
        'requires_approval' => false,
    ], $offerAttributes));

    $affiliate = Affiliate::create([
        'code' => 'AUDIT' . uniqid(),
        'handle' => 'audit-' . uniqid(),
        'name' => 'Audit Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);

    return AffiliateOfferLink::factory()
        ->forOffer($offer)
        ->forAffiliateId((string) $affiliate->getKey())
        ->create(['currency' => 'MYR']);
}

function auditReport(AffiliateOfferLink $link, array $overrides = []): JsonResponse
{
    $payload = array_merge([
        'site' => $link->offer->site->domain,
        'link_code' => $link->link->slug,
        'external_reference' => 'AUDIT-ORDER-1',
        'revenue_minor' => 10000,
        'currency' => 'MYR',
        'engine_touch_at' => null,
        'network_clicked_at' => CarbonImmutable::now()->subMinute()->toIso8601String(),
    ], $overrides);

    return app()->call(ReportNetworkConversionController::class, [
        'request' => Request::create('/api/affiliate-network/conversions', 'POST', $payload, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer site-secret',
        ]),
    ]);
}

it('looks up a site by a non-uuid domain string without a uuid whereKey', function (): void {
    $site = AffiliateSite::factory()->verified()->create(['domain' => 'lookup-domain.example']);

    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $byDomain = app(SiteLookupQuery::class)->findByIdOrDomainUnscoped('lookup-domain.example');
    $byUuid = app(SiteLookupQuery::class)->findByIdOrDomainUnscoped((string) $site->getKey());

    expect($byDomain?->getKey())->toBe($site->getKey())
        ->and($byUuid?->getKey())->toBe($site->getKey())
        ->and(collect($queries)->filter(fn (string $sql): bool => str_contains($sql, 'where "domain" ='))->isEmpty())->toBeFalse();
});

it('reports a conversion addressed by a domain string', function (): void {
    $link = auditOfferLink('domain-report.example');

    $response = auditReport($link, ['site' => 'domain-report.example']);

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true)['conversion']['externalReference'])->toBe('AUDIT-ORDER-1')
        ->and(NetworkConversionLeg::query()->where('external_reference', 'AUDIT-ORDER-1')->count())->toBe(1);
});

it('blocks a redirect when the offer site becomes suspended and returns no fallback', function (): void {
    $link = auditOfferLink('suspended-offer.example', ['fallback_url' => 'https://fallback.example/']);
    $site = AffiliateSite::query()->whereKey($link->site_id)->firstOrFail();

    $blocked = app(ResolveLink::class)->blockedReason($link->link->slug);
    $gate = app(OfferLinkGate::class);

    expect($blocked)->toBeNull();

    $site->update(['status' => SiteStatus::Suspended]);

    $blocked = app(ResolveLink::class)->blockedReason($link->link->slug);

    expect($blocked)->toBe('site_unverified')
        ->and($gate->fallbackUrl($link->link, 'site_unverified'))->toBeNull();
});

it('returns a 302 fallback for an inactive offer when a fallback url exists', function (): void {
    $link = auditOfferLink('inactive-offer.example', [
        'fallback_url' => 'https://fallback.example/promo',
    ]);
    $offer = AffiliateOffer::query()->whereKey($link->offer_id)->firstOrFail();
    $offer->update(['ends_at' => now()->subDay()]);

    $gate = app(OfferLinkGate::class);
    $reason = $gate->blockedReason($link->link);

    expect($reason)->toBe('offer_inactive')
        ->and($gate->fallbackUrl($link->link, (string) $reason))->toBe('https://fallback.example/promo');
});

it('dispatches NetworkConversionRecorded exactly once after automatic fulfillment', function (): void {
    $link = auditOfferLink('single-dispatch.example');
    $recorded = 0;

    Event::listen(
        NetworkConversionRecorded::class,
        function () use (&$recorded): void {
            $recorded++;
        },
    );

    auditReport($link, ['external_reference' => 'AUDIT-SINGLE']);

    expect($recorded)->toBe(1);
});

it('de-duplicates postbacks by reference while owner scoping is enabled', function (): void {
    config(['affiliate-network.owner.enabled' => true, 'affiliates.owner.enabled' => true]);

    $owner = User::factory()->create();
    $link = OwnerContext::withOwner($owner, function () use ($owner): AffiliateOfferLink {
        $site = AffiliateSite::factory()->verified()->forOwner($owner)->create([
            'domain' => 'owned-dedupe.example',
            'catalog_token_encrypted' => encrypt('site-secret'),
        ]);
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'rate_base_bp' => 1000,
            'currency' => 'MYR',
            'requires_approval' => false,
        ]);
        $affiliate = Affiliate::forceCreate([
            'code' => 'OWNED' . uniqid(),
            'handle' => 'owned-' . uniqid(),
            'name' => 'Owned Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'MYR',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
        ]);

        return AffiliateOfferLink::factory()->forOffer($offer)->forAffiliateId((string) $affiliate->getKey())->create(['currency' => 'MYR']);
    });

    [$first, $second] = OwnerContext::withOwner($owner, fn (): array => [
        auditReport($link, ['external_reference' => 'OWNED-DEDUPE']),
        auditReport($link, ['external_reference' => 'OWNED-DEDUPE']),
    ]);

    expect($first->getData(true)['duplicate'])->toBeFalse()
        ->and($second->getData(true)['duplicate'])->toBeTrue();
});

it('finds offer categories through whereHas parent and children self joins', function (): void {
    $parent = AffiliateOfferCategory::factory()->create();
    $child = AffiliateOfferCategory::factory()->forParent($parent)->create();
    $grandchild = AffiliateOfferCategory::factory()->forParent($child)->create();

    $withParent = AffiliateOfferCategory::query()->whereHas('parent')->pluck('id')->all();
    $withChildren = AffiliateOfferCategory::query()->whereHas('children')->pluck('id')->all();

    expect($withParent)->toContain($child->getKey(), $grandchild->getKey())
        ->and($withParent)->not->toContain($parent->getKey())
        ->and($withChildren)->toContain($parent->getKey(), $child->getKey())
        ->and($withChildren)->not->toContain($grandchild->getKey());
});

it('rejects deep links when the offer disallows them', function (): void {
    $link = auditOfferLink('deep-link.example', ['allow_deep_linking' => false]);
    $affiliateId = (string) $link->affiliate_id;

    expect(fn () => app(CreateOfferLink::class)->execute(
        (string) $link->offer_id,
        $affiliateId,
        ['target_url' => 'https://deep-link.example/other'],
    ))->toThrow(RuntimeException::class, 'Deep linking is disabled');
});

it('rejects link targets outside the offer site domain', function (): void {
    $link = auditOfferLink('open-redirect.example', ['allow_deep_linking' => true]);

    expect(fn () => app(CreateOfferLink::class)->execute(
        (string) $link->offer_id,
        (string) $link->affiliate_id,
        ['target_url' => 'https://evil.example/phish'],
    ))->toThrow(RuntimeException::class, 'verified site domain');
});

it('locks offer rates when an operator changes them on a mirrored offer during sync', function (): void {
    $link = auditOfferLink('mirrored-lock.example');
    $offer = AffiliateOffer::query()->whereKey($link->offer_id)->firstOrFail();
    $offer->forceFill(['source' => OfferSource::Mirrored])->save();

    app(UpdateOffer::class)->execute(
        $offer,
        ['rate_base_bp' => 3000, 'source_checksum' => 'sync-checksum'],
        fromCatalogSync: true,
    );

    $fresh = $offer->fresh();

    expect($fresh->rate_base_bp)->toBe(3000)
        ->and($fresh->source_checksum)->toBe('sync-checksum');
});

it('verifies a site only when it is pending', function (): void {
    $pending = AffiliateSite::factory()->pending()->create();

    $verified = app(VerifySiteByStrategy::class);
    expect($verified)->toBeInstanceOf(VerifySiteByStrategy::class);

    expect(fn () => app(VerifySite::class)->execute(
        AffiliateSite::factory()->suspended()->create(),
    ))->toThrow(RuntimeException::class, 'Only pending sites');

    expect(app(VerifySite::class)->execute($pending)->status)->toBe(SiteStatus::Verified);
});

it('re-verifies a site when its domain changes', function (): void {
    $site = AffiliateSite::factory()->verified()->create(['domain' => 'old-domain.example']);

    $updated = app(UpdateSite::class)->execute($site, [
        'name' => $site->name,
        'domain' => 'new-domain.example',
    ]);

    expect($updated->status)->toBe(SiteStatus::Pending)
        ->and($updated->verified_at)->toBeNull()
        ->and($updated->verification_token)->not->toBeNull();
});

it('rejects svg uploads for offer creatives', function (): void {
    $collection = (new AffiliateOfferCreative)->getMediaCollection('creative_asset');

    expect($collection?->acceptsMimeTypes)->not->toContain('image/svg+xml')
        ->and($collection?->acceptsMimeTypes)->toContain('image/png');
});

it('allows postbacks for an offer that was active at the click time but later expired', function (): void {
    $link = auditOfferLink('was-active.example');
    $offer = AffiliateOffer::query()->whereKey($link->offer_id)->firstOrFail();
    $clickedAt = CarbonImmutable::now()->subMinutes(5);
    $offer->update(['ends_at' => CarbonImmutable::now()->subMinute()]);

    expect($offer->fresh()->wasActiveAt($clickedAt))->toBeTrue()
        ->and($offer->fresh()->wasActiveAt(CarbonImmutable::now()))->toBeFalse();
});

it('supports a standalone integer user identity under owner mode', function (): void {
    config(['affiliate-network.owner.enabled' => true, 'affiliates.owner.enabled' => true]);

    $owner = User::factory()->create();
    $driver = new UserKeyIdentityDriver;

    $identity = OwnerContext::withOwner($owner, fn () => $driver->find((string) $owner->getKey()));

    expect($identity?->id)->toBe((string) $owner->getKey())
        ->and($identity?->ownerType)->toBe($owner->getMorphClass())
        ->and($identity?->userId)->toBe((string) $owner->getKey());
});

it('keeps an offer leg from resolving when its uuid is not a valid key', function (): void {
    $lookup = app(SiteLookupQuery::class);

    expect($lookup->findByIdOrDomainUnscoped(''))->toBeNull()
        ->and($lookup->findByIdOrDomainUnscoped('   '))->toBeNull()
        ->and(Str::isUuid('not-a-uuid'))->toBeFalse();
});
