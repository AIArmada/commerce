<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Contracts\NotificationDispatcher;
use AIArmada\AffiliateNetwork\Data\ApplicationStatus;
use AIArmada\AffiliateNetwork\Data\LegStatus;
use AIArmada\AffiliateNetwork\Data\NetworkLegSnapshot;
use AIArmada\AffiliateNetwork\Data\NotificationPayload;
use AIArmada\AffiliateNetwork\Events\ApplicationApproved;
use AIArmada\AffiliateNetwork\Events\ApplicationSubmitted;
use AIArmada\AffiliateNetwork\Events\NetworkConversionRecorded;
use AIArmada\AffiliateNetwork\Events\OfferCreated;
use AIArmada\AffiliateNetwork\Events\OfferUpdated;
use AIArmada\AffiliateNetwork\Features\Catalog\Actions\CreateOffer;
use AIArmada\AffiliateNetwork\Features\Catalog\Models\AffiliateOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Actions\ApplyToOffer;
use AIArmada\AffiliateNetwork\Features\Enrollment\Models\AffiliateOfferApplication;
use AIArmada\AffiliateNetwork\Features\Sites\Models\AffiliateSite;
use AIArmada\Affiliates\Features\Identity\Api\AffiliateRemoval;
use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

final class CapturingNotificationDispatcher implements NotificationDispatcher
{
    /** @var list<array{purpose: string, ownerType: ?string, ownerId: string|int|null, userId: ?string}> */
    public array $dispatched = [];

    public function dispatchToUser(
        string $userId,
        ?string $email,
        string $purpose,
        string $subjectType,
        string $subjectId,
        string $idempotencyKey,
        NotificationPayload $payload,
    ): void {
        $this->dispatched[] = ['purpose' => $purpose, 'ownerType' => null, 'ownerId' => null, 'userId' => $userId];
    }

    public function dispatchToOwner(
        ?string $ownerType,
        string | int | null $ownerId,
        string $purpose,
        string $subjectType,
        string $subjectId,
        string $idempotencyKey,
        NotificationPayload $payload,
    ): void {
        $this->dispatched[] = ['purpose' => $purpose, 'ownerType' => $ownerType, 'ownerId' => $ownerId, 'userId' => null];
    }
}

describe('cross-tenant application flow', function (): void {
    beforeEach(function (): void {
        config(['affiliate-network.owner.enabled' => true, 'affiliates.owner.enabled' => true]);
        $this->dispatcher = new CapturingNotificationDispatcher;
        app()->instance(NotificationDispatcher::class, $this->dispatcher);
    });

    test('applies to an offer owned by a different merchant and notifies that owner', function (): void {
        $merchant = User::factory()->create();

        $offer = OwnerContext::withOwner($merchant, function () use ($merchant): AffiliateOffer {
            $site = AffiliateSite::factory()->verified()->forOwner($merchant)->create(['domain' => 'cross-owner-apply.example']);

            return AffiliateOffer::factory()->published()->forSite($site)->create([
                'requires_approval' => true,
                'visibility' => 'public',
            ]);
        });

        $creator = User::factory()->create();

        $affiliate = OwnerContext::withOwner($creator, fn (): Affiliate => Affiliate::forceCreate([
            'code' => 'XTA' . uniqid(),
            'handle' => 'xta-' . uniqid(),
            'name' => 'Cross Tenant Affiliate',
            'status' => 'active',
            'commission_type' => 'percentage',
            'commission_rate' => 1000,
            'currency' => 'MYR',
            'contact_email' => $creator->email,
            'owner_type' => $creator->getMorphClass(),
            'owner_id' => (string) $creator->getKey(),
        ]));

        $application = OwnerContext::withOwner($creator, fn (): AffiliateOfferApplication => app(ApplyToOffer::class)
            ->execute((string) $offer->getKey(), (string) $affiliate->getKey()));

        expect($application->status)->toBe(ApplicationStatus::Pending)
            ->and($application->affiliate_id)->toBe((string) $affiliate->getKey());

        $notification = collect($this->dispatcher->dispatched)
            ->firstWhere('purpose', 'affiliate-network.application_submitted');

        expect($notification)->not->toBeNull()
            ->and($notification['ownerType'])->toBe($merchant->getMorphClass())
            ->and((string) $notification['ownerId'])->toBe((string) $merchant->getKey());

        OwnerContext::withOwner($creator, function () use ($affiliate): void {
            expect(fn () => app(AffiliateRemoval::class)->delete((string) $affiliate->getKey()))
                ->toThrow(ValidationException::class);

            expect($affiliate->fresh())->not->toBeNull();
        });

        OwnerContext::withOwner($merchant, function () use ($application): void {
            expect($application->fresh())->not->toBeNull();
        });
    });

    test('emits pure DTO events carrying owner coordinates', function (): void {
        Event::fake([ApplicationSubmitted::class, ApplicationApproved::class, OfferCreated::class, OfferUpdated::class]);

        $merchant = User::factory()->create();

        OwnerContext::withOwner($merchant, function () use ($merchant): void {
            $site = AffiliateSite::factory()->verified()->forOwner($merchant)->create(['domain' => 'dto-events.example']);

            app(CreateOffer::class)->execute($site->toRecord(), [
                'name' => 'DTO Offer',
                'requires_approval' => false,
            ]);
        });

        Event::assertDispatched(OfferCreated::class, function (OfferCreated $event) use ($merchant): bool {
            return $event->ownerType === $merchant->getMorphClass()
                && (string) $event->ownerId === (string) $merchant->getKey();
        });
    });

    test('the conversion recorded event is a pure DTO snapshot with counter revenue', function (): void {
        $snapshot = new NetworkLegSnapshot(
            id: 'leg-1',
            linkId: null,
            offerId: 'offer-1',
            siteId: null,
            affiliateId: 'aff-1',
            linkCode: null,
            revenueMinor: 10000,
            revenueCurrency: 'MYR',
            commissionMinor: 1000,
            commissionCurrency: 'MYR',
            feeMinor: 0,
            feeBp: 0,
            payoutMinor: 1000,
            tierRateBp: null,
            tierMinVolumeMinor: null,
            externalReference: 'ORDER-1',
            eventType: 'purchase',
            status: LegStatus::Posted,
            locksAt: null,
            lockedAt: null,
            fulfilledAt: null,
            statementId: null,
            couponCode: null,
            subId: null,
            subId2: null,
            subId3: null,
        );

        $event = new NetworkConversionRecorded($snapshot, 10000, 'MYR');

        expect($event->leg)->toBe($snapshot)
            ->and($event->counterRevenueMinor)->toBe(10000)
            ->and($event->currency)->toBe('MYR');
    });
});
