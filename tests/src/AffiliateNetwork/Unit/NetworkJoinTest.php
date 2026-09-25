<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Contracts\NetworkLedger;
use AIArmada\AffiliateNetwork\Data\NetworkConversionDraft;
use AIArmada\AffiliateNetwork\Enums\ApplicationStatus;
use AIArmada\AffiliateNetwork\Enums\OfferVisibility;
use AIArmada\AffiliateNetwork\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Models\AffiliateSite;
use AIArmada\AffiliateNetwork\Services\OfferManagementService;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateProgramMembership;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Contacting\Data\ContactMethodData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * @return array{AffiliateOffer, User}
 */
function networkJoinFixtures(string $domain): array
{
    $site = AffiliateSite::factory()->verified()->create(['domain' => $domain]);
    $offer = AffiliateOffer::factory()->published()->forSite($site)->create([
        'landing_url' => "https://{$domain}/landing",
        'visibility' => OfferVisibility::Public,
        'requires_approval' => true,
        'external_program_id' => (string) Str::uuid(),
        'metadata' => ['catalog_source' => 'local'],
    ]);

    return [$offer, User::factory()->create()];
}

describe('network identity join', function (): void {
    test('network user without a merchant affiliate applies to a mirrored offer', function (): void {
        [$offer, $user] = networkJoinFixtures('join-apply.example');

        $application = OwnerContext::withOwner($user, fn () => app(OfferManagementService::class)
            ->applyForOffer($offer, (string) $user->getKey(), 'Let me promote.'));

        expect($application->status)->toBe(ApplicationStatus::Pending)
            ->and($application->affiliate_id)->toBe((string) $user->getKey())
            ->and(Affiliate::query()->exists())->toBeFalse()
            ->and(AffiliateProgramMembership::query()->exists())->toBeFalse();
    });

    test('approving the network application grants promotion rights', function (): void {
        [$offer, $user] = networkJoinFixtures('join-approve.example');
        $userKey = (string) $user->getKey();

        $application = OwnerContext::withOwner($user, fn () => app(OfferManagementService::class)
            ->applyForOffer($offer, $userKey));

        app(OfferManagementService::class)->approveApplication($application);

        $service = app(OfferManagementService::class);

        expect($service->isApprovedForOffer($offer, $userKey))->toBeTrue()
            ->and($service->hasAppliedForOffer($offer, $userKey))->toBeTrue()
            ->and($service->applicationStatusForOffer($offer, $userKey))->toBe('approved');
    });

    test('unknown ids still cannot apply', function (): void {
        [$offer] = networkJoinFixtures('join-ghost.example');

        expect(fn () => app(OfferManagementService::class)->applyForOffer($offer, (string) Str::uuid()))
            ->toThrow(ModelNotFoundException::class);
    });

    test('ledger posts a network user conversion to the email-linked affiliate', function (): void {
        $user = User::factory()->create(['email' => 'linked-' . uniqid() . '@example.com']);
        $affiliate = createTestAffiliate();
        $affiliate->addContactMethod(ContactMethodData::email($user->email, 'general'));

        $posted = app(NetworkLedger::class)->post(new NetworkConversionDraft(
            linkId: (string) Str::uuid(),
            offerId: (string) Str::uuid(),
            siteId: null,
            affiliateId: (string) $user->getKey(),
            linkCode: 'JOIN1',
            revenueMinor: 89900,
            currency: 'MYR',
            externalReference: 'ORDER-JOIN-1',
            commissionMinor: 13485,
        ));

        expect($posted)->not->toBeNull()
            ->and($posted->affiliateCode)->toBe($affiliate->code)
            ->and(AffiliateConversion::query()->where('affiliate_id', $affiliate->getKey())->exists())->toBeTrue();
    });

    test('ledger posts nothing for users without a linked affiliate', function (): void {
        $user = User::factory()->create();

        $posted = app(NetworkLedger::class)->post(new NetworkConversionDraft(
            linkId: (string) Str::uuid(),
            offerId: (string) Str::uuid(),
            siteId: null,
            affiliateId: (string) $user->getKey(),
            linkCode: 'JOIN2',
            revenueMinor: 89900,
            currency: 'MYR',
            externalReference: 'ORDER-JOIN-2',
            commissionMinor: 13485,
        ));

        expect($posted)->toBeNull()
            ->and(AffiliateConversion::query()->exists())->toBeFalse();
    });

    test('existing merchant affiliates keep applying with their affiliate id', function (): void {
        [$offer] = networkJoinFixtures('join-affiliate.example');
        $affiliate = createTestAffiliate();

        $application = app(OfferManagementService::class)->applyForOffer($offer, (string) $affiliate->getKey());

        expect($application->affiliate_id)->toBe((string) $affiliate->getKey());
    });
});
