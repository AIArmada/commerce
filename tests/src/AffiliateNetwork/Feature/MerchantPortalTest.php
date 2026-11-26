<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Data\OfferStatus;
use AIArmada\AffiliateNetwork\Data\SiteStatus;
use AIArmada\AffiliateNetwork\Features\Attribution\Actions\CreateOfferLink;
use AIArmada\AffiliateNetwork\Features\Attribution\Models\AffiliateOfferLink;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\SubmitOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\UpdateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Queries\ResolvePublicOfferQuery;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApproveApplication;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Ledger\EntryPoints\Http\Controllers\ReportNetworkConversionController;
use AIArmada\AffiliateNetwork\Features\Sites\Actions\RegisterSite;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Features\Identity\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

function portalOwner(): User
{
    return User::factory()->create();
}

function portalAffiliate(string $code): Affiliate
{
    return Affiliate::create([
        'code' => $code . uniqid(),
        'name' => 'Portal Affiliate',
        'status' => 'active',
        'commission_type' => 'percentage',
        'commission_rate' => 1000,
        'currency' => 'MYR',
    ]);
}

describe('Merchant portal self-service', function (): void {
    test('registration creates a pending site owned by the merchant', function (): void {
        $owner = portalOwner();

        $site = app(RegisterSite::class)->execute($owner, [
            'name' => 'Merchant Store',
            'domain' => 'Merchant-Example.COM',
            'description' => 'A fine store.',
        ]);

        expect($site->status)->toBe(SiteStatus::Pending)
            ->and($site->domain)->toBe('merchant-example.com')
            ->and($site->owner_type)->toBe($owner->getMorphClass())
            ->and((string) $site->owner_id)->toBe((string) $owner->getKey())
            ->and($site->verification_token)->not->toBeEmpty()
            ->and($site->isVerified())->toBeFalse();
    });

    test('registration rejects duplicate and malformed domains', function (): void {
        AffiliateSite::factory()->create(['domain' => 'taken.example']);

        expect(fn () => app(RegisterSite::class)->execute(portalOwner(), [
            'name' => 'Dup',
            'domain' => 'taken.example',
        ]))->toThrow(ValidationException::class);

        expect(fn () => app(RegisterSite::class)->execute(portalOwner(), [
            'name' => 'Bad',
            'domain' => 'not a domain!!',
        ]))->toThrow(ValidationException::class);
    });

    test('verified sites submit offers as drafts', function (): void {
        $site = AffiliateSite::factory()->verified()->create();

        $offer = app(SubmitOffer::class)->execute($site->toRecord(), [
            'name' => 'Merchant Deal',
            'rate_base_bp' => 1500,
        ]);

        expect($offer->status)->toBe(OfferStatus::Draft)
            ->and((string) $offer->site_id)->toBe((string) $site->getKey());
    });

    test('pending sites cannot submit offers', function (): void {
        $site = AffiliateSite::factory()->pending()->create();

        expect(fn () => app(SubmitOffer::class)->execute($site->toRecord(), ['name' => 'Nope']))
            ->toThrow(AuthorizationException::class);
    });

    test('offers cannot publish for unverified sites', function (): void {
        $pending = AffiliateSite::factory()->pending()->create();
        $verified = AffiliateSite::factory()->verified()->create();

        expect(fn () => app(CreateOffer::class)->execute($pending->toRecord(), [
            'name' => 'Sneaky',
            'status' => OfferStatus::Published,
        ]))->toThrow(ValidationException::class);

        $draft = app(SubmitOffer::class)->execute($verified->toRecord(), ['name' => 'Legit']);

        expect(fn () => app(UpdateOffer::class)->execute($draft, ['status' => OfferStatus::Published]))
            ->not->toThrow(ValidationException::class);

        $draft->refresh();

        expect($draft->status)->toBe(OfferStatus::Published);
    });

    test('activating an unverified offer is refused', function (): void {
        $site = AffiliateSite::factory()->pending()->create();
        $offer = AffiliateOffer::factory()->draft()->forSite($site)->create();

        expect(fn () => app(UpdateOffer::class)->execute($offer, ['status' => OfferStatus::Published]))
            ->toThrow(ValidationException::class);
    });

    test('affiliates cannot apply to unverified-site offers', function (): void {
        $site = AffiliateSite::factory()->pending()->create();
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'visibility' => 'public',
        ]);

        expect(fn () => app(ApplyToOffer::class)->execute($offer->id, (string) portalAffiliate('APPLY')->getKey()))
            ->toThrow(RuntimeException::class);
    });

    test('applications cannot be approved for unverified sites', function (): void {
        $site = AffiliateSite::factory()->pending()->create();
        $offer = AffiliateOffer::factory()->draft()->forSite($site)->create();
        $application = AffiliateOfferApplication::create([
            'offer_id' => $offer->getKey(),
            'affiliate_id' => portalAffiliate('APPROVE')->getKey(),
            'status' => 'pending',
        ]);

        expect(fn () => app(ApproveApplication::class)->execute($application))
            ->toThrow(RuntimeException::class);
    });

    test('rejected sites can be resubmitted by the same owner', function (): void {
        $owner = portalOwner();

        $site = app(RegisterSite::class)->execute($owner, [
            'name' => 'Resubmit Store',
            'domain' => 'resubmit.example',
        ]);
        $site->update(['status' => SiteStatus::Rejected]);

        $resubmitted = app(RegisterSite::class)->execute($owner, [
            'name' => 'Resubmit Store v2',
            'domain' => 'resubmit.example',
        ]);

        expect($resubmitted->getKey())->toBe($site->getKey())
            ->and($resubmitted->status)->toBe(SiteStatus::Pending)
            ->and($resubmitted->name)->toBe('Resubmit Store v2');

        expect(fn () => app(RegisterSite::class)->execute(portalOwner(), [
            'name' => 'Hijack',
            'domain' => 'resubmit.example',
        ]))->toThrow(ValidationException::class);
    });

    test('public listings exclude unverified-site offers', function (): void {
        $verified = AffiliateSite::factory()->verified()->create();
        $suspended = AffiliateSite::factory()->suspended()->create();

        AffiliateOffer::factory()->published()->forSite($verified)->create([
            'visibility' => 'public',
        ]);
        $suspendedOffer = AffiliateOffer::factory()->published()->forSite($suspended)->create([
            'visibility' => 'public',
        ]);

        $listed = AffiliateOffer::query()
            ->where('status', OfferStatus::Published)
            ->where('visibility', 'public')
            ->whereSiteVerified()
            ->count();

        expect($listed)->toBe(1)
            ->and(app(ResolvePublicOfferQuery::class)->find((string) $suspendedOffer->getKey()))->toBeNull();
    });

    test('links cannot be created for unverified-site offers', function (): void {
        $site = AffiliateSite::factory()->suspended()->create();
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create();

        expect(fn () => app(CreateOfferLink::class)->execute($offer->id, (string) portalAffiliate('NOLINK')->getKey()))
            ->toThrow(RuntimeException::class);
    });

    test('postbacks from unverified sites are refused', function (): void {
        $site = AffiliateSite::factory()->pending()->create([
            'domain' => 'unverified-postback.example',
            'catalog_token_encrypted' => encrypt('site-secret'),
        ]);
        $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
            'visibility' => 'public',
            'currency' => 'MYR',
        ]);
        $link = AffiliateOfferLink::factory()
            ->forOffer($offer)
            ->forAffiliateId((string) portalAffiliate('POSTBACK')->getKey())
            ->create(['currency' => 'MYR']);

        $request = Request::create('/api/affiliate-network/conversions', 'POST', [
            'site' => 'unverified-postback.example',
            'link_code' => $link->link->slug,
            'external_reference' => 'ORDER-1',
            'revenue_minor' => 100,
            'engine_touch_at' => null,
            'network_clicked_at' => now()->toIso8601String(),
        ], [], [], ['HTTP_AUTHORIZATION' => 'Bearer site-secret']);

        $response = app()->call(ReportNetworkConversionController::class, ['request' => $request]);

        expect($response->getStatusCode())->toBe(403);
    });
});
